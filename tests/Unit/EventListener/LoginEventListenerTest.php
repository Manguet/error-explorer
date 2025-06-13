<?php

namespace App\Tests\Unit\EventListener;

use App\Entity\User;
use App\EventListener\LoginEventListener;
use App\Service\EmailService;
use App\Service\Logs\MonologService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;

class LoginEventListenerTest extends TestCase
{
    private LoginEventListener $listener;
    private EntityManagerInterface $entityManager;
    private EmailService $emailService;
    private MonologService $monolog;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->emailService = $this->createMock(EmailService::class);
        $this->monolog = $this->createMock(MonologService::class);

        $this->listener = new LoginEventListener(
            $this->entityManager,
            $this->emailService,
            $this->monolog
        );
    }

    public function testInvokeWithNonUserEntity(): void
    {
        // Créer un mock d'utilisateur qui n'est pas une instance de User
        $nonUserEntity = $this->createMock(UserInterface::class);
        $event = $this->createInteractiveLoginEvent($nonUserEntity);

        $this->entityManager->expects($this->never())->method('persist');
        $this->monolog->expects($this->never())->method('loginAttempt');

        $this->listener->__invoke($event);
    }

    public function testInvokeWithUserFirstLogin(): void
    {
        $user = $this->createTestUser();
        $user->setLastLoginAt(null); // Premier login
        $user->setIsVerified(true);

        $event = $this->createInteractiveLoginEvent($user);

        // Configurer les expectations
        $this->monolog->expects($this->once())
            ->method('loginAttempt')
            ->with('test@example.com', true, '192.168.1.1');

        $this->emailService->expects($this->once())
            ->method('sendWelcomeEmail')
            ->with($user);

        // Corriger le nombre d'appels businessEvent
        $this->monolog->expects($this->exactly(2))
            ->method('businessEvent');

        $this->monolog->expects($this->once())
            ->method('securityEvent')
            ->with('successful_login', $this->isType('array'));

        $this->entityManager->expects($this->once())->method('persist')->with($user);
        $this->entityManager->expects($this->once())->method('flush');

        $this->listener->__invoke($event);
    }

    private function createTestUser(): User
    {
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn(123);
        $user->method('getEmail')->willReturn('test@example.com');
        $user->method('getUserIdentifier')->willReturn('test@example.com');
        $user->method('isVerified')->willReturn(true);
        $user->method('getLastLoginAt')->willReturn(null);
        $user->method('isFirstLogin')->willReturn(true);

        return $user;
    }

    private function createInteractiveLoginEvent($user): InteractiveLoginEvent
    {
        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        $request = Request::create('/login');
        $request->server->set('REMOTE_ADDR', '192.168.1.1');
        $request->headers->set('User-Agent', 'Mozilla/5.0 Test');

        $session = $this->createMock(SessionInterface::class);
        $session->method('getId')->willReturn('session_123');
        $request->setSession($session);

        return new InteractiveLoginEvent($request, $token);
    }
}
