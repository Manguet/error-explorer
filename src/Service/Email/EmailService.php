<?php

namespace App\Service\Email;

use App\Entity\User;
use App\Service\Logs\MonologService;
use App\Service\SettingsManager;
use DateTimeImmutable;
use Exception;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Twig\Environment;

/**
 * Service centralisé pour l'envoi d'emails
 *
 * Ce service gère l'envoi de tous les types d'emails de l'application
 * avec retry automatique, mise en cache des templates et logging avancé.
 */
class EmailService
{
    private const DEFAULT_SENDER_EMAIL = 'error.explorer.contact@gmail.com';
    private const DEFAULT_SENDER_NAME = 'Error Explorer';

    // Templates d'emails
    private const TEMPLATES = [
        'email_verification' => 'emails/email_verification.html.twig',
        'password_reset' => 'emails/password_reset.html.twig',
        'password_changed' => 'emails/password_changed.html.twig',
        'welcome' => 'emails/welcome.html.twig',
        'account_suspended' => 'emails/account_suspended.html.twig',
        'plan_expired' => 'emails/plan_expired.html.twig',
        'plan_expiring_warning' => 'emails/plan_expiring_warning.html.twig',
        'new_project_created' => 'emails/new_project_created.html.twig',
        'error_threshold_reached' => 'emails/error_threshold_reached.html.twig',
        'weekly_digest' => 'emails/weekly_digest.html.twig',
        'contact_confirmation' => 'emails/contact_confirmation.html.twig',
        'error_assignment' => 'emails/error_assignment.html.twig'
    ];

