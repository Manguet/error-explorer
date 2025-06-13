<?php

namespace App\EventListener;

use App\Entity\User;
use App\Service\Email\EmailPriority;
use App\Service\Email\EmailQueueService;
use App\Service\Email\EmailService;
use App\Service\Logs\MonologService;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;

#[AsEventListener(event: InteractiveLoginEvent::class)]
class LoginEventListener
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly EmailQueueService $emailQueueService,
        private readonly MonologService $monolog
    ) {}

    public function __invoke(InteractiveLoginEvent $event): void
    {
        $user = $event->getAuthenticationToken()->getUser();

        if (!$user instanceof User) {
            return;
        }

        $request = $event->getRequest();
        $ip = $request->getClientIp();
        $userAgent = $request->headers->get('User-Agent');

        try {
            $this->monolog->loginAttempt($user->getUserIdentifier(), true, $ip);

            $isFirstLogin = $user->isFirstLogin();
            $wasUnverified = !$user->isVerified();

            $user->updateLastLoginAt();

            // Cas spécial : première connexion d'un utilisateur vérifié
            if ($isFirstLogin && $user->isVerified()) {
                // Email de bienvenue avec délai pour éviter le spam
                $this->emailQueueService->queueWelcomeEmail($user, delayMinutes: 1);

                $this->monolog->businessEvent('welcome_email_queued', [
                    'user_id' => $user->getId(),
                    'email' => $user->getEmail(),
                    'trigger' => 'first_login',
                    'delay_minutes' => 1
                ]);
            }

            $this->entityManager->persist($user);
            $this->entityManager->flush();

            $this->monolog->businessEvent('user_login_complete', [
                'user_id' => $user->getId(),
                'email' => $user->getEmail(),
                'last_login' => $user->getLastLoginAt()?->format('Y-m-d H:i:s'),
                'ip' => $ip,
                'user_agent' => $userAgent,
                'is_first_login' => $isFirstLogin,
                'is_verified' => $user->isVerified(),
                'was_unverified' => $wasUnverified,
            ]);

            $this->monolog->securityEvent('successful_login', [
                'user_id' => $user->getId(),
                'email' => $user->getEmail(),
                'ip' => $ip,
                'user_agent' => $userAgent,
                'session_id' => $request->getSession()->getId(),
                'is_first_login' => $isFirstLogin,
            ]);

        } catch (Exception $e) {
            $this->monolog->capture(
                'Erreur lors du traitement de la connexion: ' . $e->getMessage(),
                MonologService::SECURITY,
                MonologService::ERROR,
                [
                    'user_id' => $user->getId(),
                    'email' => $user->getEmail(),
                    'ip' => $ip,
                    'exception' => $e->getTraceAsString()
                ]
            );
        }
    }

    /**
     * Met en queue une notification de connexion
     */
    private function queueLoginNotification(User $user, ?string $ip, ?string $userAgent): void
    {
        $this->emailQueueService->queueEmail(
            type: 'login_notification',
            recipient: $user,
            context: [
                'login_ip' => $ip,
                'login_user_agent' => $userAgent,
                'login_time' => new \DateTimeImmutable(),
            ],
            metadata: [
                'notification_type' => 'login',
                'user_id' => $user->getId()
            ],
            priority: EmailPriority::LOW, // 5 minutes de délai
            delaySeconds: 300
        );
    }
}
