<?php

namespace App\Service\Error;

use App\ValueObject\Error\RequestContext;
use App\ValueObject\Error\ServerContext;
use App\ValueObject\Error\ErrorContext;
use App\ValueObject\Error\WebhookData;
use App\ValueObject\Error\CoreErrorData;
use App\ValueObject\Error\UserContext;
use App\ValueObject\Error\Breadcrumb;
use Psr\Log\LoggerInterface;

/**
 * Service responsable de l'extraction et transformation des données webhook
 * en Value Objects typés et validés
 */
class WebhookDataExtractor
{
    public function __construct(
        private readonly LoggerInterface $logger
    ) {}

    /**
     * Extrait et transforme le payload webhook complet en Value Objects
     */
    public function extractWebhookData(array $payload): WebhookData
    {
        return new WebhookData(
            requestContext: $this->extractRequestContext($payload),
            serverContext: $this->extractServerContext($payload),
            errorContext: $this->extractErrorContext($payload),
            coreData: $this->extractCoreData($payload)
        );
    }

    /**
     * Extrait le contexte de requête depuis le payload
     */
    public function extractRequestContext(array $payload): ?RequestContext
    {
        if (!isset($payload['request']) || !is_array($payload['request'])) {
            return null;
        }

        try {
            return RequestContext::fromArray($payload['request']);
        } catch (\Exception $e) {
            $this->logger->warning('WebhookDataExtractor: Erreur extraction RequestContext', [
                'error' => $e->getMessage(),
                'request_data' => $payload['request']
            ]);
            return null;
        }
    }

    /**
     * Extrait le contexte serveur depuis le payload
     */
    public function extractServerContext(array $payload): ?ServerContext
    {
        if (!isset($payload['server']) || !is_array($payload['server'])) {
            return null;
        }

        try {
            return ServerContext::fromArray($payload['server']);
        } catch (\Exception $e) {
            $this->logger->warning('WebhookDataExtractor: Erreur extraction ServerContext', [
                'error' => $e->getMessage(),
                'server_data' => $payload['server']
            ]);
            return null;
        }
    }

    /**
     * Extrait le contexte d'erreur depuis le payload
     */
    public function extractErrorContext(array $payload): ?ErrorContext
    {
        if (!isset($payload['context']) || !is_array($payload['context'])) {
            return null;
        }

        try {
            return ErrorContext::fromArray($payload['context']);
        } catch (\Exception $e) {
            $this->logger->warning('WebhookDataExtractor: Erreur extraction ErrorContext', [
                'error' => $e->getMessage(),
                'context_data' => $payload['context']
            ]);
            return null;
        }
    }

    /**
     * Extrait les données core de l'erreur avec validation
     */
    public function extractCoreData(array $payload): CoreErrorData
    {
        return new CoreErrorData(
            message: $this->extractMessage($payload),
            exceptionClass: $this->extractExceptionClass($payload),
            file: $this->extractFile($payload),
            line: $this->extractLine($payload),
            project: $this->extractProject($payload),
            environment: $this->extractEnvironment($payload),
            httpStatus: $this->extractHttpStatus($payload),
            stackTrace: $this->extractStackTrace($payload),
            timestamp: $this->extractTimestamp($payload),
            errorType: $this->detectErrorType($payload['exception_class'] ?? ''),
            fingerprint: $this->extractFingerprint($payload)
        );
    }

    /**
     * Extrait et nettoie le message d'erreur
     */
    private function extractMessage(array $payload): string
    {
        $message = $payload['message'] ?? 'Unknown error';
        
        // Nettoyer et limiter la longueur
        $message = trim($message);
        if (strlen($message) > 2000) {
            $message = substr($message, 0, 1997) . '...';
            $this->logger->info('WebhookDataExtractor: Message tronqué', [
                'original_length' => strlen($payload['message'] ?? '')
            ]);
        }
        
        return $message;
    }