    // Sujets par défaut
    private const DEFAULT_SUBJECTS = [
        'email_verification' => 'Vérifiez votre adresse email - Error Explorer',
        'password_reset' => 'Réinitialisation de votre mot de passe - Error Explorer',
        'password_changed' => 'Votre mot de passe a été modifié - Error Explorer',
        'welcome' => 'Bienvenue sur Error Explorer !',
        'account_suspended' => 'Compte temporairement suspendu - Error Explorer',
        'plan_expired' => 'Votre plan a expiré - Error Explorer',
        'plan_expiring_warning' => 'Votre plan expire bientôt - Error Explorer',
        'new_project_created' => 'Nouveau projet créé - Error Explorer',
        'error_threshold_reached' => 'Seuil d\'erreurs atteint - Error Explorer',
        'weekly_digest' => 'Votre résumé hebdomadaire - Error Explorer',
        'contact_confirmation' => 'Confirmation de réception - Error Explorer',
        'error_assignment' => 'Nouvelle erreur assignée - Error Explorer'
    ];

    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly LoggerInterface $logger,
        private readonly MonologService $monolog,
        private readonly CacheInterface $cache,
        private readonly Environment $twig,
        private readonly SettingsManager $settingsManager,
        private readonly string $senderEmail = self::DEFAULT_SENDER_EMAIL,
        private readonly string $senderName = self::DEFAULT_SENDER_NAME,
        private readonly int $maxRetries = 3,
        private readonly int $retryDelayMs = 1000
    ) {}

    /**
     * Envoie un email de vérification d'adresse email
     */
    public function sendEmailVerification(User $user): EmailResult
    {
        if (!$user->getEmailVerificationToken()) {
            throw new InvalidArgumentException('L\'utilisateur n\'a pas de token de vérification');
        }

        $verificationUrl = $this->urlGenerator->generate(
            'app_verify_email',
            ['token' => $user->getEmailVerificationToken()],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        return $this->sendEmail(
            type: 'email_verification',
            recipient: $user,
            context: [
                'user' => $user,
                'verification_url' => $verificationUrl,
                'expires_at' => (new DateTimeImmutable())->modify('+24 hours')
            ],
            metadata: [
                'user_id' => $user->getId(),
                'token_length' => strlen($user->getEmailVerificationToken())
            ]
        );
    }

    /**
     * Envoie un email de réinitialisation de mot de passe
     */
    public function sendPasswordReset(User $user): EmailResult
    {
        if (!$user->getPasswordResetToken()) {
            throw new InvalidArgumentException('L\'utilisateur n\'a pas de token de réinitialisation');
        }

        $resetUrl = $this->urlGenerator->generate(
            'app_reset_password',
            ['token' => $user->getPasswordResetToken()],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        return $this->sendEmail(
            type: 'password_reset',
            recipient: $user,
            context: [
                'user' => $user,
                'reset_url' => $resetUrl,
                'expires_at' => $user->getPasswordResetRequestedAt()?->modify('+24 hours'),
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'Non disponible'
            ],
            metadata: [
                'user_id' => $user->getId(),
                'requested_at' => $user->getPasswordResetRequestedAt()
            ]
        );
    }

    /**
     * Envoie un email de bienvenue après vérification
     */
    public function sendWelcomeEmail(User $user): EmailResult
    {
        $dashboardUrl = $this->urlGenerator->generate(
            'dashboard_index',
            [],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        return $this->sendEmail(
            type: 'welcome',
            recipient: $user,
            context: [
                'user' => $user,
                'dashboard_url' => $dashboardUrl,
                'plan' => $user->getPlan(),
                'features_url' => $this->urlGenerator->generate('features', [], UrlGeneratorInterface::ABSOLUTE_URL)
            ],
            metadata: [
                'user_id' => $user->getId(),
                'plan' => $user->getPlan()?->getName(),
                'is_premium' => $user->getPlan()?->hasPrioritySupport() ?? false
            ],
            priority: EmailPriority::LOW
        );
    }

    /**
     * Envoie une notification de changement de mot de passe
     */
    public function sendPasswordChangeNotification(User $user): EmailResult
    {
        return $this->sendEmail(
            type: 'password_changed',
            recipient: $user,
            context: [
                'user' => $user,
                'changed_at' => new DateTimeImmutable(),
                'support_url' => $this->urlGenerator->generate('contact', [], UrlGeneratorInterface::ABSOLUTE_URL),
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'Non disponible',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Non disponible'
            ],
            metadata: [
                'user_id' => $user->getId()
            ],
            priority: EmailPriority::HIGH
        );
    }

    /**
     * Envoie un email d'alerte de seuil d'erreurs atteint
     */
    public function sendErrorThresholdAlert(User $user, array $projectData): EmailResult
    {
        return $this->sendEmail(
            type: 'error_threshold_reached',
            recipient: $user,
            context: [
                'user' => $user,
                'project' => $projectData,
                'dashboard_url' => $this->urlGenerator->generate('dashboard_index', [], UrlGeneratorInterface::ABSOLUTE_URL)
            ],
            metadata: [
                'user_id' => $user->getId(),
                'project_id' => $projectData['id'] ?? null,
                'error_count' => $projectData['error_count'] ?? 0
            ],
            priority: EmailPriority::HIGH
        );
    }

    /**
     * Envoie le digest hebdomadaire
     */
    public function sendWeeklyDigest(User $user, array $digestData): EmailResult
    {
        return $this->sendEmail(
            type: 'weekly_digest',
            recipient: $user,
            context: [
                'user' => $user,
                'digest' => $digestData,
                'dashboard_url' => $this->urlGenerator->generate('dashboard_index', [], UrlGeneratorInterface::ABSOLUTE_URL)
            ],
            metadata: [
                'user_id' => $user->getId(),
                'projects_count' => count($digestData['projects'] ?? []),
                'total_errors' => $digestData['total_errors'] ?? 0
            ],
            priority: EmailPriority::LOW
        );
    }

    /**
     * Envoie une notification d'assignation d'erreur
     */
    public function sendErrorAssignmentNotification($assignee, $errorGroup, $project, $assignedBy = null): EmailResult
    {
        $errorUrl = $this->urlGenerator->generate(
            'dashboard_error_detail',
            [
                'projectSlug' => $project->getSlug(),
                'id' => $errorGroup->getId()
            ],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        $projectUrl = $this->urlGenerator->generate(
            'projects_show',
            ['slug' => $project->getSlug()],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        return $this->sendEmail(
            type: 'error_assignment',
            recipient: $assignee,
            context: [
                'user' => $assignee,
                'error' => $errorGroup,
                'project' => $project,
                'assigned_by' => $assignedBy,
                'error_url' => $errorUrl,
                'project_url' => $projectUrl,
                'assigned_at' => $errorGroup->getAssignedAt(),
                'dashboard_url' => $this->urlGenerator->generate('dashboard_index', [], UrlGeneratorInterface::ABSOLUTE_URL)
            ],
            metadata: [
                'assignee_id' => $assignee->getId(),
                'error_id' => $errorGroup->getId(),
                'project_id' => $project->getId(),
                'assigned_by_id' => $assignedBy?->getId()
            ],
        );
    }

    /**
     * Méthode générique pour envoyer un email
     */
    public function sendEmail(
        string $type,
        User $recipient,
        array $context = [],
        ?string $subject = null,
        ?string $template = null,
        array $metadata = [],
        EmailPriority $priority = EmailPriority::NORMAL
    ): EmailResult {
        $startTime = microtime(true);

        // Validation
        if (!isset(self::TEMPLATES[$type]) && !$template) {
            throw new InvalidArgumentException("Type d'email non supporté : $type");
        }

        // Préparation des données
        $emailTemplate = $template ?? self::TEMPLATES[$type];
        $emailSubject = $subject ?? self::DEFAULT_SUBJECTS[$type];

        // Enrichissement du contexte
        $enrichedContext = array_merge([
            'app_name' => 'Error Explorer',
            'year' => date('Y'),
            'support_email' => $this->senderEmail,
            'unsubscribe_url' => $this->generateUnsubscribeUrl($recipient),
            'app_url' => $this->settingsManager->getSetting('general.site_url', 'http://localhost')
        ], $context);

        // Création de l'email
        $email = $this->createEmail($recipient, $emailSubject, $emailTemplate, $enrichedContext);

        // Envoi avec retry
        $result = $this->sendWithRetry($email, $type, $recipient, $metadata, $priority);

        // Logging des performances
        $executionTime = (microtime(true) - $startTime) * 1000;

        $this->monolog->businessEvent('email_sent', [
            'type' => $type,
            'recipient' => $recipient->getEmail(),
            'success' => $result->isSuccess(),
            'attempts' => $result->getAttempts(),
            'execution_time_ms' => $executionTime,
            'priority' => $priority->value,
            'metadata' => $metadata
        ]);

        return $result;
    }

    /**
     * Envoie un email avec système de retry
     */
    private function sendWithRetry(
        TemplatedEmail $email,
        string $type,
        User $recipient,
        array $metadata,
        EmailPriority $priority
    ): EmailResult {
        $attempts = 0;
        $lastException = null;

        while ($attempts < $this->maxRetries) {
            $attempts++;

            try {
                $this->mailer->send($email);

                $this->logger->info("Email envoyé avec succès : $type", [
                    'type' => $type,
                    'recipient' => $recipient->getEmail(),
                    'attempts' => $attempts,
                    'priority' => $priority->value,
                    'metadata' => $metadata
                ]);

                return EmailResult::success($attempts, $metadata);

            } catch (TransportExceptionInterface $e) {
                $lastException = $e;

                $this->logger->warning("Échec envoi email (tentative $attempts/$this->maxRetries) : $type", [
                    'type' => $type,
                    'recipient' => $recipient->getEmail(),
                    'error' => $e->getMessage(),
                    'attempt' => $attempts,
                    'metadata' => $metadata
                ]);

                if ($attempts < $this->maxRetries) {
                    usleep($this->retryDelayMs * 1000 * $attempts);
                }
            }
        }

        $this->logger->error("Échec définitif envoi email : $type", [
            'type' => $type,
            'recipient' => $recipient->getEmail(),
            'total_attempts' => $attempts,
            'final_error' => $lastException?->getMessage(),
            'metadata' => $metadata
        ]);

        if ($priority === EmailPriority::HIGH) {
            $this->monolog->capture(
                "CRITIQUE: Échec envoi email prioritaire $type pour {$recipient->getEmail()}",
                MonologService::BUSINESS,
                MonologService::CRITICAL,
                [
                    'type' => $type,
                    'recipient' => $recipient->getEmail(),
                    'error' => $lastException?->getMessage(),
                    'metadata' => $metadata
                ]
            );
        }

        return EmailResult::failure($attempts, $lastException?->getMessage(), $metadata);
    }

    /**
     * Crée un objet email Symfony
     */
    private function createEmail(User $recipient, string $subject, string $template, array $context): TemplatedEmail
    {
        return (new TemplatedEmail())
            ->from(new Address($this->senderEmail, $this->senderName))
            ->to(new Address($recipient->getEmail(), $recipient->getFullName()))
            ->subject($subject)
            ->htmlTemplate($template)
            ->context($context);
    }

    /**
     * Génère une URL de désabonnement
     */
    private function generateUnsubscribeUrl(User $user): string
    {
        return $this->urlGenerator->generate(
            'user_unsubscribe',
            ['token' => 'unsubscribe_' . $user->getId()],
            UrlGeneratorInterface::ABSOLUTE_URL
        );
    }

    /**
     * Valide qu'un template d'email existe
     */
    public function validateTemplate(string $template): bool
    {
        try {
            return $this->cache->get(
                "email_template_exists_" . md5($template),
                function (ItemInterface $item) use ($template) {
                    $item->expiresAfter(3600); // Cache 1h
                    return $this->twig->getLoader()->exists($template);
                }
            );
        } catch (Exception) {
            return false;
        }
    }

    /**
     * Prévisualise un email (pour debug/test)
     */
    public function previewEmail(string $type, User $recipient, array $context = []): string
    {
        if (!isset(self::TEMPLATES[$type])) {
            throw new InvalidArgumentException("Type d'email non supporté : $type");
        }

        $enrichedContext = array_merge([
            'app_name' => 'Error Explorer',
            'year' => date('Y'),
            'support_email' => $this->senderEmail,
            'unsubscribe_url' => $this->generateUnsubscribeUrl($recipient),
            'preview_mode' => true,
            'user' => $recipient,
            'dashboard_url' => $this->urlGenerator->generate('dashboard_index', [], UrlGeneratorInterface::ABSOLUTE_URL),
            'verification_url' => $this->urlGenerator->generate(
                'app_verify_email',
                ['token' => 'test_token'],
                UrlGeneratorInterface::ABSOLUTE_URL
            ),
            'expires_at' => (new DateTimeImmutable())->modify('+24 hours'),
            'token' => $recipient->getEmailVerificationToken(),
            'plan' => $recipient->getPlan(),
        ], $context);

        return $this->twig->render(self::TEMPLATES[$type], $enrichedContext);
    }
}
