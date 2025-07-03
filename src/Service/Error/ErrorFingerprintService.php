<?php

namespace App\Service\Error;

use App\ValueObject\Error\WebhookData;
use App\ValueObject\Error\CoreErrorData;
use Psr\Log\LoggerInterface;

/**
 * Service avancé de génération de fingerprints pour le groupement intelligent des erreurs
 */
class ErrorFingerprintService
{
    private const FINGERPRINT_VERSION = 'v2';

    public function __construct(
        private readonly LoggerInterface $logger
    ) {}

    /**
     * Génère un fingerprint intelligent basé sur les données webhook
     */
    public function generateFingerprint(WebhookData $webhookData): string
    {
        $core = $webhookData->coreData;
        
        // Utiliser un fingerprint fourni s'il est valide
        if ($core->fingerprint && $this->isValidFingerprint($core->fingerprint)) {
            $this->logger->debug('ErrorFingerprintService: Utilisation fingerprint fourni', [
                'fingerprint' => $core->fingerprint,
                'source' => 'webhook'
            ]);
            return $core->fingerprint;
        }

        // Choisir la stratégie de fingerprinting selon le type d'erreur
        $strategy = $this->selectStrategy($core, $webhookData);
        $fingerprint = $this->generateWithStrategy($strategy, $core, $webhookData);

        $this->logger->debug('ErrorFingerprintService: Fingerprint généré', [
            'fingerprint' => $fingerprint,
            'strategy' => $strategy,
            'exception_class' => $core->exceptionClass,
            'file' => basename($core->file),
            'line' => $core->line
        ]);

        return $fingerprint;
    }

    /**
     * Sélectionne la stratégie de fingerprinting optimale
     */
    private function selectStrategy(CoreErrorData $core, WebhookData $webhookData): string
    {
        // Stratégie pour erreurs HTTP
        if ($core->isHttpError()) {
            return 'http';
        }

        // Stratégie pour erreurs de base de données
        if ($this->isDatabaseError($core)) {
            return 'database';
        }

        // Stratégie pour erreurs réseau/timeout
        if ($this->isNetworkError($core)) {
            return 'network';
        }

        // Stratégie pour erreurs PHP fatales
        if ($this->isFatalError($core)) {
            return 'fatal';
        }

        // Stratégie pour erreurs avec stack trace complexe
        if ($this->hasComplexStackTrace($core)) {
            return 'stacktrace';
        }

        // Stratégie par défaut
        return 'standard';
    }

    /**
     * Génère le fingerprint selon la stratégie choisie
     */
    private function generateWithStrategy(string $strategy, CoreErrorData $core, WebhookData $webhookData): string
    {
        $components = match ($strategy) {
            'http' => $this->generateHttpComponents($core, $webhookData),
            'database' => $this->generateDatabaseComponents($core),
            'network' => $this->generateNetworkComponents($core),
            'fatal' => $this->generateFatalComponents($core),
            'stacktrace' => $this->generateStackTraceComponents($core),
            default => $this->generateStandardComponents($core)
        };

        // Ajouter la version et la stratégie pour éviter les collisions
        $components['_version'] = self::FINGERPRINT_VERSION;
        $components['_strategy'] = $strategy;

        $payload = implode('|', $components);
        return hash('sha256', $payload);
    }

    /**
     * Composants pour erreurs HTTP
     */
    private function generateHttpComponents(CoreErrorData $core, WebhookData $webhookData): array
    {
        $components = [
            'type' => 'http',
            'status' => $core->httpStatus ?? 'unknown',
            'exception' => $this->normalizeExceptionClass($core->exceptionClass),
            'file' => $this->normalizeFilePath($core->file),
            'line' => $core->line
        ];

        // Ajouter le path de l'URL si disponible
        if ($webhookData->requestContext && $webhookData->requestContext->url) {
            $components['url_pattern'] = $this->normalizeUrlPattern($webhookData->requestContext->url);
        }

        // Inclure la méthode HTTP si pertinente
        if ($webhookData->requestContext && $webhookData->requestContext->method) {
            $components['http_method'] = strtoupper($webhookData->requestContext->method);
        }

        return $components;
    }

    /**
     * Composants pour erreurs de base de données
     */
    private function generateDatabaseComponents(CoreErrorData $core): array
    {
        return [
            'type' => 'database',
            'exception' => $this->normalizeExceptionClass($core->exceptionClass),
            'message_pattern' => $this->normalizeDatabaseMessage($core->message),
            'file' => $this->normalizeFilePath($core->file),
            'line_range' => $this->normalizeLineRange($core->line) // Grouper par range pour DB
        ];
    }

    /**
     * Composants pour erreurs réseau
     */
    private function generateNetworkComponents(CoreErrorData $core): array
    {
        return [
            'type' => 'network',
            'exception' => $this->normalizeExceptionClass($core->exceptionClass),
            'message_pattern' => $this->normalizeNetworkMessage($core->message),
            'timeout_pattern' => $this->extractTimeoutPattern($core->message)
        ];
    }

