<?php

namespace App\EventListener;

use App\Entity\User;
use App\Service\EmailService;
use Psr\Log\LoggerInterface;

/**
 * Event Listener pour les changements de mot de passe
 */
class PasswordChangeEventListener
{
    public function __construct(
        private readonly EmailService $emailService,
        private readonly LoggerInterface $logger
    ) {}

    /**
     * Déclenché après qu'un utilisateur ait changé son mot de passe
     */
    public function onPasswordChanged(User $user): void
    {
        try {
            // Envoyer une notification de changement
            $this->emailService->sendPasswordChangeNotification($user);

            $this->logger->info('Notification de changement de mot de passe envoyée', [
                'user_id' => $user->getId(),
                'email' => $user->getEmail(),
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Erreur lors de l\'envoi de la notification de changement de mot de passe', [
                'user_id' => $user->getId(),
                'email' => $user->getEmail(),
                'error' => $e->getMessage()
            ]);
        }
    }
}
