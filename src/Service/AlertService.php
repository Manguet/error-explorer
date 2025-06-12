<?php

namespace App\Service;

use App\Entity\ErrorGroup;
use App\Entity\PerformanceMetric;
use App\Entity\Project;
use App\Entity\UptimeCheck;
use App\Entity\User;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Twig\Environment;
use Psr\Log\LoggerInterface;

class AlertService
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly Environment $twig,
        private readonly LoggerInterface $logger,
        private readonly SettingsManager $settingsManager,
        private readonly ChatNotificationService $chatNotificationService
    ) {}

    public function sendErrorAlert(ErrorGroup $errorGroup, Project $project, User $user): bool
    {
        try {
            // Vérifier si les alertes sont activées globalement par l'administrateur
            if (!$this->settingsManager->getSetting('email.error_alerts', false)) {
                $this->logger->info('Alert not sent, global error alerts are disabled by administrator', [
                    'project_id' => $project->getId(),
                    'error_group_id' => $errorGroup->getId()
                ]);
                return false;
            }

            // Vérifier si les alertes sont activées pour ce projet
            if (!$project->isAlertsEnabled()) {
                $this->logger->info('Alert not sent, alerts are disabled for project', [
                    'project_id' => $project->getId(),
                    'error_group_id' => $errorGroup->getId()
                ]);
                return false;
            }

            // Vérifier si l'utilisateur souhaite recevoir des alertes
            if (!$user->isEmailAlertsEnabled()) {
                $this->logger->info('Alert not sent, user has disabled email alerts', [
                    'user_email' => $user->getEmail(),
                    'project_id' => $project->getId(),
                    'error_group_id' => $errorGroup->getId()
                ]);
                return false;
            }

            // Vérifier les seuils d'alerte
            if (!$this->shouldTriggerAlert($errorGroup, $project)) {
                $this->logger->info('Alert not sent, conditions not met', [
                    'error_group_id' => $errorGroup->getId(),
                    'project_id' => $project->getId(),
                    'user_email' => $user->getEmail()
                ]);
                return false;
            }

            // Créer et envoyer l'email d'alerte
            $email = $this->createErrorAlertEmail($errorGroup, $project, $user);
            $this->mailer->send($email);

            // Envoyer les notifications Slack/Discord
            $slackSent = $this->chatNotificationService->sendSlackNotification($errorGroup, $project, $user);
            $discordSent = $this->chatNotificationService->sendDiscordNotification($errorGroup, $project, $user);

            // Mettre à jour le timestamp de dernière alerte
            $errorGroup->setLastAlertSentAt(new \DateTime());

            $this->logger->info('Alert sent successfully', [
                'error_group_id' => $errorGroup->getId(),
                'project_id' => $project->getId(),
                'user_email' => $user->getEmail(),
                'slack_sent' => $slackSent,
                'discord_sent' => $discordSent
            ]);

            return true;

        } catch (\Exception $e) {
            $this->logger->error('Failed to send error alert', [
                'error' => $e->getMessage(),
                'error_group_id' => $errorGroup->getId(),
                'project_id' => $project->getId(),
                'user_email' => $user->getEmail()
            ]);

            return false;
        }
    }

    public function sendProjectSummaryAlert(Project $project, User $user, array $stats): bool
    {
        try {
            // Vérifier si les alertes sont activées globalement par l'administrateur
            if (!$this->settingsManager->getSetting('email.error_alerts', false)) {
                $this->logger->info('Summary not sent, global error alerts are disabled by administrator', [
                    'project_id' => $project->getId(),
                    'user_email' => $user->getEmail()
                ]);
                return false;
            }

            if (!$project->isDailySummaryEnabled()) {
                return false;
            }

            if (!$user->isEmailAlertsEnabled()) {
                return false;
            }

            $email = $this->createProjectSummaryEmail($project, $user, $stats);
            $this->mailer->send($email);

            $this->logger->info('Daily summary sent successfully', [
                'project_id' => $project->getId(),
                'user_email' => $user->getEmail()
            ]);

            return true;

        } catch (\Exception $e) {
            $this->logger->error('Failed to send daily summary', [
                'error' => $e->getMessage(),
                'project_id' => $project->getId(),
                'user_email' => $user->getEmail()
            ]);

            return false;
        }
    }

    private function shouldTriggerAlert(ErrorGroup $errorGroup, Project $project): bool
    {
        $now = new \DateTime();

        // Vérifier la fréquence d'alerte pour éviter le spam
        $alertCooldown = $project->getAlertCooldownMinutes() ?? 30; // 30 minutes par défaut
        if ($errorGroup->getLastAlertSentAt()) {
            $timeSinceLastAlert = $now->getTimestamp() - $errorGroup->getLastAlertSentAt()->getTimestamp();
            if ($timeSinceLastAlert < ($alertCooldown * 60)) {
                return false;
            }
        }

        // Vérifier le seuil d'occurrences
        $threshold = $project->getAlertThreshold() ?? 1; // 1 par défaut
        if ($errorGroup->getOccurrenceCount() < $threshold) {
            return false;
        }

        // Vérifier les filtres par statut
        $alertStatuses = $project->getAlertStatusFilters() ?? ['open'];
        if (!in_array($errorGroup->getStatus(), $alertStatuses, true)) {
            return false;
        }

        // Vérifier les filtres par environnement
        $alertEnvironments = $project->getAlertEnvironmentFilters() ?? [];
        if (!empty($alertEnvironments) && !in_array($errorGroup->getEnvironment(), $alertEnvironments, true)) {
            return false;
        }

        return true;
    }

    private function createErrorAlertEmail(ErrorGroup $errorGroup, Project $project, User $user): Email
    {
        $subject = sprintf(
            '[%s] %s - %s',
            $project->getName(),
            $this->getAlertPriorityLabel($errorGroup),
            $errorGroup->getTitle()
        );

        $html = $this->twig->render('emails/error_alert.html.twig', [
            'errorGroup' => $errorGroup,
            'project' => $project,
            'user' => $user,
            'dashboardUrl' => $this->generateDashboardUrl($errorGroup),
            'unsubscribeUrl' => $this->generateUnsubscribeUrl($user, $project)
        ]);

        return (new Email())
            ->from('alerts@errorexplorer.com')
            ->to($user->getEmail())
            ->subject($subject)
            ->html($html);
    }

    private function createProjectSummaryEmail(Project $project, User $user, array $stats): Email
    {
        $subject = sprintf('[%s] Résumé quotidien - %d nouvelles erreurs',
            $project->getName(),
            $stats['new_errors'] ?? 0
        );

        $html = $this->twig->render('emails/daily_summary.html.twig', [
            'project' => $project,
            'user' => $user,
            'stats' => $stats,
            'dashboardUrl' => $this->generateProjectDashboardUrl($project),
            'unsubscribeUrl' => $this->generateUnsubscribeUrl($user, $project)
        ]);

        return (new Email())
            ->from('alerts@errorexplorer.com')
            ->to($user->getEmail())
            ->subject($subject)
            ->html($html);
    }

    private function getAlertPriorityLabel(ErrorGroup $errorGroup): string
    {
        // Déterminer la priorité basée sur l'erreur
        if ($errorGroup->getHttpStatusCode() >= 500) {
            return '🚨 CRITIQUE';
        }

        if ($errorGroup->getOccurrenceCount() > 10) {
            return '⚠️ ÉLEVÉE';
        }

        if (in_array($errorGroup->getErrorType(), ['exception', 'error'])) {
            return '🔴 MOYENNE';
        }

        return '🟡 FAIBLE';
    }

    private function generateDashboardUrl(ErrorGroup $errorGroup): string
    {
        // TODO: Générer l'URL complète vers le dashboard
        return sprintf('https://errorexplorer.com/dashboard/error/%d', $errorGroup->getId());
    }

    private function generateProjectDashboardUrl(Project $project): string
    {
        // TODO: Générer l'URL complète vers le dashboard du projet
        return sprintf('https://errorexplorer.com/dashboard/project/%s', $project->getSlug());
    }

    private function generateUnsubscribeUrl(User $user, Project $project): string
    {
        // TODO: Générer un token sécurisé pour la désinscription
        return sprintf('https://errorexplorer.com/unsubscribe/%d/%d', $user->getId(), $project->getId());
    }

    /**
     * Vérifie si une erreur nécessite une alerte critique immédiate
     */
    public function isCriticalError(ErrorGroup $errorGroup): bool
    {
        // Erreurs serveur critiques
        if ($errorGroup->getHttpStatusCode() >= 500) {
            return true;
        }

        // Erreurs avec de nombreuses occurrences rapidement
        if ($errorGroup->getOccurrenceCount() > 5) {
            $timeSinceFirst = (new \DateTime())->getTimestamp() - $errorGroup->getFirstSeen()?->getTimestamp();
            if ($timeSinceFirst < 300) { // 5 minutes
                return true;
            }
        }

        // Types d'erreurs critiques
        $criticalTypes = ['fatal', 'error', 'exception'];
        if (in_array($errorGroup->getErrorType(), $criticalTypes, true)) {
            return true;
        }

        return false;
    }

    /**
     * Envoie une alerte critique immédiate
     */
    public function sendCriticalAlert(ErrorGroup $errorGroup, Project $project, User $user): bool
    {
        try {
            // Vérifier si les alertes sont activées globalement par l'administrateur
            if (!$this->settingsManager->getSetting('email.error_alerts', false)) {
                $this->logger->info('Critical alert not sent, global error alerts are disabled by administrator', [
                    'project_id' => $project->getId(),
                    'error_group_id' => $errorGroup->getId()
                ]);
                return false;
            }

            if (!$user->isCriticalAlertsEnabled()) {
                return false;
            }

            // Vérifier la fréquence d'alerte pour éviter le spam même pour les alertes critiques
            $now = new \DateTime();
            $alertCooldown = $project->getAlertCooldownMinutes() ?? 30; // 30 minutes par défaut
            $lastAlertSentAt = $errorGroup->getLastAlertSentAt();
            
            
            if ($lastAlertSentAt) {
                $timeSinceLastAlert = $now->getTimestamp() - $lastAlertSentAt->getTimestamp();
                if ($timeSinceLastAlert < ($alertCooldown * 60)) {
                    $this->logger->info('Critical alert not sent, cooldown period not elapsed', [
                        'project_id' => $project->getId(),
                        'error_group_id' => $errorGroup->getId(),
                        'time_since_last_alert' => $timeSinceLastAlert,
                        'cooldown_seconds' => $alertCooldown * 60,
                        'remaining_seconds' => ($alertCooldown * 60) - $timeSinceLastAlert
                    ]);
                    return false;
                }
            }

            $subject = sprintf(
                '[CRITIQUE] %s - Erreur serveur détectée !',
                $project->getName()
            );

            $html = $this->twig->render('emails/critical_alert.html.twig', [
                'errorGroup' => $errorGroup,
                'project' => $project,
                'user' => $user,
                'dashboardUrl' => $this->generateDashboardUrl($errorGroup)
            ]);

            $email = (new Email())
                ->from('critical@errorexplorer.com')
                ->to($user->getEmail())
                ->subject($subject)
                ->html($html)
                ->priority(Email::PRIORITY_HIGH);

            $this->mailer->send($email);

            // Envoyer les notifications Slack/Discord pour les alertes critiques
            $slackSent = $this->chatNotificationService->sendSlackNotification($errorGroup, $project, $user);
            $discordSent = $this->chatNotificationService->sendDiscordNotification($errorGroup, $project, $user);

            // Mettre à jour le timestamp de dernière alerte
            $errorGroup->setLastAlertSentAt(new \DateTime());

            $this->logger->critical('Critical alert sent', [
                'error_group_id' => $errorGroup->getId(),
                'project_id' => $project->getId(),
                'user_email' => $user->getEmail(),
                'slack_sent' => $slackSent,
                'discord_sent' => $discordSent
            ]);

            return true;

        } catch (\Exception $e) {
            $this->logger->error('Failed to send critical alert', [
                'error' => $e->getMessage(),
                'error_group_id' => $errorGroup->getId()
            ]);

            return false;
        }
    }

    /**
     * Envoie une alerte de performance
     */
    public function sendPerformanceAlert(Project $project, PerformanceMetric $metric): bool
    {
        try {
            // Vérifier si les alertes sont activées globalement
            if (!$this->settingsManager->getSetting('email.performance_alerts', false)) {
                $this->logger->info('Performance alert not sent, global performance alerts are disabled', [
                    'project_id' => $project->getId(),
                    'metric_id' => $metric->getId()
                ]);
                return false;
            }

            // Vérifier si les alertes sont activées pour ce projet
            if (!$project->isAlertsEnabled()) {
                return false;
            }

            $user = $project->getOwner();
            if (!$user || !$user->isEmailAlertsEnabled()) {
                return false;
            }

            $email = $this->createPerformanceAlertEmail($metric, $project, $user);
            $this->mailer->send($email);

            // Envoyer notifications chat si configurées
            $this->chatNotificationService->sendPerformanceSlackNotification($metric, $project, $user);
            $this->chatNotificationService->sendPerformanceDiscordNotification($metric, $project, $user);

            $this->logger->warning('Performance alert sent', [
                'metric_id' => $metric->getId(),
                'project_id' => $project->getId(),
                'metric_type' => $metric->getMetricType(),
                'value' => $metric->getValue(),
                'severity' => $metric->getSeverityLevel()
            ]);

            return true;

        } catch (\Exception $e) {
            $this->logger->error('Failed to send performance alert', [
                'error' => $e->getMessage(),
                'metric_id' => $metric->getId(),
                'project_id' => $project->getId()
            ]);

            return false;
        }
    }

    /**
     * Envoie une alerte d'uptime
     */
    public function sendUptimeAlert(Project $project, UptimeCheck $check): bool
    {
        try {
            // Vérifier si les alertes sont activées globalement
            if (!$this->settingsManager->getSetting('email.uptime_alerts', false)) {
                $this->logger->info('Uptime alert not sent, global uptime alerts are disabled', [
                    'project_id' => $project->getId(),
                    'check_id' => $check->getId()
                ]);
                return false;
            }

            // Vérifier si les alertes sont activées pour ce projet
            if (!$project->isAlertsEnabled()) {
                return false;
            }

            $user = $project->getOwner();
            if (!$user || !$user->isEmailAlertsEnabled()) {
                return false;
            }

            $email = $this->createUptimeAlertEmail($check, $project, $user);
            $this->mailer->send($email);

            // Envoyer notifications chat si configurées
            $this->chatNotificationService->sendUptimeSlackNotification($check, $project, $user);
            $this->chatNotificationService->sendUptimeDiscordNotification($check, $project, $user);

            $this->logger->warning('Uptime alert sent', [
                'check_id' => $check->getId(),
                'project_id' => $project->getId(),
                'url' => $check->getUrl(),
                'status' => $check->getStatus(),
                'severity' => $check->getSeverityLevel()
            ]);

            return true;

        } catch (\Exception $e) {
            $this->logger->error('Failed to send uptime alert', [
                'error' => $e->getMessage(),
                'check_id' => $check->getId(),
                'project_id' => $project->getId()
            ]);

            return false;
        }
    }

    /**
     * Crée un email d'alerte de performance
     */
    private function createPerformanceAlertEmail(PerformanceMetric $metric, Project $project, User $user): Email
    {
        $severityEmoji = match($metric->getSeverityLevel()) {
            'critical' => '🚨',
            'high' => '⚠️',
            'medium' => '🟡',
            default => '📊'
        };

        $subject = sprintf(
            '[%s] %s Alerte Performance - %s',
            $project->getName(),
            $severityEmoji,
            $metric->getDescription()
        );

        $html = $this->twig->render('emails/performance_alert.html.twig', [
            'metric' => $metric,
            'project' => $project,
            'user' => $user,
            'dashboardUrl' => $this->generateProjectDashboardUrl($project),
            'unsubscribeUrl' => $this->generateUnsubscribeUrl($user, $project)
        ]);

        $priority = match($metric->getSeverityLevel()) {
            'critical' => Email::PRIORITY_HIGH,
            'high' => Email::PRIORITY_HIGH,
            default => Email::PRIORITY_NORMAL
        };

        return (new Email())
            ->from('performance@errorexplorer.com')
            ->to($user->getEmail())
            ->subject($subject)
            ->html($html)
            ->priority($priority);
    }

    /**
     * Crée un email d'alerte d'uptime
     */
    private function createUptimeAlertEmail(UptimeCheck $check, Project $project, User $user): Email
    {
        $statusEmoji = match($check->getStatus()) {
            'down' => '🔴',
            'timeout' => '⏱️',
            'error' => '❌',
            default => '⚠️'
        };

        $subject = sprintf(
            '[%s] %s Service %s - %s',
            $project->getName(),
            $statusEmoji,
            $check->isUp() ? 'Rétabli' : 'Indisponible',
            $check->getUrl()
        );

        $html = $this->twig->render('emails/uptime_alert.html.twig', [
            'check' => $check,
            'project' => $project,
            'user' => $user,
            'dashboardUrl' => $this->generateProjectDashboardUrl($project),
            'unsubscribeUrl' => $this->generateUnsubscribeUrl($user, $project)
        ]);

        $priority = $check->isCritical() ? Email::PRIORITY_HIGH : Email::PRIORITY_NORMAL;

        return (new Email())
            ->from('uptime@errorexplorer.com')
            ->to($user->getEmail())
            ->subject($subject)
            ->html($html)
            ->priority($priority);
    }
}
