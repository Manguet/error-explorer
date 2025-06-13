<?php

namespace App\EventListener;

use App\Entity\User;
use App\Service\EmailService;
use App\Service\Logs\MonologService;

class EmailVerificationEventListener
{
    public function __construct(
        private readonly EmailService $emailService,
        private readonly MonologService $monolog
    ) {}

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

            $this->emailService->sendWelcomeEmail($user);

            $this->monolog->businessEvent('welcome_email_sent', [
                'user_id' => $user->getId(),
                'email' => $user->getEmail(),
                'trigger' => 'email_verification'
            ]);

        } catch (\Exception $e) {
            $this->monolog->capture(
                'Erreur lors de l\'envoi de l\'email de bienvenue: ' . $e->getMessage(),
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
