<?php

namespace App\EventListener;

use App\Entity\User;
use App\Service\EmailService;
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
        private readonly EmailService $emailService,
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
            $user->updateLastLoginAt();

            if ($isFirstLogin && $user->isVerified()) {
                $this->emailService->sendWelcomeEmail($user);

                $this->monolog->businessEvent('welcome_email_sent', [
                    'user_id' => $user->getId(),
                    'email' => $user->getEmail(),
                    'trigger' => 'first_login'
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
                'is_verified' => $user->isVerified()
            ]);

            $this->monolog->securityEvent('successful_login', [
                'user_id' => $user->getId(),
                'email' => $user->getEmail(),
                'ip' => $ip,
                'user_agent' => $userAgent,
                'session_id' => $request->getSession()->getId(),
                'is_first_login' => $isFirstLogin
            ]);


        } catch (Exception $e) {
            $this->monolog->capture(
                'Erreur lors de la mise à jour de lastLogin: ' . $e->getMessage(),
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
}
