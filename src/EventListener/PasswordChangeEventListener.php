<?php

namespace App\EventListener;

use App\Entity\User;
use App\Service\Email\EmailPriority;
use App\Service\Email\EmailQueueService;
use App\Service\Logs\MonologService;
use Exception;

/**
 * Event Listener pour les changements de mot de passe
 */
class PasswordChangeEventListener
{
    public function __construct(
        private readonly EmailQueueService $emailQueueService,
        private readonly MonologService $monolog
    ) {}

    /**
     * Déclenché quand un mot de passe est changé
     */
    public function onPasswordChanged(User $user, ?string $ip = null, string $method = 'unknown'): void
    {
        try {
            $this->monolog->securityEvent('password_changed', [
                'user_id' => $user->getId(),
                'email' => $user->getEmail(),
                'ip' => $ip ?? 'unknown',
                'method' => $method, // 'reset', 'profile', 'force', etc.
                'timestamp' => date('Y-m-d H:i:s')
            ]);

            // Notification de changement de mot de passe en priorité haute
            $this->emailQueueService->queueEmail(
                type: 'password_changed',
                recipient: $user,
                context: [
                    'change_method' => $method,
                    'change_ip' => $ip,
                    'change_time' => new \DateTimeImmutable(),
                    'account_security_url' => 'https://errorexplorer.com/account/security'
                ],
                metadata: [
                    'security_notification' => true,
                    'change_method' => $method,
                    'user_id' => $user->getId()
                ],
                priority: EmailPriority::HIGH,
                delaySeconds: 30
            );

            $this->monolog->businessEvent('password_change_notification_queued', [
                'user_id' => $user->getId(),
                'email' => $user->getEmail(),
                'method' => $method,
                'priority' => 'high',
                'delay_seconds' => 30
            ]);

        } catch (Exception $e) {
            $this->monolog->capture(
                'Erreur lors de la mise en queue de la notification de changement de mot de passe: ' . $e->getMessage(),
                MonologService::BUSINESS,
                MonologService::ERROR,
                [
                    'user_id' => $user->getId(),
                    'email' => $user->getEmail(),
                    'method' => $method,
                    'exception' => $e->getTraceAsString()
                ]
            );
        }
    }

    /**
     * Déclenché quand une demande de réinitialisation est faite
     */
    public function onPasswordResetRequested(User $user, ?string $ip = null): void
    {
        try {
            // Envoyer l'email de réinitialisation via la queue
            $this->emailQueueService->queuePasswordReset($user);

            $this->monolog->businessEvent('password_reset_email_queued', [
                'user_id' => $user->getId(),
                'email' => $user->getEmail(),
                'ip' => $ip ?? 'unknown',
                'priority' => 'high'
            ]);

        } catch (Exception $e) {
            $this->monolog->capture(
                'Erreur lors de la mise en queue de l\'email de réinitialisation: ' . $e->getMessage(),
                MonologService::SECURITY,
                MonologService::ERROR,
                [
                    'user_id' => $user->getId(),
                    'email' => $user->getEmail(),
                    'exception' => $e->getTraceAsString()
                ]
            );
        }
    }
}
