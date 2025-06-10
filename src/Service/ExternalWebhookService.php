<?php

namespace App\Service;

use App\Entity\ErrorGroup;
use App\Entity\Project;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class ExternalWebhookService
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Envoie les données d'erreur aux webhooks externes configurés
     */
    public function sendToExternalWebhooks(ErrorGroup $errorGroup, Project $project, string $event = 'error.created'): void
    {
        if (!$project->isExternalWebhooksEnabled()) {
            return;
        }

        $webhooks = $project->getExternalWebhooksForEvent($event);
        if (empty($webhooks)) {
            return;
        }

        $payload = $this->buildPayload($errorGroup, $project, $event);

        foreach ($webhooks as $webhook) {
            $this->sendWebhook($webhook, $payload, $project);
        }
    }

    /**
     * Construit le payload à envoyer au webhook externe
     */
    private function buildPayload(ErrorGroup $errorGroup, Project $project, string $event): array
    {
        $latestOccurrence = $errorGroup->getLatestOccurrence();
        
        return [
            'event' => $event,
            'timestamp' => (new \DateTime())->format(\DateTime::ISO8601),
            'project' => [
                'id' => $project->getId(),
                'name' => $project->getName(),
                'slug' => $project->getSlug(),
                'environment' => $project->getEnvironment(),
                'repository_url' => $project->getRepositoryUrl()
            ],
            'error_group' => [
                'id' => $errorGroup->getId(),
                'fingerprint' => $errorGroup->getFingerprint(),
                'message' => $errorGroup->getMessage(),
                'exception_class' => $errorGroup->getExceptionClass(),
                'file' => $errorGroup->getFile(),
                'line' => $errorGroup->getLine(),
                'status' => $errorGroup->getStatus(),
                'error_type' => $errorGroup->getErrorType(),
                'http_status_code' => $errorGroup->getHttpStatusCode(),
                'occurrence_count' => $errorGroup->getOccurrenceCount(),
                'first_seen' => $errorGroup->getFirstSeen()?->format(\DateTime::ISO8601),
                'last_seen' => $errorGroup->getLastSeen()?->format(\DateTime::ISO8601),
                'url' => $this->buildErrorGroupUrl($errorGroup, $project)
            ],
            'latest_occurrence' => $latestOccurrence ? [
                'id' => $latestOccurrence->getId(),
                'user_agent' => $latestOccurrence->getUserAgent(),
                'ip_address' => $latestOccurrence->getIpAddress(),
                'url' => $latestOccurrence->getUrl(),
                'http_method' => $latestOccurrence->getHttpMethod(),
                'stack_trace' => $latestOccurrence->getStackTrace(),
                'context' => $latestOccurrence->getContext(),
                'occurred_at' => $latestOccurrence->getCreatedAt()?->format(\DateTime::ISO8601)
            ] : null,
            'source_code' => $this->extractSourceCode($errorGroup),
            'is_critical' => $this->isCriticalError($errorGroup),
            'suggested_actions' => $this->generateSuggestedActions($errorGroup)
        ];
    }

    /**
     * Envoie un webhook individuel
     */
    private function sendWebhook(array $webhook, array $payload, Project $project): void
    {
        try {
            $options = [
                'json' => $payload,
                'timeout' => 10,
                'headers' => array_merge([
                    'User-Agent' => 'Error-Explorer-Webhook/1.0',
                    'X-Error-Explorer-Project' => $project->getSlug(),
                    'X-Error-Explorer-Event' => $payload['event']
                ], $webhook['headers'] ?? [])
            ];

            $response = $this->httpClient->request('POST', $webhook['url'], $options);

            if ($response->getStatusCode() >= 400) {
                $this->logger->warning('External webhook failed', [
                    'webhook_name' => $webhook['name'],
                    'webhook_url' => $webhook['url'],
                    'status_code' => $response->getStatusCode(),
                    'project' => $project->getSlug()
                ]);
            } else {
                $this->logger->info('External webhook sent successfully', [
                    'webhook_name' => $webhook['name'],
                    'project' => $project->getSlug(),
                    'event' => $payload['event']
                ]);
            }
        } catch (\Exception $e) {
            $this->logger->error('External webhook error', [
                'webhook_name' => $webhook['name'],
                'webhook_url' => $webhook['url'],
                'error' => $e->getMessage(),
                'project' => $project->getSlug()
            ]);
        }
    }

    /**
     * Test un webhook externe
     */
    public function testWebhook(array $webhook, Project $project): array
    {
        try {
            $testPayload = [
                'event' => 'webhook.test',
                'timestamp' => (new \DateTime())->format(\DateTime::ISO8601),
                'project' => [
                    'id' => $project->getId(),
                    'name' => $project->getName(),
                    'slug' => $project->getSlug()
                ],
                'message' => 'Test webhook depuis Error Explorer',
                'test' => true
            ];

            $options = [
                'json' => $testPayload,
                'timeout' => 10,
                'headers' => array_merge([
                    'User-Agent' => 'Error-Explorer-Webhook/1.0',
                    'X-Error-Explorer-Project' => $project->getSlug(),
                    'X-Error-Explorer-Event' => 'webhook.test'
                ], $webhook['headers'] ?? [])
            ];

            $response = $this->httpClient->request('POST', $webhook['url'], $options);
            
            return [
                'success' => $response->getStatusCode() < 400,
                'status_code' => $response->getStatusCode(),
                'response_time' => null, // Pourrait être ajouté si nécessaire
                'message' => $response->getStatusCode() < 400 ? 'Test réussi' : 'Test échoué'
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'status_code' => null,
                'message' => 'Erreur: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Extrait le code source autour de l'erreur
     */
    private function extractSourceCode(ErrorGroup $errorGroup): ?array
    {
        $file = $errorGroup->getFile();
        $line = $errorGroup->getLine();
        
        if (!$file || !$line) {
            return null;
        }

        // Note: Dans un vrai projet, vous pourriez avoir accès au code source
        // Ici nous retournons juste les informations de base
        return [
            'file' => $file,
            'line' => $line,
            'class' => $errorGroup->getExceptionClass(),
            'snippet' => null // Le snippet de code pourrait être extrait si accessible
        ];
    }

    /**
     * Construit l'URL vers la page de détail de l'erreur
     */
    private function buildErrorGroupUrl(ErrorGroup $errorGroup, Project $project): string
    {
        // URL de base à configurer selon votre environnement
        $baseUrl = $_ENV['APP_URL'] ?? 'https://your-error-explorer.com';
        return rtrim($baseUrl, '/') . '/dashboard/project/' . $project->getSlug() . '/error/' . $errorGroup->getId();
    }

    /**
     * Détermine si l'erreur est critique
     */
    private function isCriticalError(ErrorGroup $errorGroup): bool
    {
        // HTTP 500+ errors
        if ($errorGroup->getHttpStatusCode() >= 500) {
            return true;
        }

        // High frequency errors (5+ occurrences in 5 minutes)
        if ($errorGroup->getOccurrenceCount() > 5) {
            $timeSinceFirst = (new \DateTime())->getTimestamp() - $errorGroup->getFirstSeen()?->getTimestamp();
            if ($timeSinceFirst < 300) {
                return true;
            }
        }

        // Critical error types
        if (in_array($errorGroup->getErrorType(), ['fatal', 'error', 'exception'], true)) {
            return true;
        }

        return false;
    }

    /**
     * Génère des actions suggérées basées sur le type d'erreur
     */
    private function generateSuggestedActions(ErrorGroup $errorGroup): array
    {
        $actions = [];

        // Actions basées sur le type d'erreur
        switch ($errorGroup->getErrorType()) {
            case 'database':
                $actions[] = 'Vérifier la connexion à la base de données';
                $actions[] = 'Contrôler les requêtes SQL';
                $actions[] = 'Examiner les logs de la base de données';
                break;
                
            case 'network':
                $actions[] = 'Vérifier la connectivité réseau';
                $actions[] = 'Contrôler les timeouts';
                $actions[] = 'Examiner les services externes';
                break;
                
            case 'memory':
                $actions[] = 'Analyser l\'utilisation mémoire';
                $actions[] = 'Optimiser les requêtes gourmandes';
                $actions[] = 'Vérifier les fuites mémoire';
                break;
                
            default:
                $actions[] = 'Examiner le fichier ' . basename($errorGroup->getFile() ?? '') . ' ligne ' . $errorGroup->getLine();
                $actions[] = 'Vérifier la stack trace complète';
                $actions[] = 'Reproduire l\'erreur en local';
        }

        // Actions pour erreurs critiques
        if ($this->isCriticalError($errorGroup)) {
            array_unshift($actions, 'CRITIQUE: Traiter en priorité');
        }

        return $actions;
    }
}