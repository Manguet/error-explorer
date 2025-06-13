<?php

namespace App\Tests\Unit\EventListener;

use App\Entity\User;
use App\EventListener\EmailVerificationEventListener;
use App\Service\Email\EmailService;
use App\Service\Logs\MonologService;
use PHPUnit\Framework\TestCase;

class EmailVerificationEventListenerTest extends TestCase
{
    private EmailVerificationEventListener $listener;
    private EmailService $emailService;
    private MonologService $monolog;

    protected function setUp(): void
    {
        $this->emailService = $this->createMock(EmailService::class);
        $this->monolog = $this->createMock(MonologService::class);

        $this->listener = new EmailVerificationEventListener(
            $this->emailService,
            $this->monolog
        );
    }

    public function testOnEmailVerifiedSuccess(): void
    {
        $user = $this->createTestUser();
        $ip = '192.168.1.1';

        $this->monolog->expects($this->exactly(2))
            ->method('businessEvent');

        // Email de bienvenue envoyé
        $this->emailService->expects($this->once())
            ->method('sendWelcomeEmail')
            ->with($user);

        $this->listener->onEmailVerified($user, $ip);
    }

    public function testOnEmailVerifiedWithException(): void
    {
        $user = $this->createTestUser();

        // Simuler une exception lors de l'envoi d'email
        $this->emailService->method('sendWelcomeEmail')
            ->willThrowException(new \Exception('Email service error'));

        // L'erreur devrait être loggée
        $this->monolog->expects($this->once())
            ->method('capture')
            ->with(
                $this->stringContains('Erreur lors de l\'envoi de l\'email de bienvenue'),
                MonologService::BUSINESS,
                MonologService::ERROR,
                $this->isType('array')
            );

        // Ne devrait pas lever d'exception
        $this->listener->onEmailVerified($user);
    }

    private function createTestUser(): User
    {
        $user = new User();
        $user->setId(123);
        $user->setEmail('test@example.com');

        return $user;
    }
}
