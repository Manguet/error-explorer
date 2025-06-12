<?php

namespace App\EventListener;

use App\Entity\User;
use App\Service\EmailService;
use Psr\Log\LoggerInterface;

/**
 * Event Listener pour la vérification d'email
 */
class EmailVerificationEventListener
{
    public function __construct(
        private readonly EmailService $emailService,
        private readonly LoggerInterface $logger
    ) {}

    /**
     * Déclenché après qu'un utilisateur ait vérifié son email
     */
    public function onEmailVerified(User $user): void
    {
        try {
            // Envoyer l'email de bienvenue
            $this->emailService->sendWelcomeEmail($user);

            $this->logger->info('Email de bienvenue envoyé après vérification', [
                'user_id' => $user->getId(),
                'email' => $user->getEmail(),
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Erreur lors de l\'envoi de l\'email de bienvenue', [
                'user_id' => $user->getId(),
                'email' => $user->getEmail(),
                'error' => $e->getMessage()
            ]);
        }
    }
}
