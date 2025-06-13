<?php

namespace App\Controller\WebHook;

use App\Entity\Invoice;
use App\Entity\Subscription;
use App\Service\StripeService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class WebhookPaymentController extends AbstractController
{
    public function __construct(
        private StripeService $stripeService,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
        #[Autowire('%env(STRIPE_WEBHOOK_SECRET)%')] private string $webhookSecret
    ) {}

    #[Route('/webhook/stripe', name: 'webhook_stripe', methods: ['POST'])]
    public function handleStripeWebhook(Request $request): Response
    {
        $payload = $request->getContent();
        $sigHeader = $request->headers->get('stripe-signature');

        try {
            $event = Webhook::constructEvent($payload, $sigHeader, $this->webhookSecret);
        } catch (\UnexpectedValueException $e) {
            $this->logger->error('Invalid Stripe webhook payload', ['error' => $e->getMessage()]);
            return new Response('Invalid payload', 400);
        } catch (SignatureVerificationException $e) {
            $this->logger->error('Invalid Stripe webhook signature', ['error' => $e->getMessage()]);
            return new Response('Invalid signature', 400);
        }

        $this->logger->info('Stripe webhook received', [
            'type' => $event->type,
            'id' => $event->id
        ]);

        try {
            switch ($event->type) {
                case 'checkout.session.completed':
                    $this->handleCheckoutSessionCompleted($event->data->object);
                    break;

                case 'customer.subscription.created':
                case 'customer.subscription.updated':
                    $this->handleSubscriptionUpdated($event->data->object);
                    break;

                case 'customer.subscription.deleted':
                    $this->handleSubscriptionDeleted($event->data->object);
                    break;

                case 'invoice.payment_succeeded':
                    $this->handleInvoicePaymentSucceeded($event->data->object);
                    break;

                case 'invoice.payment_failed':
                    $this->handleInvoicePaymentFailed($event->data->object);
                    break;

                case 'invoice.created':
                    $this->handleInvoiceCreated($event->data->object);
                    break;

                case 'payment_method.attached':
                    $this->handlePaymentMethodAttached($event->data->object);
                    break;

                default:
                    $this->logger->info('Unhandled Stripe webhook event', ['type' => $event->type]);
            }

            return new Response('Webhook handled successfully', 200);
        } catch (\Exception $e) {
            $this->logger->error('Error handling Stripe webhook', [
                'type' => $event->type,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return new Response('Error processing webhook', 500);
        }
    }

    private function handleCheckoutSessionCompleted($session): void
    {
        $this->logger->info('Processing checkout session completed', [
            'session_id' => $session->id,
            'customer_id' => $session->customer,
            'subscription_id' => $session->subscription
        ]);

        if (!$session->subscription) {
            $this->logger->warning('Checkout session has no subscription', ['session_id' => $session->id]);
            return;
        }

        // Synchroniser l'abonnement depuis Stripe
        $this->stripeService->syncSubscriptionFromStripe($session->subscription);
    }

    private function handleSubscriptionUpdated($subscription): void
    {
        $this->logger->info('Processing subscription updated', [
            'subscription_id' => $subscription->id,
            'status' => $subscription->status
        ]);

        $localSubscription = $this->entityManager->getRepository(Subscription::class)
            ->findOneBy(['stripeSubscriptionId' => $subscription->id]);

        if (!$localSubscription) {
            $this->logger->warning('Subscription not found in database', [
                'stripe_subscription_id' => $subscription->id
            ]);
            return;
        }

        // Mettre à jour le statut et les dates
        $localSubscription->setStatus($subscription->status)
            ->setCurrentPeriodStart((new \DateTime())->setTimestamp($subscription->current_period_start))
            ->setCurrentPeriodEnd((new \DateTime())->setTimestamp($subscription->current_period_end))
            ->setUpdatedAt(new \DateTime());

        if ($subscription->canceled_at) {
            $localSubscription->setCanceledAt((new \DateTime())->setTimestamp($subscription->canceled_at));
        }

        if ($subscription->cancel_at) {
            $localSubscription->setCancelAt((new \DateTime())->setTimestamp($subscription->cancel_at));
        }

        // Mettre à jour les informations de l'utilisateur
        $user = $localSubscription->getUser();
        if ($localSubscription->isActive()) {
            $user->setPlan($localSubscription->getPlan())
                ->setPlanExpiresAt($localSubscription->getCurrentPeriodEnd());
        } else {
            // Si l'abonnement n'est plus actif, revenir au plan gratuit
            $freePlan = $this->entityManager->getRepository(\App\Entity\Plan::class)
                ->findOneBy(['name' => 'Free']) ?? $this->entityManager->getRepository(\App\Entity\Plan::class)->findOneBy([]);

            if ($freePlan) {
                $user->setPlan($freePlan)
                    ->setPlanExpiresAt(null);
            }
        }

        $this->entityManager->flush();

        $this->logger->info('Subscription updated successfully', [
            'subscription_id' => $localSubscription->getId(),
            'status' => $localSubscription->getStatus()
        ]);
    }

    private function handleSubscriptionDeleted($subscription): void
    {
        $this->logger->info('Processing subscription deleted', [
            'subscription_id' => $subscription->id
        ]);

        $localSubscription = $this->entityManager->getRepository(Subscription::class)
            ->findOneBy(['stripeSubscriptionId' => $subscription->id]);

        if (!$localSubscription) {
            $this->logger->warning('Subscription not found in database', [
                'stripe_subscription_id' => $subscription->id
            ]);
            return;
        }

        $localSubscription->setStatus(Subscription::STATUS_CANCELED)
            ->setCanceledAt(new \DateTime())
            ->setUpdatedAt(new \DateTime());

        // Revenir au plan gratuit
        $user = $localSubscription->getUser();
        $freePlan = $this->entityManager->getRepository(\App\Entity\Plan::class)
            ->findOneBy(['name' => 'Free']) ?? $this->entityManager->getRepository(\App\Entity\Plan::class)->findOneBy([]);

        if ($freePlan) {
            $user->setPlan($freePlan)
                ->setPlanExpiresAt(null);
        }

        $this->entityManager->flush();

        $this->logger->info('Subscription deletion processed', [
            'subscription_id' => $localSubscription->getId()
        ]);
    }

    private function handleInvoiceCreated($invoice): void
    {
        $this->logger->info('Processing invoice created', [
            'invoice_id' => $invoice->id,
            'subscription_id' => $invoice->subscription
        ]);

        if (!$invoice->subscription) {
            $this->logger->info('Invoice has no subscription, skipping', ['invoice_id' => $invoice->id]);
            return;
        }

        $subscription = $this->entityManager->getRepository(Subscription::class)
            ->findOneBy(['stripeSubscriptionId' => $invoice->subscription]);

        if (!$subscription) {
            $this->logger->warning('Subscription not found for invoice', [
                'invoice_id' => $invoice->id,
                'subscription_id' => $invoice->subscription
            ]);
            return;
        }

        // Vérifier si la facture existe déjà
        $existingInvoice = $this->entityManager->getRepository(Invoice::class)
            ->findOneBy(['stripeInvoiceId' => $invoice->id]);

        if ($existingInvoice) {
            $this->logger->info('Invoice already exists', ['invoice_id' => $invoice->id]);
            return;
        }

        // Créer la nouvelle facture
        $localInvoice = new Invoice();
        $localInvoice->setSubscription($subscription)
            ->setStripeInvoiceId($invoice->id)
            ->setNumber($invoice->number)
            ->setStatus($invoice->status)
            ->setSubtotal($invoice->subtotal / 100)
            ->setTax($invoice->tax / 100)
            ->setTotal($invoice->total / 100)
            ->setCurrency(strtoupper($invoice->currency))
            ->setPeriodStart((new \DateTime())->setTimestamp($invoice->period_start))
            ->setPeriodEnd((new \DateTime())->setTimestamp($invoice->period_end))
            ->setDescription($invoice->description);

        if ($invoice->due_date) {
            $localInvoice->setDueDate((new \DateTime())->setTimestamp($invoice->due_date));
        }

        // Ajouter les éléments de ligne
        $lineItems = [];
        foreach ($invoice->lines->data as $line) {
            $lineItems[] = [
                'description' => $line->description,
                'amount' => $line->amount / 100,
                'quantity' => $line->quantity,
                'period_start' => $line->period->start,
                'period_end' => $line->period->end
            ];
        }
        $localInvoice->setLineItems($lineItems);

        $this->entityManager->persist($localInvoice);
        $this->entityManager->flush();

        $this->logger->info('Invoice created successfully', [
            'invoice_id' => $localInvoice->getId(),
            'stripe_invoice_id' => $invoice->id
        ]);
    }

    private function handleInvoicePaymentSucceeded($invoice): void
    {
        $this->logger->info('Processing invoice payment succeeded', [
            'invoice_id' => $invoice->id
        ]);

        $localInvoice = $this->entityManager->getRepository(Invoice::class)
            ->findOneBy(['stripeInvoiceId' => $invoice->id]);

        if (!$localInvoice) {
            $this->logger->warning('Invoice not found in database', [
                'stripe_invoice_id' => $invoice->id
            ]);
            return;
        }

        $localInvoice->setStatus(Invoice::STATUS_PAID)
            ->setPaidAt(new \DateTime())
            ->setUpdatedAt(new \DateTime());

        $this->entityManager->flush();

        $this->logger->info('Invoice payment processed', [
            'invoice_id' => $localInvoice->getId()
        ]);

        // TODO: Envoyer un email de confirmation de paiement
    }

    private function handleInvoicePaymentFailed($invoice): void
    {
        $this->logger->info('Processing invoice payment failed', [
            'invoice_id' => $invoice->id
        ]);

        $localInvoice = $this->entityManager->getRepository(Invoice::class)
            ->findOneBy(['stripeInvoiceId' => $invoice->id]);

        if (!$localInvoice) {
            $this->logger->warning('Invoice not found in database', [
                'stripe_invoice_id' => $invoice->id
            ]);
            return;
        }

        $localInvoice->setStatus(Invoice::STATUS_OPEN)
            ->setUpdatedAt(new \DateTime());

        $this->entityManager->flush();

        $this->logger->info('Invoice payment failure processed', [
            'invoice_id' => $localInvoice->getId()
        ]);

        // TODO: Envoyer un email d'échec de paiement
    }

    private function handlePaymentMethodAttached($paymentMethod): void
    {
        $this->logger->info('Processing payment method attached', [
            'payment_method_id' => $paymentMethod->id,
            'customer_id' => $paymentMethod->customer
        ]);

        // Cette méthode peut être utilisée pour synchroniser les méthodes de paiement
        // si nécessaire, mais généralement elle est gérée côté application
    }
}
