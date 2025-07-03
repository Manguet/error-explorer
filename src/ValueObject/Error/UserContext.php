<?php

namespace App\ValueObject\Error;

/**
 * Value Object représentant le contexte utilisateur
 * Compatible avec tous les SDKs
 */
class UserContext
{
    public function __construct(
        public ?string $id = null,
        public ?string $email = null,
        public ?string $username = null,
        public ?string $name = null,
        public ?string $ip = null,
        public array $extra = []
    ) {}

    /**
     * Crée une instance depuis les données JSON du webhook
     */
    public static function fromArray(array $data): self
    {
        // Extraire les champs connus
        $knownFields = ['id', 'email', 'username', 'name', 'ip'];
        $extra = $data;

        foreach ($knownFields as $field) {
            unset($extra[$field]);
        }

        return new self(
            id: isset($data['id']) ? (string) $data['id'] : null,
            email: $data['email'] ?? null,
            username: $data['username'] ?? null,
            name: $data['name'] ?? null,
            ip: $data['ip'] ?? null,
            extra: $extra
        );
    }

    /**
     * Retourne les données en format array pour compatibilité
     */
    public function toArray(): array
    {
        $data = array_filter([
            'id' => $this->id,
            'email' => $this->email,
            'username' => $this->username,
            'name' => $this->name,
            'ip' => $this->ip
        ], fn($value) => $value !== null);

        return array_merge($data, $this->extra);
    }

    /**
     * Vérifie si le contexte utilisateur contient des données valides
     */
    public function isValid(): bool
    {
        return !empty($this->id) || !empty($this->email) || !empty($this->username);
    }

    /**
     * Retourne le nom d'affichage préféré de l'utilisateur
     */
    public function getDisplayName(): string
    {
        // Priorité : name > username > email > id
        if (!empty($this->name)) {
            return $this->name;
        }

        if (!empty($this->username)) {
            return $this->username;
        }

        if (!empty($this->email)) {
            return $this->email;
        }

        if (!empty($this->id)) {
            return "User #{$this->id}";
        }

        return 'Anonymous User';
    }

    /**
     * Retourne l'identifiant unique de l'utilisateur
     */
    public function getUniqueIdentifier(): ?string
    {
        return $this->id ?? $this->email ?? $this->username;
    }

    /**
     * Vérifie si l'utilisateur a un email valide
     */
    public function hasValidEmail(): bool
    {
        return !empty($this->email) && filter_var($this->email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Retourne l'email masqué pour l'affichage public
     */
    public function getMaskedEmail(): ?string
    {
        if (!$this->email) {
            return null;
        }

        $parts = explode('@', $this->email);
        if (count($parts) !== 2) {
            return '[EMAIL INVALID]';
        }

        $localPart = $parts[0];
        $domain = $parts[1];

        // Masquer la partie locale en gardant le premier et dernier caractère
        if (strlen($localPart) <= 2) {
            $maskedLocal = str_repeat('*', strlen($localPart));
        } else {
            $maskedLocal = $localPart[0] . str_repeat('*', strlen($localPart) - 2) . substr($localPart, -1);
        }

        return $maskedLocal . '@' . $domain;
    }

    /**
     * Vérifie si l'IP utilisateur est privée
     */
    public function hasPrivateIp(): bool
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

    /**
     * Retourne l'IP masquée pour la confidentialité
     */
    public function getMaskedIp(): ?string
    {
        if (!$this->ip) {
            return null;
        }

        // IPv4
        if (filter_var($this->ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $parts = explode('.', $this->ip);
            $parts[3] = 'xxx';
            return implode('.', $parts);
        }

        // IPv6
        if (filter_var($this->ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $parts = explode(':', $this->ip);
            $visibleParts = array_slice($parts, 0, 4);
            return implode(':', $visibleParts) . ':xxxx:xxxx:xxxx:xxxx';
        }

        return '[IP INVALID]';
    }

    /**
     * Vérifie si des données extra sont présentes
     */
    public function hasExtra(): bool
    {
        return !empty($this->extra);
    }

    /**
     * Retourne une valeur extra par clé
     */
    public function getExtra(string $key): mixed
    {
        return $this->extra[$key] ?? null;
    }

    /**
     * Retourne les données extra filtrées (sans données sensibles)
     */
    public function getSafeExtra(): array
    {
        $sensitiveKeys = [
            'password', 'passwd', 'secret', 'token', 'api_key', 'access_token',
            'refresh_token', 'auth', 'authorization', 'session', 'cookie'
        ];

        $safeExtra = [];
        foreach ($this->extra as $key => $value) {
            if (in_array(strtolower($key), $sensitiveKeys, true)) {
                $safeExtra[$key] = '[FILTERED]';
            } else {
                $safeExtra[$key] = $value;
            }
        }

        return $safeExtra;
    }

    /**
     * Retourne un résumé de l'utilisateur pour les logs
     */
    public function getSummary(): string
    {
        $parts = [];

        $displayName = $this->getDisplayName();
        if ($displayName !== 'Anonymous User') {
            $parts[] = $displayName;
        }

        if ($this->id && $this->id !== $this->getDisplayName()) {
            $parts[] = "ID: {$this->id}";
        }

        if ($this->ip) {
            $parts[] = "IP: {$this->getMaskedIp()}";
        }

        if ($this->hasExtra()) {
            $parts[] = count($this->extra) . " extra field(s)";
        }

        return implode(', ', $parts) ?: 'Anonymous User';
    }

    /**
     * Convertit vers un format sécurisé pour l'affichage public
     */
    public function toSafeArray(): array
    {
        return array_filter([
            'id' => $this->id,
            'email' => $this->getMaskedEmail(),
            'username' => $this->username,
            'name' => $this->name,
            'ip' => $this->getMaskedIp(),
            'extra' => $this->getSafeExtra()
        ], fn($value) => $value !== null && $value !== []);
    }
}
