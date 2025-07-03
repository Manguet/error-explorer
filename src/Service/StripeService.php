<?php

namespace App\Service;

use App\Entity\Plan;
use App\Entity\User;
use App\Entity\Subscription;
use App\Entity\Invoice;
use App\Entity\PaymentMethod;
use Doctrine\ORM\EntityManagerInterface;
use Stripe\StripeClient;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Psr\Log\LoggerInterface;

class StripeService
{
    private StripeClient $stripe;

    public function __construct(
        #[Autowire('%app.stripe.secret_key%')] private string $stripeSecretKey,
        #[Autowire('%app.stripe.public_key%')] private string $stripePublicKey,
        #[Autowire('%app.base_url%')] private readonly string $baseUrl,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger
    ) {
        $this->stripe = new StripeClient($this->stripeSecretKey);
    }

    public function getPublicKey(): string
    {
        return $this->stripePublicKey;
    }

    public function createOrUpdateCustomer(User $user): string
    {
        try {
            // Rechercher un client existant par email
            $existingCustomers = $this->stripe->customers->all([
                'email' => $user->getEmail(),
                'limit' => 1
            ]);

            if ($existingCustomers->data) {
                $customer = $existingCustomers->data[0];
                
                // Mettre à jour les informations si nécessaire
                $customer = $this->stripe->customers->update($customer->id, [
                    'name' => $user->getFullName(),
                    'metadata' => [
                        'user_id' => $user->getId(),
                        'company' => $user->getCompany() ?? ''
                    ]
                ]);
            } else {
                // Créer un nouveau client
                $customer = $this->stripe->customers->create([
                    'email' => $user->getEmail(),
                    'name' => $user->getFullName(),
                    'metadata' => [
                        'user_id' => $user->getId(),
                        'company' => $user->getCompany() ?? ''
                    ]
                ]);
            }

            $this->logger->info('Stripe customer created/updated', [
                'user_id' => $user->getId(),
                'customer_id' => $customer->id
            ]);

            return $customer->id;
        } catch (\Exception $e) {
            $this->logger->error('Failed to create/update Stripe customer', [
                'user_id' => $user->getId(),
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function createCheckoutSession(User $user, Plan $plan, string $billingPeriod = 'monthly'): array
    {
        try {
            $customerId = $this->createOrUpdateCustomer($user);
            
            $priceField = $billingPeriod === 'yearly' ? 'stripePriceIdYearly' : 'stripePriceIdMonthly';
            $priceId = $plan->{'get' . ucfirst($priceField)}();
            
            if (!$priceId) {
                throw new \Exception("Prix Stripe non configuré pour le plan {$plan->getName()} ({$billingPeriod})");
            }

            $session = $this->stripe->checkout->sessions->create([
                'customer' => $customerId,
                'payment_method_types' => ['card'],
                'line_items' => [[
                    'price' => $priceId,
                    'quantity' => 1,
                ]],
                'mode' => 'subscription',
                'success_url' => $this->getSuccessUrl(),
                'cancel_url' => $this->getCancelUrl(),
                'metadata' => [
                    'user_id' => $user->getId(),
                    'plan_id' => $plan->getId(),
                    'billing_period' => $billingPeriod
                ],
                'subscription_data' => [
                    'metadata' => [
                        'user_id' => $user->getId(),
                        'plan_id' => $plan->getId(),
                        'billing_period' => $billingPeriod
                    ],
                    'trial_period_days' => $plan->getTrialDays() ?? 0
                ],
                'allow_promotion_codes' => true
            ]);

            $this->logger->info('Stripe checkout session created', [
                'user_id' => $user->getId(),
                'plan_id' => $plan->getId(),
                'session_id' => $session->id
            ]);

            return [
                'session_id' => $session->id,
                'url' => $session->url
            ];
        } catch (\Exception $e) {
            $this->logger->error('Failed to create checkout session', [
                'user_id' => $user->getId(),
                'plan_id' => $plan->getId(),
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function createSubscription(User $user, Plan $plan, string $paymentMethodId, string $billingPeriod = 'monthly'): Subscription
    {
        try {
            $customerId = $this->createOrUpdateCustomer($user);
            
            // Attacher la méthode de paiement au client
            $this->stripe->paymentMethods->attach($paymentMethodId, [
                'customer' => $customerId
            ]);

            // Définir comme méthode par défaut
            $this->stripe->customers->update($customerId, [
                'invoice_settings' => [
                    'default_payment_method' => $paymentMethodId
                ]
            ]);

            $priceField = $billingPeriod === 'yearly' ? 'stripePriceIdYearly' : 'stripePriceIdMonthly';
            $priceId = $plan->{'get' . ucfirst($priceField)}();

            $subscriptionData = [
                'customer' => $customerId,
                'items' => [[
                    'price' => $priceId,
                ]],
                'payment_behavior' => 'default_incomplete',
                'payment_settings' => [
                    'save_default_payment_method' => 'on_subscription'
                ],
                'expand' => ['latest_invoice.payment_intent'],
                'metadata' => [
                    'user_id' => $user->getId(),
                    'plan_id' => $plan->getId(),
                    'billing_period' => $billingPeriod
                ]
            ];

            if ($plan->getTrialDays() && $plan->getTrialDays() > 0) {
                $subscriptionData['trial_period_days'] = $plan->getTrialDays();
            }

            $stripeSubscription = $this->stripe->subscriptions->create($subscriptionData);

            // Créer l'entité Subscription locale
            $subscription = new Subscription();
            $subscription->setUser($user)
                ->setPlan($plan)
                ->setStripeSubscriptionId($stripeSubscription->id)
                ->setStripeCustomerId($customerId)
                ->setStatus($stripeSubscription->status)
                ->setBillingPeriod($billingPeriod)
                ->setAmount($stripeSubscription->items->data[0]->price->unit_amount / 100)
                ->setCurrency(strtoupper($stripeSubscription->items->data[0]->price->currency))
                ->setCurrentPeriodStart((new \DateTime())->setTimestamp($stripeSubscription->current_period_start))
                ->setCurrentPeriodEnd((new \DateTime())->setTimestamp($stripeSubscription->current_period_end));

            if ($stripeSubscription->trial_start && $stripeSubscription->trial_end) {
                $subscription->setTrialStart((new \DateTime())->setTimestamp($stripeSubscription->trial_start))
                    ->setTrialEnd((new \DateTime())->setTimestamp($stripeSubscription->trial_end));
            }

            $this->entityManager->persist($subscription);
            $this->entityManager->flush();

            $this->logger->info('Subscription created', [
                'user_id' => $user->getId(),
                'subscription_id' => $subscription->getId(),
                'stripe_subscription_id' => $stripeSubscription->id
            ]);

            return $subscription;
        } catch (\Exception $e) {
            $this->logger->error('Failed to create subscription', [
                'user_id' => $user->getId(),
                'plan_id' => $plan->getId(),
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function cancelSubscription(Subscription $subscription, bool $immediately = false): void
    {
        try {
            if ($immediately) {
                $this->stripe->subscriptions->cancel($subscription->getStripeSubscriptionId());
                $subscription->setStatus(Subscription::STATUS_CANCELED)
                    ->setCanceledAt(new \DateTime());
            } else {
                $this->stripe->subscriptions->update($subscription->getStripeSubscriptionId(), [
                    'cancel_at_period_end' => true
                ]);
                $subscription->setCancelAt($subscription->getCurrentPeriodEnd());
            }

            $this->entityManager->flush();

            $this->logger->info('Subscription canceled', [
                'subscription_id' => $subscription->getId(),
                'immediately' => $immediately
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to cancel subscription', [
                'subscription_id' => $subscription->getId(),
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function updateSubscription(Subscription $subscription, Plan $newPlan, string $billingPeriod = null): void
    {
        try {
            $billingPeriod = $billingPeriod ?? $subscription->getBillingPeriod();
            $priceField = $billingPeriod === 'yearly' ? 'stripePriceIdYearly' : 'stripePriceIdMonthly';
            $newPriceId = $newPlan->{'get' . ucfirst($priceField)}();

            $stripeSubscription = $this->stripe->subscriptions->retrieve($subscription->getStripeSubscriptionId());
            
            $this->stripe->subscriptions->update($subscription->getStripeSubscriptionId(), [
                'items' => [[
                    'id' => $stripeSubscription->items->data[0]->id,
                    'price' => $newPriceId,
                ]],
                'proration_behavior' => 'create_prorations',
                'metadata' => [
                    'user_id' => $subscription->getUser()->getId(),
                    'plan_id' => $newPlan->getId(),
                    'billing_period' => $billingPeriod
                ]
            ]);

            $subscription->setPlan($newPlan)
                ->setBillingPeriod($billingPeriod);

            $this->entityManager->flush();

            $this->logger->info('Subscription updated', [
                'subscription_id' => $subscription->getId(),
                'new_plan_id' => $newPlan->getId()
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to update subscription', [
                'subscription_id' => $subscription->getId(),
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function savePaymentMethod(User $user, string $paymentMethodId, bool $setAsDefault = false): PaymentMethod
    {
        try {
            $customerId = $this->createOrUpdateCustomer($user);
            
            // Récupérer les informations de la méthode de paiement depuis Stripe
            $stripePaymentMethod = $this->stripe->paymentMethods->retrieve($paymentMethodId);
            
            // Attacher au client
            $this->stripe->paymentMethods->attach($paymentMethodId, [
                'customer' => $customerId
            ]);

            // Créer l'entité locale
            $paymentMethod = new PaymentMethod();
            $paymentMethod->setUser($user)
                ->setStripePaymentMethodId($paymentMethodId)
                ->setType($stripePaymentMethod->type);

            if ($stripePaymentMethod->type === 'card') {
                $card = $stripePaymentMethod->card;
                $paymentMethod->setCardLast4($card->last4)
                    ->setCardBrand($card->brand)
                    ->setCardExpMonth($card->exp_month)
                    ->setCardExpYear($card->exp_year)
                    ->setCardFingerprint($card->fingerprint)
                    ->setCardCountry($card->country);
            }

            if ($setAsDefault) {
                // Retirer le statut par défaut des autres méthodes
                $existingMethods = $this->entityManager->getRepository(PaymentMethod::class)
                    ->findBy(['user' => $user, 'isDefault' => true]);
                
                foreach ($existingMethods as $method) {
                    $method->setIsDefault(false);
                }

                $paymentMethod->setIsDefault(true);
                
                // Définir comme méthode par défaut dans Stripe
                $this->stripe->customers->update($customerId, [
                    'invoice_settings' => [
                        'default_payment_method' => $paymentMethodId
                    ]
                ]);
            }

            $this->entityManager->persist($paymentMethod);
            $this->entityManager->flush();

            $this->logger->info('Payment method saved', [
                'user_id' => $user->getId(),
                'payment_method_id' => $paymentMethod->getId()
            ]);

            return $paymentMethod;
        } catch (\Exception $e) {
            $this->logger->error('Failed to save payment method', [
                'user_id' => $user->getId(),
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function syncSubscriptionFromStripe(string $stripeSubscriptionId): ?Subscription
    {
        try {
            $stripeSubscription = $this->stripe->subscriptions->retrieve($stripeSubscriptionId);
            
            $subscription = $this->entityManager->getRepository(Subscription::class)
                ->findOneBy(['stripeSubscriptionId' => $stripeSubscriptionId]);

            if (!$subscription) {
                $this->logger->warning('Subscription not found in database', [
                    'stripe_subscription_id' => $stripeSubscriptionId
                ]);
                return null;
            }

            // Synchroniser le statut et les dates
            $subscription->setStatus($stripeSubscription->status)
                ->setCurrentPeriodStart((new \DateTime())->setTimestamp($stripeSubscription->current_period_start))
                ->setCurrentPeriodEnd((new \DateTime())->setTimestamp($stripeSubscription->current_period_end));

            if ($stripeSubscription->canceled_at) {
                $subscription->setCanceledAt((new \DateTime())->setTimestamp($stripeSubscription->canceled_at));
            }

            if ($stripeSubscription->cancel_at) {
                $subscription->setCancelAt((new \DateTime())->setTimestamp($stripeSubscription->cancel_at));
            }

            // Mettre à jour les informations de l'utilisateur
            $user = $subscription->getUser();
            if ($subscription->isActive()) {
                $user->setPlan($subscription->getPlan())
                    ->setPlanExpiresAt($subscription->getCurrentPeriodEnd());
            }

            $this->entityManager->flush();

            $this->logger->info('Subscription synced from Stripe', [
                'subscription_id' => $subscription->getId(),
                'status' => $subscription->getStatus()
            ]);

            return $subscription;
        } catch (\Exception $e) {
            $this->logger->error('Failed to sync subscription from Stripe', [
                'stripe_subscription_id' => $stripeSubscriptionId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    private function getSuccessUrl(): string
    {
        return $this->baseUrl . '/dashboard/billing/success?session_id={CHECKOUT_SESSION_ID}';
    }

    private function getCancelUrl(): string
    {
        return $this->baseUrl . '/dashboard/billing/cancel';
    }
}