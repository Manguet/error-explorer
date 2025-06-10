<?php

namespace App\Controller;

use App\Entity\Plan;
use App\Entity\Subscription;
use App\Entity\PaymentMethod;
use App\Service\StripeService;
use Doctrine\ORM\EntityManagerInterface;
use Stripe\StripeClient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/dashboard/billing')]
#[IsGranted('ROLE_USER')]
class BillingController extends AbstractController
{
    public function __construct(
        private StripeService $stripeService,
        private EntityManagerInterface $entityManager
    ) {}

    #[Route('/', name: 'billing_dashboard')]
    public function dashboard(): Response
    {
        $user = $this->getUser();

        // Récupérer l'abonnement actuel
        $subscription = $this->entityManager->getRepository(Subscription::class)
            ->findOneBy(['user' => $user], ['createdAt' => 'DESC']);

        // Récupérer les méthodes de paiement
        $paymentMethods = $this->entityManager->getRepository(PaymentMethod::class)
            ->findBy(['user' => $user], ['isDefault' => 'DESC', 'createdAt' => 'DESC']);

        // Récupérer les factures
        $invoices = [];
        if ($subscription) {
            $invoices = $this->entityManager->getRepository(\App\Entity\Invoice::class)
                ->findBy(['subscription' => $subscription], ['createdAt' => 'DESC']);
        }

        // Récupérer tous les plans disponibles
        $plans = $this->entityManager->getRepository(Plan::class)->findAll();

        return $this->render('billing/dashboard.html.twig', [
            'subscription' => $subscription,
            'paymentMethods' => $paymentMethods,
            'invoices' => $invoices,
            'plans' => $plans,
            'stripe_public_key' => $this->stripeService->getPublicKey()
        ]);
    }

    #[Route('/checkout/{planId}', name: 'billing_checkout')]
    public function checkout(int $planId, Request $request): Response
    {
        $plan = $this->entityManager->getRepository(Plan::class)->find($planId);
        if (!$plan) {
            throw $this->createNotFoundException('Plan introuvable');
        }

        $billingPeriod = $request->query->get('period', 'monthly');
        if (!in_array($billingPeriod, ['monthly', 'yearly'])) {
            $billingPeriod = 'monthly';
        }

        $user = $this->getUser();

        // Vérifier si l'utilisateur a déjà un abonnement actif
        $existingSubscription = $this->entityManager->getRepository(Subscription::class)
            ->findOneBy(['user' => $user, 'status' => [Subscription::STATUS_ACTIVE, Subscription::STATUS_TRIALING]]);

        if ($existingSubscription) {
            $this->addFlash('warning', 'Vous avez déjà un abonnement actif. Vous pouvez le modifier depuis votre tableau de bord.');
            return $this->redirectToRoute('billing_dashboard');
        }

        return $this->render('billing/checkout.html.twig', [
            'plan' => $plan,
            'billing_period' => $billingPeriod,
            'stripe_public_key' => $this->stripeService->getPublicKey()
        ]);
    }

    #[Route('/create-checkout-session', name: 'billing_create_checkout_session', methods: ['POST'])]
    public function createCheckoutSession(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            $planId = $data['planId'] ?? null;
            $billingPeriod = $data['billingPeriod'] ?? 'monthly';

            if (!$planId) {
                return new JsonResponse(['error' => 'Plan ID requis'], 400);
            }

            $plan = $this->entityManager->getRepository(Plan::class)->find($planId);
            if (!$plan) {
                return new JsonResponse(['error' => 'Plan introuvable'], 404);
            }

            $user = $this->getUser();
            $sessionData = $this->stripeService->createCheckoutSession($user, $plan, $billingPeriod);

            return new JsonResponse([
                'sessionId' => $sessionData['session_id'],
                'url' => $sessionData['url']
            ]);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/success', name: 'billing_success')]
    public function success(Request $request): Response
    {
        $sessionId = $request->query->get('session_id');

        $this->addFlash('success', 'Votre abonnement a été activé avec succès !');

        return $this->render('billing/success.html.twig', [
            'session_id' => $sessionId
        ]);
    }

    #[Route('/cancel', name: 'billing_cancel')]
    public function cancel(): Response
    {
        $this->addFlash('info', 'Votre paiement a été annulé. Vous pouvez réessayer à tout moment.');

        return $this->render('billing/cancel.html.twig');
    }

    #[Route('/change-plan/{planId}', name: 'billing_change_plan', methods: ['POST'])]
    public function changePlan(int $planId, Request $request): Response
    {
        try {
            $plan = $this->entityManager->getRepository(Plan::class)->find($planId);
            if (!$plan) {
                throw $this->createNotFoundException('Plan introuvable');
            }

            $user = $this->getUser();
            $subscription = $this->entityManager->getRepository(Subscription::class)
                ->findOneBy(['user' => $user, 'status' => [Subscription::STATUS_ACTIVE, Subscription::STATUS_TRIALING]]);

            if (!$subscription) {
                $this->addFlash('error', 'Aucun abonnement actif trouvé');
                return $this->redirectToRoute('billing_dashboard');
            }

            $billingPeriod = $request->request->get('billing_period', $subscription->getBillingPeriod());
            $this->stripeService->updateSubscription($subscription, $plan, $billingPeriod);

            $this->addFlash('success', 'Votre plan a été mis à jour avec succès !');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de la mise à jour du plan : ' . $e->getMessage());
        }

        return $this->redirectToRoute('billing_dashboard');
    }

    #[Route('/cancel-subscription', name: 'billing_cancel_subscription', methods: ['POST'])]
    public function cancelSubscription(Request $request): Response
    {
        try {
            $user = $this->getUser();
            $subscription = $this->entityManager->getRepository(Subscription::class)
                ->findOneBy(['user' => $user, 'status' => [Subscription::STATUS_ACTIVE, Subscription::STATUS_TRIALING]]);

            if (!$subscription) {
                $this->addFlash('error', 'Aucun abonnement actif trouvé');
                return $this->redirectToRoute('billing_dashboard');
            }

            $immediately = $request->request->getBoolean('immediately', false);
            $this->stripeService->cancelSubscription($subscription, $immediately);

            if ($immediately) {
                $this->addFlash('success', 'Votre abonnement a été annulé immédiatement.');
            } else {
                $this->addFlash('success', 'Votre abonnement sera annulé à la fin de la période de facturation actuelle.');
            }
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de l\'annulation : ' . $e->getMessage());
        }

        return $this->redirectToRoute('billing_dashboard');
    }

