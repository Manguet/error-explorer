<?php

namespace App\EventListener;

use App\Entity\User;
use App\Service\EmailService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;

/**
 * Event Listener pour mettre à jour la date de dernière connexion
 */
#[AsEventListener(event: InteractiveLoginEvent::class)]
class LoginEventListener
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly EmailService $emailService,
        private readonly LoggerInterface $logger
    ) {}

    public function __invoke(InteractiveLoginEvent $event): void
    {
        $user = $event->getAuthenticationToken()->getUser();

        // Vérifier que c'est bien notre entité User
        if (!$user instanceof User) {
            return;
        }

        try {
            // Mettre à jour la date de dernière connexion
            $user->updateLastLoginAt();

            if ($user->isFirstLogin() && $user->isVerified()) {
                $this->emailService->sendWelcomeEmail($user);

                $this->logger->info('Email de bienvenue envoyé', [
                    'user_id' => $user->getId(),
                    'email' => $user->getEmail(),
                ]);
            }

            // Persister les changements
            $this->entityManager->persist($user);
            $this->entityManager->flush();

            // Log pour traçabilité
            $this->logger->info('Dernière connexion mise à jour', [
                'user_id' => $user->getId(),
                'email' => $user->getEmail(),
                'last_login' => $user->getLastLoginAt()?->format('Y-m-d H:i:s'),
                'ip' => $event->getRequest()->getClientIp(),
                'user_agent' => $event->getRequest()->headers->get('User-Agent')
            ]);

        } catch (\Exception $e) {
            // Ne pas faire échouer la connexion à cause d'une erreur de lastLogin
            $this->logger->error('Erreur lors de la mise à jour de lastLogin', [
                'user_id' => $user->getId(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
}
