<?php

namespace App\EventListener;

use App\Entity\User;
use App\Service\Email\EmailPriority;
use App\Service\Email\EmailQueueService;
use App\Service\Logs\MonologService;

/**
 * Event Listener pour les événements de sécurité avancés
 */
class SecurityEventListener
{
    public function __construct(
        private readonly EmailQueueService $emailQueueService,
        private readonly MonologService $monolog
    ) {}

    /**
     * Déclenché lors de tentatives de connexion multiples échouées
     */
    public function onMultipleFailedLogins(User $user, int $attempts, ?string $ip = null): void
    {
        if ($attempts >= 5) {
            $this->emailQueueService->queueEmail(
                type: 'security_alert',
                recipient: $user,
                context: [
                    'alert_type' => 'multiple_failed_logins',
                    'failed_attempts' => $attempts,
                    'suspected_ip' => $ip,
                    'time' => new \DateTimeImmutable(),
                    'security_url' => 'https://errorexplorer.com/account/security'
                ],
                metadata: [
                    'security_alert' => true,
                    'alert_type' => 'brute_force',
                    'attempts' => $attempts
                ],
                priority: EmailPriority::HIGH
            );

            $this->monolog->securityEvent('multiple_failed_logins_alert_sent', [
                'user_id' => $user->getId(),
                'email' => $user->getEmail(),
                'failed_attempts' => $attempts,
                'ip' => $ip,
                'alert_sent' => true
            ]);
        }
    }

    /**
     * Déclenché quand un compte est verrouillé
     */
    public function onAccountLocked(User $user, string $reason, ?string $ip = null): void
    {
        $this->emailQueueService->queueEmail(
            type: 'account_locked',
            recipient: $user,
            context: [
                'lock_reason' => $reason,
                'lock_time' => new \DateTimeImmutable(),
                'suspected_ip' => $ip,
                'unlock_url' => 'https://errorexplorer.com/account/unlock',
                'support_email' => 'support@errorexplorer.com'
            ],
            metadata: [
                'security_notification' => true,
                'action' => 'account_locked',
                'reason' => $reason
            ],
            priority: EmailPriority::HIGH
        );

        $this->monolog->securityEvent('account_locked_notification_sent', [
            'user_id' => $user->getId(),
            'email' => $user->getEmail(),
            'reason' => $reason,
            'ip' => $ip
        ]);
    }
}
