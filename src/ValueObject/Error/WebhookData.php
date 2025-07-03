<?php

namespace App\ValueObject\Error;

/**
 * Value Object contenant toutes les données extraites d'un webhook
 */
class WebhookData
{
    public function __construct(
        public ?RequestContext $requestContext,
        public ?ServerContext $serverContext,
        public ?ErrorContext $errorContext,
        public CoreErrorData $coreData
    ) {}

    /**
     * Vérifie si les données webhook sont valides
     */
    public function isValid(): bool
    {
        return !empty($this->coreData->message) &&
               !empty($this->coreData->exceptionClass) &&
               !empty($this->coreData->file) &&
               $this->coreData->line >= 0;
    }

    /**
     * Retourne un résumé des données pour le logging
     */
    public function getSummary(): string
    {
        $parts = [
            $this->coreData->exceptionClass,
            "in {$this->coreData->file}:{$this->coreData->line}",
            "({$this->coreData->environment})"
        ];

        if ($this->requestContext && $this->requestContext->url) {
            $parts[] = "URL: {$this->requestContext->getDisplayUrl()}";
        }

        if ($this->serverContext) {
            $runtime = $this->serverContext->getRuntimeType();
            if ($runtime) {
                $parts[] = "Runtime: {$runtime}";
            }
        }

        return implode(' ', $parts);
    }

    /**
     * Convertit vers le format array pour compatibilité backward
     */
    public function toLegacyArray(): array
    {
        $data = [
            // Core data
            'message' => $this->coreData->message,
            'exception_class' => $this->coreData->exceptionClass,
            'file' => $this->coreData->file,
            'line' => $this->coreData->line,
            'project' => $this->coreData->project,
            'environment' => $this->coreData->environment,
            'http_status' => $this->coreData->httpStatus,
            'stack_trace' => $this->coreData->stackTrace,
            'timestamp' => $this->coreData->timestamp,
            'error_type' => $this->coreData->errorType,
            'fingerprint' => $this->coreData->fingerprint,

            // Contextes as arrays
            'request' => $this->requestContext?->toArray() ?? [],
            'server' => $this->serverContext?->toArray() ?? [],
            'context' => $this->errorContext?->toArray() ?? []
        ];

        // Ajouter les champs indexés pour compatibilité
        if ($this->requestContext) {
            $data['url'] = $this->requestContext->url;
            $data['http_method'] = $this->requestContext->method;
            $data['ip_address'] = $this->requestContext->ip;
            $data['user_agent'] = $this->requestContext->userAgent;
        }

        if ($this->serverContext) {
            $data['memory_usage'] = $this->serverContext->memoryUsage;
            $data['execution_time'] = $this->serverContext->executionTime;
        }

        if ($this->errorContext && $this->errorContext->hasUserContext()) {
            $data['user_id'] = $this->errorContext->userContext->id;
        }

        return $data;
    }

    /**
     * Vérifie si les données contiennent des métriques de performance
     */
    public function hasPerformanceMetrics(): bool
    {
        return $this->serverContext && $this->serverContext->hasPerformanceMetrics();
    }

    /**
     * Vérifie si les données contiennent un contexte utilisateur
     */
    public function hasUserContext(): bool
    {
        return $this->errorContext && $this->errorContext->hasUserContext();
    }

    /**
     * Vérifie si les données contiennent des breadcrumbs
     */
    public function hasBreadcrumbs(): bool
    {
        return $this->errorContext && $this->errorContext->hasBreadcrumbs();
    }

    /**
     * Retourne les informations de debug pour le logging
     */
    public function getDebugInfo(): array
    {
        return [
            'core' => [
                'message_length' => strlen($this->coreData->message),
                'exception_class' => $this->coreData->exceptionClass,
                'environment' => $this->coreData->environment,
                'error_type' => $this->coreData->errorType,
                'has_fingerprint' => !empty($this->coreData->fingerprint)
            ],
            'contexts' => [
                'has_request' => $this->requestContext !== null,
                'has_server' => $this->serverContext !== null,
                'has_error_context' => $this->errorContext !== null,
                'has_user' => $this->hasUserContext(),
                'has_breadcrumbs' => $this->hasBreadcrumbs(),
                'has_performance' => $this->hasPerformanceMetrics()
            ],
            'details' => [
                'request_url' => $this->requestContext?->url ?? null,
                'server_runtime' => $this->serverContext?->getRuntimeType() ?? null,
                'user_id' => $this->errorContext?->userContext?->id ?? null,
                'breadcrumb_count' => $this->errorContext?->getBreadcrumbCount() ?? 0
            ]
        ];
    }
}
