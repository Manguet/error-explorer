<?php

namespace App\Service;

use App\Entity\User;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class EmailService
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly LoggerInterface $logger,
        private readonly string $senderEmail = 'error.explorer.contact@gmail.com',
        private readonly string $senderName = 'Error Explorer'
    ) {}

    /**
     * Envoie un email de vérification d'adresse email
     */
    public function sendEmailVerification(User $user): void
    {
        if (!$user->getEmailVerificationToken()) {
            throw new \InvalidArgumentException('L\'utilisateur n\'a pas de token de vérification');
        }

        $verificationUrl = $this->urlGenerator->generate(
            'app_verify_email',
            ['token' => $user->getEmailVerificationToken()],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        $email = (new TemplatedEmail())
            ->from(new Address($this->senderEmail, $this->senderName))
            ->to(new Address($user->getEmail(), $user->getFullName()))
            ->subject('Vérifiez votre adresse email - Error Explorer')
            ->htmlTemplate('emails/email_verification.html.twig')
            ->context([
                'user' => $user,
                'verification_url' => $verificationUrl,
                'expires_at' => (new \DateTimeImmutable())->modify('+24 hours')
            ]);

        try {
            $this->mailer->send($email);

            $this->logger->info('Email de vérification envoyé', [
                'user_id' => $user->getId(),
                'email' => $user->getEmail(),
                'verification_url' => $verificationUrl
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Erreur lors de l\'envoi de l\'email de vérification', [
                'user_id' => $user->getId(),
                'email' => $user->getEmail(),
                'error' => $e->getMessage()
            ]);

            throw new \RuntimeException('Impossible d\'envoyer l\'email de vérification', 0, $e);
        }
    }

    /**
     * Envoie un email de réinitialisation de mot de passe
     */
    public function sendPasswordReset(User $user): void
    {
        if (!$user->getPasswordResetToken()) {
            throw new \InvalidArgumentException('L\'utilisateur n\'a pas de token de réinitialisation');
        }

        $resetUrl = $this->urlGenerator->generate(
            'app_reset_password',
            ['token' => $user->getPasswordResetToken()],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        $email = (new TemplatedEmail())
            ->from(new Address($this->senderEmail, $this->senderName))
            ->to(new Address($user->getEmail(), $user->getFullName()))
            ->subject('Réinitialisation de votre mot de passe - Error Explorer')
            ->htmlTemplate('emails/password_reset.html.twig')
            ->context([
                'user' => $user,
                'reset_url' => $resetUrl,
                'expires_at' => $user->getPasswordResetRequestedAt()?->modify('+24 hours')
            ]);

        try {
            $this->mailer->send($email);

            $this->logger->info('Email de réinitialisation envoyé', [
                'user_id' => $user->getId(),
                'email' => $user->getEmail(),
                'reset_url' => $resetUrl
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Erreur lors de l\'envoi de l\'email de réinitialisation', [
                'user_id' => $user->getId(),
                'email' => $user->getEmail(),
                'error' => $e->getMessage()
            ]);

            throw new \RuntimeException('Impossible d\'envoyer l\'email de réinitialisation', 0, $e);
        }
    }

    /**
     * Envoie un email de bienvenue après vérification
     */
    public function sendWelcomeEmail(User $user): void
    {
        $dashboardUrl = $this->urlGenerator->generate(
            'dashboard_index',
            [],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        $email = (new TemplatedEmail())
            ->from(new Address($this->senderEmail, $this->senderName))
            ->to(new Address($user->getEmail(), $user->getFullName()))
            ->subject('Bienvenue sur Error Explorer !')
            ->htmlTemplate('emails/welcome.html.twig')
            ->context([
                'user' => $user,
                'dashboard_url' => $dashboardUrl,
                'plan' => $user->getPlan()
            ]);

        try {
            $this->mailer->send($email);

            $this->logger->info('Email de bienvenue envoyé', [
                'user_id' => $user->getId(),
                'email' => $user->getEmail()
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Erreur lors de l\'envoi de l\'email de bienvenue', [
                'user_id' => $user->getId(),
                'email' => $user->getEmail(),
                'error' => $e->getMessage()
            ]);

            // Ne pas faire échouer le processus pour l'email de bienvenue
        }
    }

    /**
     * Envoie une notification de changement de mot de passe
     */
    public function sendPasswordChangeNotification(User $user): void
    {
        $email = (new TemplatedEmail())
            ->from(new Address($this->senderEmail, $this->senderName))
            ->to(new Address($user->getEmail(), $user->getFullName()))
            ->subject('Votre mot de passe a été modifié - Error Explorer')
            ->htmlTemplate('emails/password_changed.html.twig')
            ->context([
                'user' => $user,
                'changed_at' => new \DateTimeImmutable()
            ]);

        try {
            $this->mailer->send($email);

            $this->logger->info('Notification de changement de mot de passe envoyée', [
                'user_id' => $user->getId(),
                'email' => $user->getEmail()
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Erreur lors de l\'envoi de la notification de changement de mot de passe', [
                'user_id' => $user->getId(),
                'email' => $user->getEmail(),
                'error' => $e->getMessage()
            ]);

            // Ne pas faire échouer le processus pour la notification
        }
    }
}
