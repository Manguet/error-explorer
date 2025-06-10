<?php

namespace App\Service;

use App\Entity\User;
use App\Entity\Plan;
use App\Entity\Subscription;
use App\Entity\PaymentMethod;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Twig\Environment;
use Psr\Log\LoggerInterface;

class SubscriptionManager
{
    public function __construct(
        private StripeService $stripeService,
        private EntityManagerInterface $entityManager,
        private MailerInterface $mailer,
        private Environment $twig,
        private LoggerInterface $logger
    ) {}

    public function handleTrialExpiration(Subscription $subscription): void
    {
        $user = $subscription->getUser();
        
        if (!$subscription->isOnTrial()) {
            return;
        }

        $trialDaysRemaining = $subscription->getTrialDaysRemaining();
        
        // Envoyer un rappel si il reste 3 jours ou 1 jour
        if (in_array($trialDaysRemaining, [3, 1])) {
            $this->sendTrialEndingEmail($subscription);
        }
        
        // Si l'essai est expiré, gérer la transition
        if ($trialDaysRemaining <= 0) {
            $this->handleTrialExpired($subscription);
        }
    }

    public function handleTrialExpired(Subscription $subscription): void
    {
        $user = $subscription->getUser();
        
        // Vérifier si l'utilisateur a une méthode de paiement valide
        $defaultPaymentMethod = $this->entityManager->getRepository(PaymentMethod::class)
            ->findOneBy(['user' => $user, 'isDefault' => true]);

        if ($defaultPaymentMethod && !$defaultPaymentMethod->isExpired()) {
            // Laisser Stripe gérer la transition automatique
            $this->logger->info('Trial expired, automatic payment will be attempted', [
                'subscription_id' => $subscription->getId(),
                'user_id' => $user->getId()
            ]);
        } else {
            // Pas de méthode de paiement valide, revenir au plan gratuit
            $this->downgradeToFreePlan($user);
            $this->sendTrialExpiredEmail($subscription);
        }
    }

    public function downgradeToFreePlan(User $user): void
    {
        $freePlan = $this->entityManager->getRepository(Plan::class)
            ->findOneBy(['name' => 'Free']) ?? $this->getDefaultFreePlan();

        if ($freePlan) {
            $user->setPlan($freePlan)
                ->setPlanExpiresAt(null);
            
            $this->entityManager->flush();
            
            $this->logger->info('User downgraded to free plan', [
                'user_id' => $user->getId(),
                'plan' => $freePlan->getName()
            ]);
        }
    }

    public function upgradeUserPlan(User $user, Plan $plan, Subscription $subscription): void
    {
        // Mettre à jour le plan de l'utilisateur
        $user->setPlan($plan);
        
        if ($subscription->isActive()) {
            $user->setPlanExpiresAt($subscription->getCurrentPeriodEnd());
        }
        
        // Réinitialiser les compteurs si nécessaire selon le nouveau plan
        if ($plan->getMaxMonthlyErrors() > $user->getCurrentMonthlyErrors() || $plan->getMaxMonthlyErrors() === -1) {
            // Le nouveau plan permet plus d'erreurs, pas besoin de réinitialiser
        }
        
        if ($plan->getMaxProjects() > $user->getCurrentProjectsCount() || $plan->getMaxProjects() === -1) {
            // Le nouveau plan permet plus de projets, pas besoin de réinitialiser
        }
        
        $this->entityManager->flush();
        
        $this->logger->info('User plan upgraded', [
            'user_id' => $user->getId(),
            'new_plan' => $plan->getName(),
            'subscription_id' => $subscription->getId()
        ]);
    }

    public function calculateProration(Subscription $subscription, Plan $newPlan, string $newBillingPeriod): array
    {
        $currentAmount = (float) $subscription->getAmount();
        $currentPeriodStart = $subscription->getCurrentPeriodStart();
        $currentPeriodEnd = $subscription->getCurrentPeriodEnd();
        
        // Calculer les jours restants dans la période actuelle
        $now = new \DateTime();
        $totalDays = $currentPeriodStart->diff($currentPeriodEnd)->days;
        $remainingDays = $now->diff($currentPeriodEnd)->days;
        
        // Montant non utilisé de l'abonnement actuel
        $unusedAmount = ($currentAmount / $totalDays) * $remainingDays;
        
        // Nouveau montant au prorata
        $newAmount = $newBillingPeriod === 'yearly' ? 
            (float) $newPlan->getPriceYearly() : 
            (float) $newPlan->getPriceMonthly();
            
        $proratedAmount = $newAmount - $unusedAmount;
        
        return [
            'unused_amount' => $unusedAmount,
            'new_amount' => $newAmount,
            'prorated_amount' => max(0, $proratedAmount),
            'remaining_days' => $remainingDays,
            'total_days' => $totalDays
        ];
    }

    public function sendPaymentSuccessEmail(Subscription $subscription): void
    {
        try {
            $user = $subscription->getUser();
            
            $email = (new Email())
                ->from('noreply@error-explorer.com')
                ->to($user->getEmail())
                ->subject('Paiement confirmé - Error Explorer')
                ->html($this->twig->render('emails/payment_success.html.twig', [
                    'user' => $user,
                    'subscription' => $subscription
                ]));

            $this->mailer->send($email);
            
            $this->logger->info('Payment success email sent', [
                'user_id' => $user->getId(),
                'subscription_id' => $subscription->getId()
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to send payment success email', [
                'error' => $e->getMessage(),
                'user_id' => $subscription->getUser()->getId()
            ]);
        }
    }

