<?php

namespace App\ValueObject\Error;

/**
 * Value Object représentant les données core d'une erreur
 */
class CoreErrorData
{
    public function __construct(
        public string $message,
        public string $exceptionClass,
        public string $file,
        public int $line,
        public string $project,
        public string $environment,
        public ?int $httpStatus,
        public string $stackTrace,
        public \DateTimeInterface $timestamp,
        public string $errorType,
        public ?string $fingerprint = null
    ) {}

    /**
     * Vérifie si les données core sont valides
     */
    public function isValid(): bool
    {
        return !empty($this->message) &&
               !empty($this->exceptionClass) &&
               !empty($this->file) &&
               $this->line >= 0;
    }

    /**
     * Retourne le titre de l'erreur pour l'affichage
     */
    public function getTitle(): string
    {
        $title = $this->exceptionClass;

        if ($this->httpStatus) {
            $title .= " (HTTP {$this->httpStatus})";
        }

        return $title;
    }

    /**
     * Retourne la localisation de l'erreur
     */
    public function getLocation(): string
    {
        return "{$this->file}:{$this->line}";
    }

    /**
     * Vérifie si l'erreur est critique basée sur le type et le code HTTP
     */
    public function isCritical(): bool
    {
        // Erreurs critiques par type
        if (in_array($this->errorType, ['error', 'exception'], true)) {
            return true;
        }

        // Erreurs critiques par code HTTP
        if ($this->httpStatus && $this->httpStatus >= 500) {
            return true;
        }

        return false;
    }

    /**
     * Retourne la priorité numérique de l'erreur (pour tri)
     */
    public function getPriority(): int
    {
        return match ($this->errorType) {
            'error' => 4,
            'exception' => 3,
            'warning' => 2,
            'notice' => 1,
            default => 0
        };
    }

    /**
     * Retourne un résumé court de l'erreur
     */
    public function getShortSummary(): string
    {
        $summary = $this->exceptionClass;

        if (strlen($this->message) <= 50) {
            $summary .= ": {$this->message}";
        } else {
            $summary .= ": " . substr($this->message, 0, 47) . "...";
        }

        return $summary;
    }

    /**
     * Extrait un aperçu de la stack trace
     */
    public function getStackTracePreview(int $lines = 3): string
    {
        if (empty($this->stackTrace)) {
            return '';
        }

        $stackLines = explode("\n", $this->stackTrace);
        $preview = array_slice($stackLines, 0, $lines);

        return implode("\n", $preview);
    }

    /**
     * Vérifie si l'erreur est liée à HTTP
     */
    public function isHttpError(): bool
    {
        return $this->httpStatus !== null;
    }

    /**
     * Retourne les tags associés à l'erreur pour la classification
     */
    public function getTags(): array
    {
        $tags = [$this->errorType, $this->environment];

        if ($this->isHttpError()) {
            $tags[] = 'http';

            if ($this->httpStatus >= 500) {
                $tags[] = 'server-error';
            } elseif ($this->httpStatus >= 400) {
                $tags[] = 'client-error';
            }
        }

        if ($this->isCritical()) {
            $tags[] = 'critical';
        }

        // Détecter les erreurs de base de données
        if (str_contains(strtolower($this->exceptionClass), 'database') ||
            str_contains(strtolower($this->exceptionClass), 'sql') ||
            str_contains(strtolower($this->exceptionClass), 'connection')) {
            $tags[] = 'database';
        }

        // Détecter les erreurs réseau
        if (str_contains(strtolower($this->exceptionClass), 'curl') ||
            str_contains(strtolower($this->exceptionClass), 'timeout') ||
            str_contains(strtolower($this->exceptionClass), 'connection')) {
            $tags[] = 'network';
        }

        return array_unique($tags);
    }

    /**
     * Convertit vers un format array pour persistance
     */
    public function toArray(): array
    {
        return [
            'message' => $this->message,
            'exception_class' => $this->exceptionClass,
            'file' => $this->file,
            'line' => $this->line,
            'project' => $this->project,
            'environment' => $this->environment,
            'http_status' => $this->httpStatus,
            'stack_trace' => $this->stackTrace,
            'timestamp' => $this->timestamp->format('c'),
            'error_type' => $this->errorType,
            'fingerprint' => $this->fingerprint
        ];
    }

    /**
     * Retourne les méta-données pour l'analytics
     */
    public function getAnalyticsData(): array
    {
        return [
            'error_type' => $this->errorType,
            'environment' => $this->environment,
            'http_status' => $this->httpStatus,
            'is_critical' => $this->isCritical(),
            'is_http_error' => $this->isHttpError(),
            'priority' => $this->getPriority(),
            'tags' => $this->getTags(),
            'file_extension' => pathinfo($this->file, PATHINFO_EXTENSION),
            'hour_of_day' => (int) $this->timestamp->format('H'),
            'day_of_week' => (int) $this->timestamp->format('N')
        ];
    }
}