    /**
     * Composants pour erreurs fatales PHP
     */
    private function generateFatalComponents(CoreErrorData $core): array
    {
        return [
            'type' => 'fatal',
            'exception' => $this->normalizeExceptionClass($core->exceptionClass),
            'file' => $this->normalizeFilePath($core->file),
            'line' => $core->line,
            'memory_pattern' => $this->extractMemoryPattern($core->message)
        ];
    }

    /**
     * Composants basés sur la stack trace
     */
    private function generateStackTraceComponents(CoreErrorData $core): array
    {
        $stackSignature = $this->generateStackTraceSignature($core->stackTrace);
        
        return [
            'type' => 'stacktrace',
            'exception' => $this->normalizeExceptionClass($core->exceptionClass),
            'stack_signature' => $stackSignature,
            'entry_point' => $this->extractEntryPoint($core->stackTrace)
        ];
    }

    /**
     * Composants standard (fallback)
     */
    private function generateStandardComponents(CoreErrorData $core): array
    {
        return [
            'type' => 'standard',
            'exception' => $this->normalizeExceptionClass($core->exceptionClass),
            'file' => $this->normalizeFilePath($core->file),
            'line' => $core->line,
            'message' => $this->normalizeMessage($core->message)
        ];
    }

    /**
     * Normalise une classe d'exception
     */
    private function normalizeExceptionClass(string $exceptionClass): string
    {
        // Retirer les namespaces pour grouper les exceptions similaires
        $class = basename(str_replace('\\', '/', $exceptionClass));
        
        // Normaliser les variations courantes
        $normalizations = [
            '/Exception$/' => 'Exception',
            '/Error$/' => 'Error',
            '/Warning$/' => 'Warning',
            '/Notice$/' => 'Notice'
        ];

        foreach ($normalizations as $pattern => $replacement) {
            $class = preg_replace($pattern, $replacement, $class);
        }

        return $class;
    }

    /**
     * Normalise un chemin de fichier
     */
    private function normalizeFilePath(string $file): string
    {
        // Normaliser les chemins pour enlever les parties variables
        $patterns = [
            '/^.*\/(src|app|vendor|public|resources)\//' => '$1/',
            '/^.*\/packages\/([^\/]+)\//' => 'packages/$1/',
            '/\.php\(\d+\)\s*:\s*eval\(\)\'d code/' => '.php',
            '/\([0-9]+\)\s*:\s*eval/' => ''
        ];

        foreach ($patterns as $pattern => $replacement) {
            $file = preg_replace($pattern, $replacement, $file);
        }

        return $file;
    }

    /**
     * Normalise un message d'erreur
     */
    private function normalizeMessage(string $message): string
    {
        // Remplacer les valeurs dynamiques par des placeholders
        $patterns = [
            '/\b\d+\b/' => 'N',                          // Nombres
            '/["\']([^"\']*)["\']/' => '""',             // Chaînes entre guillemets
            '/\b[a-f0-9]{8,}\b/i' => 'HASH',            // Hash/IDs
            '/\b\d{4}-\d{2}-\d{2}/' => 'DATE',          // Dates
            '/\b\d{2}:\d{2}:\d{2}/' => 'TIME',          // Heures
            '/\btmp_[a-z0-9]+\b/i' => 'TMPFILE',        // Fichiers temporaires
            '/\bsession_[a-z0-9]+\b/i' => 'SESSION',    // IDs de session
        ];

        foreach ($patterns as $pattern => $replacement) {
            $message = preg_replace($pattern, $replacement, $message);
        }

        // Limiter la longueur pour éviter les fingerprints trop longs
        return substr($message, 0, 200);
    }

    /**
     * Génère une signature de stack trace
     */
    private function generateStackTraceSignature(string $stackTrace): string
    {
        if (empty($stackTrace)) {
            return 'empty';
        }

        $lines = explode("\n", $stackTrace);
        $signature = [];

        foreach (array_slice($lines, 0, 5) as $line) { // Top 5 frames
            if (preg_match('/^#\d+\s+(.+?)(\(\d+\))?:\s*(.+)$/', trim($line), $matches)) {
                $file = $this->normalizeFilePath($matches[1]);
                $function = $matches[3] ?? 'unknown';
                
                // Normaliser les noms de fonction
                $function = preg_replace('/\([^)]*\)/', '()', $function);
                
                $signature[] = basename($file) . '::' . $function;
            }
        }

        return implode('->', array_slice($signature, 0, 3));
    }

    /**
     * Extrait le point d'entrée de la stack trace
     */
    private function extractEntryPoint(string $stackTrace): string
    {
        $lines = explode("\n", $stackTrace);
        $lastLine = trim(end($lines));
        
        if (str_contains($lastLine, '{main}')) {
            return 'main';
        }
        
        if (preg_match('/(\w+\.php)/', $lastLine, $matches)) {
            return basename($matches[1], '.php');
        }
        
        return 'unknown';
    }

