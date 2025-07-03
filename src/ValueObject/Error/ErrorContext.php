<?php

namespace App\ValueObject\Error;

/**
 * Value Object représentant le contexte métier d'une erreur
 * Données arbitraires et contexte utilisateur
 */
class ErrorContext
{
    public function __construct(
        public ?UserContext $userContext = null,
        public ?string $level = null,
        public array $customData = [],
        public array $breadcrumbs = []
    ) {}

    /**
     * Crée une instance depuis les données JSON du webhook
     */
    public static function fromArray(array $data): self
    {
        // Extraire les données utilisateur si présentes
        $userContext = null;
        if (isset($data['user']) && is_array($data['user'])) {
            $userContext = UserContext::fromArray($data['user']);
        }

        // Extraire les breadcrumbs si présents
        $breadcrumbs = [];
        if (isset($data['breadcrumbs']) && is_array($data['breadcrumbs'])) {
            $breadcrumbs = array_map(
                fn(array $breadcrumb) => Breadcrumb::fromArray($breadcrumb),
                array_filter($data['breadcrumbs'], 'is_array')
            );
        }

        // Le reste constitue les données custom
        $customData = $data;
        unset($customData['user'], $customData['breadcrumbs'], $customData['level']);

        return new self(
            userContext: $userContext,
            level: $data['level'] ?? null,
            customData: $customData,
            breadcrumbs: $breadcrumbs
        );
    }

    /**
     * Retourne les données en format array pour compatibilité
     */
    public function toArray(): array
    {
        $data = $this->customData;

        if ($this->userContext) {
            $data['user'] = $this->userContext->toArray();
        }

        if ($this->level) {
            $data['level'] = $this->level;
        }

        if (!empty($this->breadcrumbs)) {
            $data['breadcrumbs'] = array_map(
                fn(Breadcrumb $breadcrumb) => $breadcrumb->toArray(),
                $this->breadcrumbs
            );
        }

        return $data;
    }

    /**
     * Vérifie si le contexte contient des données utilisateur
     */
    public function hasUserContext(): bool
    {
        return $this->userContext !== null && $this->userContext->isValid();
    }

    /**
     * Vérifie si le contexte contient des breadcrumbs
     */
    public function hasBreadcrumbs(): bool
    {
        return !empty($this->breadcrumbs);
    }

    /**
     * Retourne le nombre de breadcrumbs
     */
    public function getBreadcrumbCount(): int
    {
        return count($this->breadcrumbs);
    }

    /**
     * Retourne les breadcrumbs filtrés par niveau
     */
    public function getBreadcrumbsByLevel(string $level): array
    {
        return array_filter(
            $this->breadcrumbs,
            fn(Breadcrumb $breadcrumb) => $breadcrumb->level === $level
        );
    }

    /**
     * Retourne le dernier breadcrumb
     */
    public function getLastBreadcrumb(): ?Breadcrumb
    {
        return end($this->breadcrumbs) ?: null;
    }

    /**
     * Vérifie si le contexte contient des données custom
     */
    public function hasCustomData(): bool
    {
        return !empty($this->customData);
    }

    /**
     * Retourne une valeur custom par clé
     */
    public function getCustomValue(string $key): mixed
    {
        return $this->customData[$key] ?? null;
    }

    /**
     * Vérifie si une clé custom existe
     */
    public function hasCustomKey(string $key): bool
    {
        return array_key_exists($key, $this->customData);
    }

    /**
     * Retourne les clés de données custom
     */
    public function getCustomKeys(): array
    {
        return array_keys($this->customData);
    }

    /**
     * Retourne un résumé du contexte pour l'affichage
     */
    public function getSummary(): string
    {
        $parts = [];

        if ($this->hasUserContext()) {
            $parts[] = "User: " . $this->userContext->getDisplayName();
        }

        if ($this->level) {
            $parts[] = "Level: {$this->level}";
        }

        if ($this->hasBreadcrumbs()) {
            $parts[] = count($this->breadcrumbs) . " breadcrumb(s)";
        }

        if ($this->hasCustomData()) {
            $parts[] = count($this->customData) . " custom field(s)";
        }

        return implode(', ', $parts) ?: 'No context data';
    }

    /**
     * Convertit vers le format JSON pour export
     */
    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Filtre les données sensibles du contexte custom
     */
    public function getSafeCustomData(): array
    {
        $sensitiveKeys = [
            'password', 'passwd', 'secret', 'token', 'api_key', 'access_token',
            'refresh_token', 'auth', 'authorization', 'x-api-key', 'x-auth-token',
            'csrf_token', 'csrf', '_token', 'session', 'cookie'
        ];

        $safeData = [];
        foreach ($this->customData as $key => $value) {
            if (in_array(strtolower($key), $sensitiveKeys, true)) {
                $safeData[$key] = '[FILTERED]';
            } else {
                // Si c'est un array, on applique le filtrage récursivement
                if (is_array($value)) {
                    $safeData[$key] = $this->filterSensitiveArrayData($value, $sensitiveKeys);
                } else {
                    $safeData[$key] = $value;
                }
            }
        }

        return $safeData;
    }

    /**
     * Filtre les données sensibles dans un array de manière récursive
     */
    private function filterSensitiveArrayData(array $data, array $sensitiveKeys): array
    {
        $filtered = [];
        foreach ($data as $key => $value) {
            if (in_array(strtolower($key), $sensitiveKeys, true)) {
                $filtered[$key] = '[FILTERED]';
            } elseif (is_array($value)) {
                $filtered[$key] = $this->filterSensitiveArrayData($value, $sensitiveKeys);
            } else {
                $filtered[$key] = $value;
            }
        }
        return $filtered;
    }
}