    /**
     * Extrait et valide la classe d'exception
     */
    private function extractExceptionClass(array $payload): string
    {
        $exceptionClass = $payload['exception_class'] ?? 'Exception';
        
        // Nettoyer et normaliser
        $exceptionClass = trim($exceptionClass);
        $exceptionClass = preg_replace('/[^a-zA-Z0-9\\\\_]/', '', $exceptionClass);
        
        return $exceptionClass ?: 'Exception';
    }

    /**
     * Extrait et normalise le chemin du fichier
     */
    private function extractFile(array $payload): string
    {
        $file = $payload['file'] ?? 'unknown';
        
        return $this->normalizePath($file);
    }

    /**
     * Extrait et valide le numéro de ligne
     */
    private function extractLine(array $payload): int
    {
        $line = $payload['line'] ?? 0;
        
        if (is_string($line) && is_numeric($line)) {
            $line = (int) $line;
        } elseif (!is_int($line)) {
            $line = 0;
        }
        
        return max(0, $line);
    }

    /**
     * Extrait et normalise le nom du projet
     */
    private function extractProject(array $payload): string
    {
        $project = $payload['project'] ?? 'unknown';
        
        return $this->normalizeProjectName($project);
    }

    /**
     * Extrait l'environnement avec valeur par défaut
     */
    private function extractEnvironment(array $payload): string
    {
        $environment = $payload['environment'] ?? 'unknown';
        
        // Normaliser les environnements courants
        $environment = strtolower(trim($environment));
        $validEnvironments = ['production', 'staging', 'development', 'dev', 'test', 'local'];
        
        if (!in_array($environment, $validEnvironments, true)) {
            $environment = 'unknown';
        }
        
        return $environment;
    }

    /**
     * Extrait et valide le code HTTP
     */
    private function extractHttpStatus(array $payload): ?int
    {
        if (!isset($payload['http_status'])) {
            return null;
        }
        
        $httpStatus = $payload['http_status'];
        
        if (is_string($httpStatus) && is_numeric($httpStatus)) {
            $httpStatus = (int) $httpStatus;
        } elseif (!is_int($httpStatus)) {
            return null;
        }
        
        // Valider que c'est un code HTTP valide
        if ($httpStatus < 100 || $httpStatus > 599) {
            $this->logger->warning('WebhookDataExtractor: Code HTTP invalide', [
                'http_status' => $httpStatus
            ]);
            return null;
        }
        
        return $httpStatus;
    }

    /**
     * Extrait la stack trace avec gestion intelligente de la taille
     */
    private function extractStackTrace(array $payload): string
    {
        $stackTrace = $payload['stack_trace'] ?? '';
        
        if (empty($stackTrace)) {
            return '';
        }
        
        // Limite recommandée : 200KB pour les gros projets enterprise
        $maxSize = 200 * 1024; // 200KB
        
        if (strlen($stackTrace) > $maxSize) {
            // Tronquer intelligemment en gardant le début et la fin
            $keepStart = (int) ($maxSize * 0.7); // 70% du début
            $keepEnd = $maxSize - $keepStart - 100; // Le reste pour la fin (- espace pour marqueur)
            
            $truncatedTrace = substr($stackTrace, 0, $keepStart) . 
                            "\n\n... [TRONQUÉ - " . (strlen($stackTrace) - $maxSize) . " caractères omis] ...\n\n" .
                            substr($stackTrace, -$keepEnd);
            
            $this->logger->info('WebhookDataExtractor: Stack trace tronquée', [
                'original_length' => strlen($stackTrace),
                'truncated_length' => strlen($truncatedTrace),
                'truncated_bytes' => strlen($stackTrace) - $maxSize
            ]);
            
            return $truncatedTrace;
        }
        
        return $stackTrace;
    }