    public function sendPaymentFailedEmail(Subscription $subscription, $invoice = null): void
    {
        try {
            $user = $subscription->getUser();
            
            $email = (new Email())
                ->from('noreply@error-explorer.com')
                ->to($user->getEmail())
                ->subject('Échec du paiement - Error Explorer')
                ->html($this->twig->render('emails/payment_failed.html.twig', [
                    'user' => $user,
                    'subscription' => $subscription,
                    'invoice' => $invoice
                ]));

            $this->mailer->send($email);
            
            $this->logger->info('Payment failed email sent', [
                'user_id' => $user->getId(),
                'subscription_id' => $subscription->getId()
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to send payment failed email', [
                'error' => $e->getMessage(),
                'user_id' => $subscription->getUser()->getId()
            ]);
        }
    }

    public function sendSubscriptionCancelledEmail(Subscription $subscription): void
    {
        try {
            $user = $subscription->getUser();
            
            $email = (new Email())
                ->from('noreply@error-explorer.com')
                ->to($user->getEmail())
                ->subject('Abonnement annulé - Error Explorer')
                ->html($this->twig->render('emails/subscription_cancelled.html.twig', [
                    'user' => $user,
                    'subscription' => $subscription
                ]));

            $this->mailer->send($email);
            
            $this->logger->info('Subscription cancelled email sent', [
                'user_id' => $user->getId(),
                'subscription_id' => $subscription->getId()
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to send subscription cancelled email', [
                'error' => $e->getMessage(),
                'user_id' => $subscription->getUser()->getId()
            ]);
        }
    }

    public function sendTrialEndingEmail(Subscription $subscription): void
    {
        try {
            $user = $subscription->getUser();
            
            $email = (new Email())
                ->from('noreply@error-explorer.com')
                ->to($user->getEmail())
                ->subject('Votre essai se termine bientôt - Error Explorer')
                ->html($this->twig->render('emails/trial_ending.html.twig', [
                    'user' => $user,
                    'subscription' => $subscription
                ]));

            $this->mailer->send($email);
            
            $this->logger->info('Trial ending email sent', [
                'user_id' => $user->getId(),
                'subscription_id' => $subscription->getId(),
                'days_remaining' => $subscription->getTrialDaysRemaining()
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to send trial ending email', [
                'error' => $e->getMessage(),
                'user_id' => $subscription->getUser()->getId()
            ]);
        }
    }

    public function sendTrialExpiredEmail(Subscription $subscription): void
    {
        try {
            $user = $subscription->getUser();
            
            $email = (new Email())
                ->from('noreply@error-explorer.com')
                ->to($user->getEmail())
                ->subject('Votre essai gratuit est terminé - Error Explorer')
                ->html($this->twig->render('emails/trial_expired.html.twig', [
                    'user' => $user,
                    'subscription' => $subscription
                ]));

            $this->mailer->send($email);
            
            $this->logger->info('Trial expired email sent', [
                'user_id' => $user->getId(),
                'subscription_id' => $subscription->getId()
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to send trial expired email', [
                'error' => $e->getMessage(),
                'user_id' => $subscription->getUser()->getId()
            ]);
        }
    }

    private function getDefaultFreePlan(): Plan
    {
        // Créer un plan gratuit par défaut si il n'existe pas
        $plan = new Plan();
        $plan->setName('Free')
            ->setSlug('free')
            ->setDescription('Plan gratuit avec fonctionnalités limitées')
            ->setPriceMonthly('0.00')
            ->setPriceYearly('0.00')
            ->setMaxProjects(1)
            ->setMaxMonthlyErrors(100)
            ->setDataRetentionDays(7)
            ->setHasAdvancedFilters(false)
            ->setHasApiAccess(false)
            ->setHasEmailAlerts(false)
            ->setHasSlackIntegration(false)
            ->setHasPrioritySupport(false)
            ->setHasCustomRetention(false)
            ->setIsActive(true)
            ->setIsPopular(false)
            ->setSortOrder(0);

        $this->entityManager->persist($plan);
        $this->entityManager->flush();

        return $plan;
    }

    public function cleanupExpiredPaymentMethods(): void
    {
        $expiredMethods = $this->entityManager->getRepository(PaymentMethod::class)
            ->createQueryBuilder('pm')
            ->where('pm.type = :card_type')
            ->andWhere('pm.cardExpYear < :current_year OR (pm.cardExpYear = :current_year AND pm.cardExpMonth < :current_month)')
            ->setParameter('card_type', PaymentMethod::TYPE_CARD)
            ->setParameter('current_year', (int) date('Y'))
            ->setParameter('current_month', (int) date('n'))
            ->getQuery()
            ->getResult();

        foreach ($expiredMethods as $method) {
            try {
                // Détacher de Stripe et supprimer localement
                $stripe = new \Stripe\StripeClient($_ENV['STRIPE_SECRET_KEY']);
                $stripe->paymentMethods->detach($method->getStripePaymentMethodId());
                
                $this->entityManager->remove($method);
                
                $this->logger->info('Expired payment method cleaned up', [
                    'payment_method_id' => $method->getId(),
                    'user_id' => $method->getUser()->getId()
                ]);
            } catch (\Exception $e) {
                $this->logger->warning('Failed to cleanup expired payment method', [
                    'payment_method_id' => $method->getId(),
                    'error' => $e->getMessage()
                ]);
            }
        }

        if (count($expiredMethods) > 0) {
            $this->entityManager->flush();
        }
    }
}