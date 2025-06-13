<?php

namespace App\EventListener;

use App\Entity\User;
use App\Service\Email\EmailPriority;
use App\Service\Email\EmailQueueService;

/**
 * Event Listener pour les événements business
 */
class BusinessEventListener
{
    public function __construct(
        private readonly EmailQueueService $emailQueueService,
    ) {}

    /**
     * Déclenché quand un plan expire bientôt
     */
    public function onPlanExpiringSoon(User $user, int $daysRemaining): void
    {
        $this->emailQueueService->queueEmail(
            type: 'plan_expiring_warning',
            recipient: $user,
            context: [
                'days_remaining' => $daysRemaining,
                'plan' => $user->getPlan(),
                'renewal_url' => 'https://errorexplorer.com/billing/renew',
            ],
            metadata: [
                'business_notification' => true,
                'plan_id' => $user->getPlan()?->getId(),
                'days_remaining' => $daysRemaining
            ],
        );
    }

    /**
     * Déclenché quand un plan a expiré
     */
    public function onPlanExpired(User $user): void
    {
        $this->emailQueueService->queueEmail(
            type: 'plan_expired',
            recipient: $user,
            context: [
                'expired_plan' => $user->getPlan(),
                'renewal_url' => 'https://errorexplorer.com/billing/renew',
                'contact_support' => 'support@errorexplorer.com'
            ],
            metadata: [
                'business_notification' => true,
                'expired_plan_id' => $user->getPlan()?->getId()
            ],
            priority: EmailPriority::HIGH
        );
    }

    /**
     * Déclenché quand un seuil d'erreurs est atteint
     */
    public function onErrorThresholdReached(User $user, array $projectData): void
    {
        $this->emailQueueService->queueErrorAlert($user, $projectData);
    }
}