    #[Route('/add-payment-method', name: 'billing_add_payment_method', methods: ['POST'])]
    public function addPaymentMethod(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            $paymentMethodId = $data['payment_method_id'] ?? null;
            $setAsDefault = $data['set_as_default'] ?? false;

            if (!$paymentMethodId) {
                return new JsonResponse(['error' => 'Payment method ID requis'], 400);
            }

            $user = $this->getUser();
            $paymentMethod = $this->stripeService->savePaymentMethod($user, $paymentMethodId, $setAsDefault);

            return new JsonResponse([
                'success' => true,
                'payment_method' => [
                    'id' => $paymentMethod->getId(),
                    'display_name' => $paymentMethod->getDisplayName(),
                    'is_default' => $paymentMethod->isDefault()
                ]
            ]);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/remove-payment-method/{id}', name: 'billing_remove_payment_method', methods: ['DELETE'])]
    public function removePaymentMethod(int $id): JsonResponse
    {
        try {
            $paymentMethod = $this->entityManager->getRepository(PaymentMethod::class)->find($id);

            if (!$paymentMethod || $paymentMethod->getUser() !== $this->getUser()) {
                return new JsonResponse(['error' => 'Méthode de paiement introuvable'], 404);
            }

            // Détacher de Stripe
            $stripe = new StripeClient($_ENV['STRIPE_SECRET_KEY']);
            $stripe->paymentMethods->detach($paymentMethod->getStripePaymentMethodId());

            // Supprimer de la base de données
            $this->entityManager->remove($paymentMethod);
            $this->entityManager->flush();

            return new JsonResponse(['success' => true]);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/set-default-payment-method/{id}', name: 'billing_set_default_payment_method', methods: ['POST'])]
    public function setDefaultPaymentMethod(int $id): JsonResponse
    {
        try {
            $paymentMethod = $this->entityManager->getRepository(PaymentMethod::class)->find($id);

            if (!$paymentMethod || $paymentMethod->getUser() !== $this->getUser()) {
                return new JsonResponse(['error' => 'Méthode de paiement introuvable'], 404);
            }

            $user = $this->getUser();

            // Retirer le statut par défaut des autres méthodes
            $existingMethods = $this->entityManager->getRepository(PaymentMethod::class)
                ->findBy(['user' => $user, 'isDefault' => true]);

            foreach ($existingMethods as $method) {
                $method->setIsDefault(false);
            }

            $paymentMethod->setIsDefault(true);
            $this->entityManager->flush();

            // Mettre à jour dans Stripe
            $customerId = $this->stripeService->createOrUpdateCustomer($user);
            $stripe = new StripeClient($_ENV['STRIPE_SECRET_KEY']);
            $stripe->customers->update($customerId, [
                'invoice_settings' => [
                    'default_payment_method' => $paymentMethod->getStripePaymentMethodId()
                ]
            ]);

            return new JsonResponse(['success' => true]);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/download-invoice/{id}', name: 'billing_download_invoice')]
    public function downloadInvoice(int $id): Response
    {
        $invoice = $this->entityManager->getRepository(\App\Entity\Invoice::class)->find($id);

        if (!$invoice || $invoice->getUser() !== $this->getUser()) {
            throw $this->createNotFoundException('Facture introuvable');
        }

        // TODO: Implémenter la génération de PDF
        $this->addFlash('info', 'Le téléchargement de factures sera bientôt disponible');

        return $this->redirectToRoute('billing_dashboard');
    }

}
