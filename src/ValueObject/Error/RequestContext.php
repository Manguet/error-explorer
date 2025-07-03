<?php

namespace App\ValueObject\Error;

/**
 * Value Object représentant le contexte d'une requête HTTP
 * Compatible avec tous les SDKs (PHP, Node.js, Python, etc.)
 */
class RequestContext
{
    public function __construct(
        public ?string $url = null,
        public ?string $method = null,
        public ?array $headers = null,
        public ?array $query = null,
        public mixed $body = null,
        public ?string $ip = null,
        public ?string $userAgent = null,
        public ?string $route = null,
        public ?array $parameters = null,
        public ?array $cookies = null,
        public ?array $files = null
    ) {}

    /**
     * Crée une instance depuis les données JSON du webhook
     */
    public static function fromArray(array $data): self
    {
        return new self(
            url: $data['url'] ?? null,
            method: $data['method'] ?? null,
            headers: $data['headers'] ?? null,
            query: $data['query'] ?? null,
            body: $data['body'] ?? null,
            ip: $data['ip'] ?? null,
            userAgent: $data['user_agent'] ?? null,
            route: $data['route'] ?? null,
            parameters: $data['parameters'] ?? null,
            cookies: $data['cookies'] ?? null,
            files: $data['files'] ?? null
        );
    }

    /**
     * Retourne les données en format array pour compatibilité
     */
    public function toArray(): array
    {
        return array_filter([
            'url' => $this->url,
            'method' => $this->method,
            'headers' => $this->headers,
            'query' => $this->query,
            'body' => $this->body,
            'ip' => $this->ip,
            'user_agent' => $this->userAgent,
            'route' => $this->route,
            'parameters' => $this->parameters,
            'cookies' => $this->cookies,
            'files' => $this->files
        ], fn($value) => $value !== null);
    }

    /**
     * Vérifie si la requête contient des données valides
     */
    public function isValid(): bool
    {
        return !empty($this->url) || !empty($this->method);
    }

    /**
     * Retourne l'URL formatée pour l'affichage
     */
    public function getDisplayUrl(): ?string
    {
        if (!$this->url) {
            return null;
        }

        // Limite à 100 caractères pour l'affichage
        return strlen($this->url) > 100
            ? substr($this->url, 0, 100) . '...'
            : $this->url;
    }

    /**
     * Vérifie si la requête utilise HTTPS
     */
    public function isSecure(): bool
    {
        return $this->url && str_starts_with($this->url, 'https://');
    }

    /**
     * Retourne la méthode HTTP en majuscules
     */
    public function getMethodUpperCase(): ?string
    {
        return $this->method ? strtoupper($this->method) : null;
    }

    /**
     * Vérifie si la requête contient des données POST
     */
    public function hasBody(): bool
    {
        return !empty($this->body);
    }

    /**
     * Retourne les headers sans les données sensibles
     */
    public function getSafeHeaders(): array
    {
        if (!$this->headers) {
            return [];
        }

        $sensitiveHeaders = [
            'authorization', 'x-api-key', 'x-auth-token', 'cookie',
            'x-csrf-token', 'x-xsrf-token', 'authentication'
        ];

        $safeHeaders = [];
        foreach ($this->headers as $key => $value) {
            if (in_array(strtolower($key), $sensitiveHeaders, true)) {
                $safeHeaders[$key] = '[FILTERED]';
            } else {
                $safeHeaders[$key] = $value;
            }
        }

        return $safeHeaders;
    }

    /**
     * Retourne un résumé de la requête pour le logging
     */
    public function getSummary(): string
    {
        $parts = [];

        if ($this->method) {
            $parts[] = strtoupper($this->method);
        }

        if ($this->url) {
            $parts[] = $this->getDisplayUrl();
        }

        if ($this->ip) {
            $parts[] = "from {$this->ip}";
        }

        return implode(' ', $parts) ?: 'Unknown request';
    }

    /**
     * Vérifie si l'IP est privée
     */
    public function isPrivateIp(): bool
    {
        if (!$this->ip) {
            return false;
        }

        return filter_var(
            $this->ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) === false;
    }
}
