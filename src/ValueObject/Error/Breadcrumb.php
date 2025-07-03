<?php

namespace App\ValueObject\Error;

/**
 * Value Object représentant un breadcrumb (trace d'activité)
 * Compatible avec tous les SDKs
 */
class Breadcrumb
{
    public const LEVEL_DEBUG = 'debug';
    public const LEVEL_INFO = 'info';
    public const LEVEL_WARNING = 'warning';
    public const LEVEL_ERROR = 'error';

    public const CATEGORY_AUTH = 'auth';
    public const CATEGORY_NAVIGATION = 'navigation';
    public const CATEGORY_HTTP = 'http';
    public const CATEGORY_DATABASE = 'database';
    public const CATEGORY_CACHE = 'cache';
    public const CATEGORY_CUSTOM = 'custom';

    public function __construct(
        public string $message,
        public string $category = self::CATEGORY_CUSTOM,
        public string $level = self::LEVEL_INFO,
        public ?\DateTimeInterface $timestamp = null,
        public array $data = []
    ) {
        // Valider le timestamp par défaut
        if ($this->timestamp === null) {
            $this->timestamp = new \DateTime();
        }
    }

    /**
     * Crée une instance depuis les données JSON du webhook
     */
    public static function fromArray(array $data): self
    {
        $timestamp = null;
        if (isset($data['timestamp'])) {
            try {
                $timestamp = new \DateTime($data['timestamp']);
            } catch (\Exception) {
                $timestamp = new \DateTime();
            }
        }

        return new self(
            message: $data['message'] ?? 'Unknown breadcrumb',
            category: $data['category'] ?? self::CATEGORY_CUSTOM,
            level: $data['level'] ?? self::LEVEL_INFO,
            timestamp: $timestamp,
            data: $data['data'] ?? []
        );
    }

    /**
     * Retourne les données en format array pour compatibilité
     */
    public function toArray(): array
    {
        return [
            'message' => $this->message,
            'category' => $this->category,
            'level' => $this->level,
            'timestamp' => $this->timestamp->format('c'),
            'data' => $this->data
        ];
    }

    /**
     * Retourne les niveaux valides
     */
    public static function getValidLevels(): array
    {
        return [
            self::LEVEL_DEBUG,
            self::LEVEL_INFO,
            self::LEVEL_WARNING,
            self::LEVEL_ERROR
        ];
    }

    /**
     * Retourne les catégories valides
     */
    public static function getValidCategories(): array
    {
        return [
            self::CATEGORY_AUTH,
            self::CATEGORY_NAVIGATION,
            self::CATEGORY_HTTP,
            self::CATEGORY_DATABASE,
            self::CATEGORY_CACHE,
            self::CATEGORY_CUSTOM
        ];
    }

    /**
     * Vérifie si le niveau est valide
     */
    public function hasValidLevel(): bool
    {
        return in_array($this->level, self::getValidLevels(), true);
    }

    /**
     * Vérifie si la catégorie est valide
     */
    public function hasValidCategory(): bool
    {
        return in_array($this->category, self::getValidCategories(), true);
    }

    /**
     * Retourne la priorité numérique du niveau (pour tri)
     */
    public function getLevelPriority(): int
    {
        return match ($this->level) {
            self::LEVEL_ERROR => 4,
            self::LEVEL_WARNING => 3,
            self::LEVEL_INFO => 2,
            self::LEVEL_DEBUG => 1,
            default => 0
        };
    }

    /**
     * Vérifie si le breadcrumb est critique
     */
    public function isCritical(): bool
    {
        return $this->level === self::LEVEL_ERROR;
    }

    /**
     * Retourne le timestamp formaté pour l'affichage
     */
    public function getFormattedTimestamp(): string
    {
        return $this->timestamp->format('H:i:s');
    }

    /**
     * Retourne le timestamp relatif (ex: "il y a 2 minutes")
     */
    public function getRelativeTime(): string
    {
        $now = new \DateTime();
        $diff = $now->diff($this->timestamp);

        if ($diff->d > 0) {
            return "il y a {$diff->d} jour" . ($diff->d > 1 ? 's' : '');
        }

        if ($diff->h > 0) {
            return "il y a {$diff->h} heure" . ($diff->h > 1 ? 's' : '');
        }

        if ($diff->i > 0) {
            return "il y a {$diff->i} minute" . ($diff->i > 1 ? 's' : '');
        }

        return "il y a {$diff->s} seconde" . ($diff->s > 1 ? 's' : '');
    }

    /**
     * Retourne l'icône associée au niveau
     */
    public function getLevelIcon(): string
    {
        return match ($this->level) {
            self::LEVEL_ERROR => '❌',
            self::LEVEL_WARNING => '⚠️',
            self::LEVEL_INFO => 'ℹ️',
            self::LEVEL_DEBUG => '🐛',
            default => '📝'
        };
    }

    /**
     * Retourne l'icône associée à la catégorie
     */
    public function getCategoryIcon(): string
    {
        return match ($this->category) {
            self::CATEGORY_AUTH => '🔐',
            self::CATEGORY_NAVIGATION => '🧭',
            self::CATEGORY_HTTP => '🌐',
            self::CATEGORY_DATABASE => '🗄️',
            self::CATEGORY_CACHE => '📦',
            default => '📝'
        };
    }

    /**
     * Retourne une couleur CSS associée au niveau
     */
    public function getLevelColor(): string
    {
        return match ($this->level) {
            self::LEVEL_ERROR => '#dc2626',    // red
            self::LEVEL_WARNING => '#f59e0b',  // yellow
            self::LEVEL_INFO => '#2563eb',     // blue
            self::LEVEL_DEBUG => '#6b7280',    // gray
            default => '#374151'               // dark gray
        };
    }

    /**
     * Vérifie si le breadcrumb contient des données additionnelles
     */
    public function hasData(): bool
    {
        return !empty($this->data);
    }

    /**
     * Retourne une valeur des données par clé
     */
    public function getData(string $key): mixed
    {
        return $this->data[$key] ?? null;
    }

    /**
     * Retourne un résumé du breadcrumb pour l'affichage
     */
    public function getSummary(): string
    {
        $parts = [
            $this->getFormattedTimestamp(),
            strtoupper($this->level),
            "({$this->category})",
            $this->message
        ];

        if ($this->hasData()) {
            $parts[] = "[" . count($this->data) . " data field(s)]";
        }

        return implode(' ', $parts);
    }

    /**
     * Filtre les données sensibles
     */
    public function getSafeData(): array
    {
        if (!$this->hasData()) {
            return [];
        }

        $sensitiveKeys = [
            'password', 'passwd', 'secret', 'token', 'api_key', 'access_token',
            'refresh_token', 'auth', 'authorization', 'session', 'cookie'
        ];

        $safeData = [];
        foreach ($this->data as $key => $value) {
            if (in_array(strtolower($key), $sensitiveKeys, true)) {
                $safeData[$key] = '[FILTERED]';
            } else {
                $safeData[$key] = $value;
            }
        }

        return $safeData;
    }

    /**
     * Convertit vers un format sécurisé pour l'affichage public
     */
    public function toSafeArray(): array
    {
        return [
            'message' => $this->message,
            'category' => $this->category,
            'level' => $this->level,
            'timestamp' => $this->timestamp->format('c'),
            'relative_time' => $this->getRelativeTime(),
            'data' => $this->getSafeData()
        ];
    }
}
