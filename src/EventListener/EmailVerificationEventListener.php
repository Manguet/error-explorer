<?php

namespace App\EventListener;

use App\Entity\User;
use App\Service\Email\EmailQueueService;
use App\Service\Email\EmailPriority;
use App\Service\Logs\MonologService;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;

/**
 * Event Listener modernisé pour la vérification d'email
 */
class EmailVerificationEventListener
{
    public function __construct(
        private readonly EmailQueueService $emailQueueService,
        private readonly MonologService $monolog
    ) {}

    /**
     * Déclenché quand un email est vérifié avec succès
     */
    public function onEmailVerified(User $user, ?string $ip = null): void
    {
        try {
            $this->monolog->securityEvent('email_verification_completed', [
                'user_id' => $user->getId(),
                'email' => $user->getEmail(),
                'ip' => $ip ?? 'unknown',
                'verification_date' => date('Y-m-d H:i:s')
            ]);

            $this->monolog->businessEvent('user_email_verified', [
                'user_id' => $user->getId(),
                'email' => $user->getEmail(),
                'account_status' => 'verified'
            ]);

            // Envoyer l'email de bienvenue via la queue avec un délai de 2 minutes
            $this->emailQueueService->queueWelcomeEmail($user, delayMinutes: 2);

            $this->monolog->businessEvent('welcome_email_queued', [
                'user_id' => $user->getId(),
                'email' => $user->getEmail(),
                'trigger' => 'email_verification',
                'delay_minutes' => 2,
                'queue_system' => true
            ]);

        } catch (Exception $e) {
            $this->monolog->capture(
                'Erreur lors de la mise en queue de l\'email de bienvenue: ' . $e->getMessage(),
                MonologService::BUSINESS,
                MonologService::ERROR,
                [
                    'user_id' => $user->getId(),
                    'email' => $user->getEmail(),
                    'exception' => $e->getTraceAsString(),
                    'trigger' => 'email_verification'
                ]
            );
        }
    }

    /**
     * Déclenché quand un utilisateur demande un renvoi de vérification
     */
    public function onEmailVerificationRequested(User $user, ?string $ip = null): void
    {
        try {
            // Envoyer via la queue avec priorité haute (sécurité)
            $this->emailQueueService->queueEmailVerification($user, delaySeconds: 10);

            $this->monolog->businessEvent('email_verification_resend_queued', [
                'user_id' => $user->getId(),
                'email' => $user->getEmail(),
                'ip' => $ip ?? 'unknown',
                'delay_seconds' => 10,
                'priority' => 'high'
            ]);

        } catch (Exception $e) {
            $this->monolog->capture(
                'Erreur lors de la mise en queue du renvoi de vérification: ' . $e->getMessage(),
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