    /**
     * Normalise un pattern d'URL
     */
    private function normalizeUrlPattern(string $url): string
    {
        $parsed = parse_url($url);
        $path = $parsed['path'] ?? '/';
        
        // Remplacer les IDs par des placeholders
        $path = preg_replace('/\/\d+(?=\/|$)/', '/{id}', $path);
        $path = preg_replace('/\/[a-f0-9]{8,}(?=\/|$)/i', '/{hash}', $path);
        
        return $path;
    }

    /**
     * Normalise un range de ligne pour les erreurs DB
     */
    private function normalizeLineRange(int $line, int $range = 5): int
    {
        return intval($line / $range) * $range;
    }

    /**
     * Détecteurs de types d'erreurs
     */
    private function isDatabaseError(CoreErrorData $core): bool
    {
        $indicators = ['database', 'sql', 'mysql', 'postgresql', 'connection', 'query', 'pdo'];
        $text = strtolower($core->exceptionClass . ' ' . $core->message);
        
        foreach ($indicators as $indicator) {
            if (str_contains($text, $indicator)) {
                return true;
            }
        }
        
        return false;
    }

    private function isNetworkError(CoreErrorData $core): bool
    {
        $indicators = ['curl', 'timeout', 'connection', 'network', 'socket', 'guzzle', 'http'];
        $text = strtolower($core->exceptionClass . ' ' . $core->message);
        
        foreach ($indicators as $indicator) {
            if (str_contains($text, $indicator)) {
                return true;
            }
        }
        
        return false;
    }

    private function isFatalError(CoreErrorData $core): bool
    {
        $indicators = ['fatal', 'memory', 'maximum execution time', 'allowed memory size'];
        $text = strtolower($core->message);
        
        foreach ($indicators as $indicator) {
            if (str_contains($text, $indicator)) {
                return true;
            }
        }
        
        return $core->errorType === 'error';
    }

    private function hasComplexStackTrace(CoreErrorData $core): bool
    {
        if (empty($core->stackTrace)) {
            return false;
        }
        
        $lineCount = substr_count($core->stackTrace, "\n");
        return $lineCount > 10; // Stack trace complexe si > 10 lignes
    }

    /**
     * Normalisateurs spécialisés
     */
    private function normalizeDatabaseMessage(string $message): string
    {
        $patterns = [
            '/Table \'[^\']+\'/' => 'Table {table}',
            '/Column \'[^\']+\'/' => 'Column {column}',
            '/Key \'[^\']+\'/' => 'Key {key}',
            '/\([0-9]+\)/' => '(N)',
            '/SQLSTATE\[[^\]]+\]/' => 'SQLSTATE[{code}]'
        ];

        foreach ($patterns as $pattern => $replacement) {
            $message = preg_replace($pattern, $replacement, $message);
        }

        return substr($message, 0, 150);
    }

    private function normalizeNetworkMessage(string $message): string
    {
        $patterns = [
            '/https?:\/\/[^\s]+/' => 'http://{url}',
            '/timeout of \d+/' => 'timeout of N',
            '/after \d+ seconds/' => 'after N seconds',
            '/port \d+/' => 'port N'
        ];

        foreach ($patterns as $pattern => $replacement) {
            $message = preg_replace($pattern, $replacement, $message);
        }

        return substr($message, 0, 150);
    }

    private function extractTimeoutPattern(string $message): string
    {
        if (preg_match('/timeout.*?(\d+)/', strtolower($message), $matches)) {
            $timeout = (int) $matches[1];
            
            // Grouper par ranges de timeout
            if ($timeout <= 5) return 'short';
            if ($timeout <= 30) return 'medium';
            if ($timeout <= 120) return 'long';
            return 'very_long';
        }
        
        return 'unknown';
    }

    private function extractMemoryPattern(string $message): string
    {
        if (preg_match('/(\d+)\s*(bytes?|kb|mb|gb)/i', $message, $matches)) {
            $size = strtolower($matches[2]);
            
            return match($size) {
                'bytes', 'byte' => 'bytes',
                'kb' => 'kb',
                'mb' => 'mb',
                'gb' => 'gb',
                default => 'unknown'
            };
        }
        
        return 'unknown';
    }

    /**
     * Valide qu'un fingerprint est au bon format
     */
    private function isValidFingerprint(string $fingerprint): bool
    {
        return strlen($fingerprint) === 64 && ctype_xdigit($fingerprint);
    }

    /**
     * Génère des statistiques sur les fingerprints générés
     */
    public function getStrategyStats(): array
    {
        // Cette méthode pourrait être étendue pour tracker les stats en Redis/cache
        return [
            'strategies_used' => ['http', 'database', 'network', 'fatal', 'stacktrace', 'standard'],
            'version' => self::FINGERPRINT_VERSION
        ];
    }
}