<?php

namespace App\Tests\Unit\EventListener;

use App\Entity\User;
use App\EventListener\PasswordChangeEventListener;
use App\Service\Email\EmailService;
use App\Service\Logs\MonologService;
use PHPUnit\Framework\TestCase;

class PasswordChangeEventListenerTest extends TestCase
{
    private PasswordChangeEventListener $listener;
    private EmailService $emailService;
    private MonologService $monolog;

    protected function setUp(): void
    {
        $this->emailService = $this->createMock(EmailService::class);
        $this->monolog = $this->createMock(MonologService::class);

        $this->listener = new PasswordChangeEventListener(
            $this->emailService,
            $this->monolog
        );
    }

    public function testOnPasswordChangedSuccess(): void
    {
        $user = $this->createTestUser();
        $ip = '192.168.1.1';

        // Configurer les expectations
        $this->monolog->expects($this->once())
            ->method('securityEvent')
        ;

        $this->emailService->expects($this->once())
            ->method('sendPasswordChangeNotification')
            ->with($user);

        // Log métier pour la notification envoyée
        $this->monolog->expects($this->once())
            ->method('businessEvent')
            ->with('password_change_notification_sent', $this->isType('array'));

        $this->listener->onPasswordChanged($user, $ip);
    }

    private function createTestUser(): User
    {
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn(123);
        $user->method('getEmail')->willReturn('test@example.com');

        return $user;
    }
}
