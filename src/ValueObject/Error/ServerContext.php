<?php

namespace App\ValueObject\Error;

/**
 * Value Object représentant le contexte serveur
 * Compatible avec tous les SDKs (PHP, Node.js, Python, etc.)
 */
class ServerContext
{
    public function __construct(
        // Communs à tous les SDKs
        public ?string $hostname = null,
        public ?int $memoryUsage = null,
        public ?int $pid = null,

        // Spécifiques PHP
        public ?string $phpVersion = null,
        public ?string $serverSoftware = null,
        public ?string $memoryLimit = null,
        public ?int $memoryPeak = null,
        public ?array $extensions = null,
        public ?array $iniSettings = null,

        // Spécifiques Node.js
        public ?string $nodeVersion = null,
        public ?string $platform = null,
        public ?string $arch = null,
        public ?float $uptime = null,

        // Spécifiques Python
        public ?string $pythonVersion = null,
        public ?int $threadId = null,

        // Framework spécifique
        public ?string $laravelVersion = null,
        public ?string $symfonyVersion = null,

        // Métriques supplémentaires
        public ?float $executionTime = null,
        public ?array $environment = null
    ) {}

    /**
     * Crée une instance depuis les données JSON du webhook
     */
    public static function fromArray(array $data): self
    {
        return new self(
            hostname: $data['hostname'] ?? null,
            memoryUsage: self::parseInteger($data['memory_usage'] ?? null),
            pid: self::parseInteger($data['pid'] ?? null),
            phpVersion: $data['php_version'] ?? null,
            serverSoftware: $data['server_software'] ?? null,
            memoryLimit: $data['memory_limit'] ?? null,
            memoryPeak: self::parseInteger($data['memory_peak'] ?? null),
            extensions: $data['extensions'] ?? null,
            iniSettings: $data['ini_settings'] ?? null,
            nodeVersion: $data['node_version'] ?? null,
            platform: $data['platform'] ?? null,
            arch: $data['arch'] ?? null,
            uptime: self::parseFloat($data['uptime'] ?? null),
            pythonVersion: $data['python_version'] ?? null,
            threadId: self::parseInteger($data['thread_id'] ?? null),
            laravelVersion: $data['laravel_version'] ?? null,
            symfonyVersion: $data['symfony_version'] ?? null,
            executionTime: self::parseFloat($data['execution_time'] ?? null),
            environment: $data['environment'] ?? null
        );
    }

    /**
     * Retourne les données en format array pour compatibilité
     */
    public function toArray(): array
    {
        return array_filter([
            'hostname' => $this->hostname,
            'memory_usage' => $this->memoryUsage,
            'pid' => $this->pid,
            'php_version' => $this->phpVersion,
            'server_software' => $this->serverSoftware,
            'memory_limit' => $this->memoryLimit,
            'memory_peak' => $this->memoryPeak,
            'extensions' => $this->extensions,
            'ini_settings' => $this->iniSettings,
            'node_version' => $this->nodeVersion,
            'platform' => $this->platform,
            'arch' => $this->arch,
            'uptime' => $this->uptime,
            'python_version' => $this->pythonVersion,
            'thread_id' => $this->threadId,
            'laravel_version' => $this->laravelVersion,
            'symfony_version' => $this->symfonyVersion,
            'execution_time' => $this->executionTime,
            'environment' => $this->environment
        ], fn($value) => $value !== null);
    }

    /**
     * Détecte le type de runtime (PHP, Node.js, Python)
     */
    public function getRuntimeType(): ?string
    {
        if ($this->phpVersion) {
            return 'PHP';
        }

        if ($this->nodeVersion) {
            return 'Node.js';
        }

        if ($this->pythonVersion) {
            return 'Python';
        }

        return null;
    }

    /**
     * Retourne la version du runtime
     */
    public function getRuntimeVersion(): ?string
    {
        return $this->phpVersion ?? $this->nodeVersion ?? $this->pythonVersion;
    }

    /**
     * Détecte le framework utilisé
     */
    public function getFramework(): ?string
    {
        if ($this->laravelVersion) {
            return "Laravel {$this->laravelVersion}";
        }

        if ($this->symfonyVersion) {
            return "Symfony {$this->symfonyVersion}";
        }

        return null;
    }

    /**
     * Vérifie si les métriques de performance sont disponibles
     */
    public function hasPerformanceMetrics(): bool
    {
        return $this->memoryUsage !== null || $this->executionTime !== null;
    }

    /**
     * Retourne un résumé des informations serveur
     */
    public function getSummary(): string
    {
        $parts = [];

        $runtime = $this->getRuntimeType();
        $version = $this->getRuntimeVersion();
        if ($runtime && $version) {
            $parts[] = "{$runtime} {$version}";
        }

        $framework = $this->getFramework();
        if ($framework) {
            $parts[] = $framework;
        }

        if ($this->hostname) {
            $parts[] = "on {$this->hostname}";
        }

        if ($this->platform) {
            $parts[] = "({$this->platform})";
        }

        return implode(' ', $parts) ?: 'Unknown server';
    }

    /**
     * Vérifie si c'est un environnement de développement
     */
    public function isDevelopment(): bool
    {
        if (!$this->environment) {
            return false;
        }

        $devIndicators = ['dev', 'development', 'local', 'localhost'];

        foreach ($this->environment as $key => $value) {
            $searchIn = is_string($value) ? strtolower($value) : strtolower((string) $key);

            foreach ($devIndicators as $indicator) {
                if (str_contains($searchIn, $indicator)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Retourne les informations critiques pour le debugging
     */
    public function getCriticalInfo(): array
    {
        return array_filter([
            'runtime' => $this->getRuntimeType(),
            'version' => $this->getRuntimeVersion(),
            'framework' => $this->getFramework(),
            'memory_usage' => $this->memoryUsage,
            'execution_time' => $this->executionTime,
            'hostname' => $this->hostname,
            'pid' => $this->pid
        ]);
    }

    /**
     * Parse un entier de manière sécurisée
     */
    private static function parseInteger(mixed $value): ?int
    {
        if ($value === null) {
            return null;
        }

        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && is_numeric($value)) {
            return (int) $value;
        }

        return null;
    }

    /**
     * Parse un float de manière sécurisée
     */
    private static function parseFloat(mixed $value): ?float
    {
        if ($value === null) {
            return null;
        }

        if (is_float($value) || is_int($value)) {
            return (float) $value;
        }

        if (is_string($value) && is_numeric($value)) {
            return (float) $value;
        }

        return null;
    }
}
