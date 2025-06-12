<?php

namespace App\Service;

use App\Entity\ErrorGroup;
use App\Entity\Project;
use App\Entity\User;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class ChatNotificationService
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly SettingsManager $settingsManager
    ) {}

    public function sendSlackNotification(ErrorGroup $errorGroup, Project $project, User $user): bool
    {
        try {
            // Vérifier si Slack est activé globalement
            if (!$this->settingsManager->getSetting('integrations.slack_enabled', false)) {
                $this->logger->info('Slack notification not sent, globally disabled', [
                    'project_id' => $project->getId(),
                    'error_group_id' => $errorGroup->getId()
                ]);
                return false;
            }

            // Vérifier si Slack est activé pour ce projet
            if (!$project->isSlackAlertsEnabled()) {
                $this->logger->info('Slack notification not sent, disabled for project', [
                    'project_id' => $project->getId(),
                    'error_group_id' => $errorGroup->getId()
                ]);
                return false;
            }

            $webhookUrl = $project->getSlackWebhookUrl() ?: $this->settingsManager->getSetting('integrations.slack_webhook_url');
            if (!$webhookUrl) {
                $this->logger->warning('Slack notification not sent, no webhook URL configured', [
                    'project_id' => $project->getId(),
                    'error_group_id' => $errorGroup->getId()
                ]);
                return false;
            }

            $channel = $project->getSlackChannel() ?: $this->settingsManager->getSetting('integrations.slack_default_channel', '#errors');
            $username = $project->getSlackUsername() ?: 'Error Explorer';

            $payload = $this->buildSlackPayload($errorGroup, $project, $channel, $username);

            $response = $this->httpClient->request('POST', $webhookUrl, [
                'json' => $payload,
                'timeout' => 10
            ]);

            if ($response->getStatusCode() === 200) {
                $this->logger->info('Slack notification sent successfully', [
                    'project_id' => $project->getId(),
                    'error_group_id' => $errorGroup->getId(),
                    'channel' => $channel
                ]);
                return true;
            }

            $this->logger->error('Slack notification failed', [
                'project_id' => $project->getId(),
                'error_group_id' => $errorGroup->getId(),
                'status_code' => $response->getStatusCode(),
                'response' => $response->getContent(false)
            ]);

            return false;

        } catch (\Exception $e) {
            $this->logger->error('Slack notification error', [
                'project_id' => $project->getId(),
                'error_group_id' => $errorGroup->getId(),
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public function sendDiscordNotification(ErrorGroup $errorGroup, Project $project, User $user): bool
    {
        try {
            // Vérifier si Discord est activé globalement
            if (!$this->settingsManager->getSetting('integrations.discord_enabled', false)) {
                $this->logger->info('Discord notification not sent, globally disabled', [
                    'project_id' => $project->getId(),
                    'error_group_id' => $errorGroup->getId()
                ]);
                return false;
            }

            // Vérifier si Discord est activé pour ce projet
            if (!$project->isDiscordAlertsEnabled()) {
                $this->logger->info('Discord notification not sent, disabled for project', [
                    'project_id' => $project->getId(),
                    'error_group_id' => $errorGroup->getId()
                ]);
                return false;
            }

            $webhookUrl = $project->getDiscordWebhookUrl() ?: $this->settingsManager->getSetting('integrations.discord_webhook_url');
            if (!$webhookUrl) {
                $this->logger->warning('Discord notification not sent, no webhook URL configured', [
                    'project_id' => $project->getId(),
                    'error_group_id' => $errorGroup->getId()
                ]);
                return false;
            }

            $username = $project->getDiscordUsername() ?: 'Error Explorer';

            $payload = $this->buildDiscordPayload($errorGroup, $project, $username);

            $response = $this->httpClient->request('POST', $webhookUrl, [
                'json' => $payload,
                'timeout' => 10
            ]);

            if ($response->getStatusCode() === 204) {
                $this->logger->info('Discord notification sent successfully', [
                    'project_id' => $project->getId(),
                    'error_group_id' => $errorGroup->getId()
                ]);
                return true;
            }

            $this->logger->error('Discord notification failed', [
                'project_id' => $project->getId(),
                'error_group_id' => $errorGroup->getId(),
                'status_code' => $response->getStatusCode(),
                'response' => $response->getContent(false)
            ]);

            return false;

        } catch (\Exception $e) {
            $this->logger->error('Discord notification error', [
                'project_id' => $project->getId(),
                'error_group_id' => $errorGroup->getId(),
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    private function buildSlackPayload(ErrorGroup $errorGroup, Project $project, string $channel, string $username): array
    {
        $priority = $this->getErrorPriority($errorGroup);
        $color = $this->getErrorColor($errorGroup);
        
        return [
            'channel' => $channel,
            'username' => $username,
            'icon_emoji' => ':warning:',
            'attachments' => [
                [
                    'color' => $color,
                    'title' => sprintf('[%s] %s', $project->getName(), $errorGroup->getTitle()),
                    'title_link' => $this->generateDashboardUrl($errorGroup),
                    'fields' => [
                        [
                            'title' => 'Priorité',
                            'value' => $priority,
                            'short' => true
                        ],
                        [
                            'title' => 'Environnement',
                            'value' => $errorGroup->getEnvironment() ?: 'Non spécifié',
                            'short' => true
                        ],
                        [
                            'title' => 'Occurrences',
                            'value' => $errorGroup->getOccurrenceCount(),
                            'short' => true
                        ],
                        [
                            'title' => 'Code HTTP',
                            'value' => $errorGroup->getHttpStatusCode() ?: 'N/A',
                            'short' => true
                        ],
                        [
                            'title' => 'Fichier',
                            'value' => $this->formatFilePath($errorGroup->getFile(), $errorGroup->getLine()),
                            'short' => false
                        ],
                        [
                            'title' => 'Message',
                            'value' => substr($errorGroup->getMessage(), 0, 300) . (strlen($errorGroup->getMessage()) > 300 ? '...' : ''),
                            'short' => false
                        ]
                    ],
                    'footer' => 'Error Explorer',
                    'ts' => $errorGroup->getLastSeen()?->getTimestamp() ?: time()
                ]
            ]
        ];
    }

    private function buildDiscordPayload(ErrorGroup $errorGroup, Project $project, string $username): array
    {
        $priority = $this->getErrorPriority($errorGroup);
        $color = $this->getErrorColorHex($errorGroup);

        return [
            'username' => $username,
            'avatar_url' => 'https://cdn-icons-png.flaticon.com/512/564/564619.png',
            'embeds' => [
                [
                    'title' => sprintf('[%s] %s', $project->getName(), $errorGroup->getTitle()),
                    'url' => $this->generateDashboardUrl($errorGroup),
                    'color' => $color,
                    'fields' => [
                        [
                            'name' => 'Priorité',
                            'value' => $priority,
                            'inline' => true
                        ],
                        [
                            'name' => 'Environnement',
                            'value' => $errorGroup->getEnvironment() ?: 'Non spécifié',
                            'inline' => true
                        ],
                        [
                            'name' => 'Occurrences',
                            'value' => (string)$errorGroup->getOccurrenceCount(),
                            'inline' => true
                        ],
                        [
                            'name' => 'Code HTTP',
                            'value' => (string)($errorGroup->getHttpStatusCode() ?: 'N/A'),
                            'inline' => true
                        ],
                        [
                            'name' => 'Fichier',
                            'value' => '`' . $this->formatFilePath($errorGroup->getFile(), $errorGroup->getLine()) . '`',
                            'inline' => false
                        ],
                        [
                            'name' => 'Message',
                            'value' => '```' . substr($errorGroup->getMessage(), 0, 1000) . (strlen($errorGroup->getMessage()) > 1000 ? '...' : '') . '```',
                            'inline' => false
                        ]
                    ],
                    'footer' => [
                        'text' => 'Error Explorer',
                        'icon_url' => 'https://cdn-icons-png.flaticon.com/512/564/564619.png'
                    ],
                    'timestamp' => $errorGroup->getLastSeen()?->format('c') ?: (new \DateTime())->format('c')
                ]
            ]
        ];
    }

    private function getErrorPriority(ErrorGroup $errorGroup): string
    {
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

    private function getErrorColor(ErrorGroup $errorGroup): string
    {
        if ($errorGroup->getHttpStatusCode() >= 500) {
            return 'danger';
        }

        if ($errorGroup->getOccurrenceCount() > 10) {
            return 'warning';
        }

        if (in_array($errorGroup->getErrorType(), ['exception', 'error'])) {
            return '#ff9500';
        }

        return '#ffcc00';
    }

    private function getErrorColorHex(ErrorGroup $errorGroup): int
    {
        if ($errorGroup->getHttpStatusCode() >= 500) {
            return hexdec('FF0000'); // Rouge
        }

        if ($errorGroup->getOccurrenceCount() > 10) {
            return hexdec('FF8C00'); // Orange foncé
        }

        if (in_array($errorGroup->getErrorType(), ['exception', 'error'])) {
            return hexdec('FF9500'); // Orange
        }

        return hexdec('FFCC00'); // Jaune
    }

    private function formatFilePath(?string $file, ?int $line): string
    {
        if (!$file) {
            return 'Fichier non spécifié';
        }

        $shortFile = basename($file);
        if ($line) {
            return sprintf('%s:%d', $shortFile, $line);
        }

        return $shortFile;
    }
    
    private function generateDashboardUrl(ErrorGroup $errorGroup): string
    {
        $baseUrl = $this->settingsManager->getSetting('general.site_url', 'https://error-explorer.com');
        return sprintf('%s/dashboard/error/%d', rtrim($baseUrl, '/'), $errorGroup->getId());
    }
}
