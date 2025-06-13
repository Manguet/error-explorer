<?php

namespace App\EventListener;

use App\Entity\User;
use App\Service\EmailService;
use App\Service\Logs\MonologService;
use Exception;

/**
 * Event Listener pour les changements de mot de passe
 */
class PasswordChangeEventListener
{
    public function __construct(
        private readonly EmailService $emailService,
        private readonly MonologService $monolog
    ) {}


    public function onPasswordChanged(User $user, ?string $ip = null): void
    {
        try {
            $this->monolog->securityEvent('password_changed', [
                'user_id' => $user->getId(),
                'email' => $user->getEmail(),
                'ip' => $ip ?? 'unknown',
                'timestamp' => date('Y-m-d H:i:s')
            ]);

            $this->emailService->sendPasswordChangeNotification($user);

            $this->monolog->businessEvent('password_change_notification_sent', [
                'user_id' => $user->getId(),
                'email' => $user->getEmail(),
            ]);

        } catch (Exception $e) {
            $this->monolog->capture(
                'Erreur lors de l\'envoi de la notification de changement de mot de passe: ' . $e->getMessage(),
                MonologService::BUSINESS,
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