    /**
     * Extrait et parse le timestamp
     */
    private function extractTimestamp(array $payload): \DateTimeInterface
    {
        if (!isset($payload['timestamp'])) {
            return new \DateTime();
        }
        
        try {
            $timestamp = new \DateTime($payload['timestamp']);
            
            // Vérifier que le timestamp n'est pas trop dans le futur (max 1 heure)
            $now = new \DateTime();
            $maxFuture = (clone $now)->add(new \DateInterval('PT1H'));
            
            if ($timestamp > $maxFuture) {
                $this->logger->warning('WebhookDataExtractor: Timestamp futur détecté', [
                    'timestamp' => $payload['timestamp'],
                    'parsed' => $timestamp->format('c')
                ]);
                return $now;
            }
            
            return $timestamp;
        } catch (\Exception $e) {
            $this->logger->warning('WebhookDataExtractor: Timestamp invalide', [
                'timestamp' => $payload['timestamp'],
                'error' => $e->getMessage()
            ]);
            return new \DateTime();
        }
    }

    /**
     * Extrait le fingerprint s'il est fourni
     */
    private function extractFingerprint(array $payload): ?string
    {
        return $payload['fingerprint'] ?? null;
    }

    /**
     * Détecte le type d'erreur basé sur la classe d'exception
     */
    private function detectErrorType(string $exceptionClass): string
    {
        $class = strtolower($exceptionClass);

        if (str_contains($class, 'error')) {
            return 'error';
        }

        if (str_contains($class, 'warning')) {
            return 'warning';
        }

        if (str_contains($class, 'notice')) {
            return 'notice';
        }

        // Par défaut, considérer comme une exception
        return 'exception';
    }

    /**
     * Normalise le chemin de fichier pour cohérence
     */
    private function normalizePath(string $path): string
    {
        // Remplacer les backslashes par des slashes
        $path = str_replace('\\', '/', $path);

        // Normaliser les chemins relatifs courants
        $path = preg_replace('/^.*\/(src|app|vendor|public)\//', '$1/', $path);

        // Limiter la longueur
        if (strlen($path) > 500) {
            $path = '...' . substr($path, -497);
        }

        return $path;
    }

    /**
     * Normalise le nom du projet
     */
    private function normalizeProjectName(string $project): string
    {
        // Nettoyer et limiter la longueur
        $project = trim($project);
        $project = preg_replace('/[^a-zA-Z0-9\-_.]/', '-', $project);

        return substr($project, 0, 100);
    }

    /**
     * Extrait les données pour les index de base de données
     */
    public function extractIndexedFields(WebhookData $webhookData): array
    {
        $fields = [];

        // Depuis RequestContext
        if ($webhookData->requestContext) {
            $fields['url'] = $webhookData->requestContext->url;
            $fields['http_method'] = $webhookData->requestContext->method;
            $fields['ip_address'] = $webhookData->requestContext->ip;
            $fields['user_agent'] = $webhookData->requestContext->userAgent;
        }

        // Depuis ServerContext
        if ($webhookData->serverContext) {
            $fields['memory_usage'] = $webhookData->serverContext->memoryUsage;
            $fields['execution_time'] = $webhookData->serverContext->executionTime;
        }

        // Depuis ErrorContext (user ID)
        if ($webhookData->errorContext && $webhookData->errorContext->hasUserContext()) {
            $fields['user_id'] = $webhookData->errorContext->userContext->id;
        }

        return $fields;
    }

    /**
     * Valide que les données webhook sont complètes
     */
    public function validateWebhookData(WebhookData $webhookData): array
    {
        $errors = [];

        // Validation des données core
        $core = $webhookData->coreData;
        
        if (empty($core->message)) {
            $errors[] = 'Message is required';
        }
        
        if (empty($core->exceptionClass)) {
            $errors[] = 'Exception class is required';
        }
        
        if (empty($core->file)) {
            $errors[] = 'File is required';
        }
        
        if ($core->line < 0) {
            $errors[] = 'Line must be positive';
        }

        return $errors;
    }
}