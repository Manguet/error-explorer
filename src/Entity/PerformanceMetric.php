<?php

namespace App\Entity;

use App\Repository\PerformanceMetricRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PerformanceMetricRepository::class)]
#[ORM\Table(name: 'performance_metrics')]
#[ORM\Index(name: 'idx_project_timestamp', columns: ['project_id', 'recorded_at'])]
#[ORM\Index(name: 'idx_metric_type', columns: ['metric_type', 'recorded_at'])]
#[ORM\Index(name: 'idx_recorded_at', columns: ['recorded_at'])]
class PerformanceMetric
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Project::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Project $project = null;

    #[ORM\Column(length: 50)]
    private ?string $metricType = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 3)]
    private ?string $value = null;

    #[ORM\Column(length: 20)]
    private ?string $unit = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $metadata = [];

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $recordedAt = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $source = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $environment = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $endpoint = null;

    #[ORM\Column(nullable: true)]
    private ?int $httpStatusCode = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $tags = [];

    public function __construct()
    {
        $this->recordedAt = new \DateTime();
        $this->metadata = [];
        $this->tags = [];
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProject(): ?Project
    {
        return $this->project;
    }

    public function setProject(?Project $project): static
    {
        $this->project = $project;
        return $this;
    }

    public function getMetricType(): ?string
    {
        return $this->metricType;
    }

    public function setMetricType(string $metricType): static
    {
        $this->metricType = $metricType;
        return $this;
    }

    public function getValue(): ?string
    {
        return $this->value;
    }

    public function setValue(string $value): static
    {
        $this->value = $value;
        return $this;
    }

    public function getValueAsFloat(): ?float
    {
        return $this->value ? (float) $this->value : null;
    }

    public function setValueFromFloat(float $value): static
    {
        $this->value = (string) $value;
        return $this;
    }

    public function getUnit(): ?string
    {
        return $this->unit;
    }

    public function setUnit(string $unit): static
    {
        $this->unit = $unit;
        return $this;
    }

    public function getMetadata(): array
    {
        return $this->metadata ?? [];
    }

    public function setMetadata(?array $metadata): static
    {
        $this->metadata = $metadata ?? [];
        return $this;
    }

    public function addMetadata(string $key, mixed $value): static
    {
        $this->metadata[$key] = $value;
        return $this;
    }

    public function getMetadataValue(string $key, mixed $default = null): mixed
    {
        return $this->metadata[$key] ?? $default;
    }

    public function getRecordedAt(): ?\DateTimeInterface
    {
        return $this->recordedAt;
    }

    public function setRecordedAt(\DateTimeInterface $recordedAt): static
    {
        $this->recordedAt = $recordedAt;
        return $this;
    }

    public function getSource(): ?string
    {
        return $this->source;
    }

    public function setSource(?string $source): static
    {
        $this->source = $source;
        return $this;
    }

    public function getEnvironment(): ?string
    {
        return $this->environment;
    }

    public function setEnvironment(?string $environment): static
    {
        $this->environment = $environment;
        return $this;
    }

    public function getEndpoint(): ?string
    {
        return $this->endpoint;
    }

    public function setEndpoint(?string $endpoint): static
    {
        $this->endpoint = $endpoint;
        return $this;
    }

    public function getHttpStatusCode(): ?int
    {
        return $this->httpStatusCode;
    }

    public function setHttpStatusCode(?int $httpStatusCode): static
    {
        $this->httpStatusCode = $httpStatusCode;
        return $this;
    }

    public function getTags(): array
    {
        return $this->tags ?? [];
    }

    public function setTags(?array $tags): static
    {
        $this->tags = $tags ?? [];
        return $this;
    }

    public function addTag(string $tag): static
    {
        if (!in_array($tag, $this->tags)) {
            $this->tags[] = $tag;
        }
        return $this;
    }

    public function hasTag(string $tag): bool
    {
        return in_array($tag, $this->tags);
    }

    // Méthodes utilitaires pour les types de métriques courants

    public static function createResponseTimeMetric(Project $project, float $responseTime, string $endpoint = null): self
    {
        return (new self())
            ->setProject($project)
            ->setMetricType('response_time')
            ->setValueFromFloat($responseTime)
            ->setUnit('ms')
            ->setEndpoint($endpoint);
    }

    public static function createThroughputMetric(Project $project, float $requestsPerSecond): self
    {
        return (new self())
            ->setProject($project)
            ->setMetricType('throughput')
            ->setValueFromFloat($requestsPerSecond)
            ->setUnit('req/s');
    }

    public static function createErrorRateMetric(Project $project, float $errorRate): self
    {
        return (new self())
            ->setProject($project)
            ->setMetricType('error_rate')
            ->setValueFromFloat($errorRate)
            ->setUnit('percent');
    }

    public static function createCpuUsageMetric(Project $project, float $cpuPercent): self
    {
        return (new self())
            ->setProject($project)
            ->setMetricType('cpu_usage')
            ->setValueFromFloat($cpuPercent)
            ->setUnit('percent');
    }

    public static function createMemoryUsageMetric(Project $project, float $memoryMB): self
    {
        return (new self())
            ->setProject($project)
            ->setMetricType('memory_usage')
            ->setValueFromFloat($memoryMB)
            ->setUnit('MB');
    }

    public static function createDatabaseQueryTimeMetric(Project $project, float $queryTime, array $metadata = []): self
    {
        return (new self())
            ->setProject($project)
            ->setMetricType('db_query_time')
            ->setValueFromFloat($queryTime)
            ->setUnit('ms')
            ->setMetadata($metadata);
    }

    public static function createUptimeMetric(Project $project, bool $isUp): self
    {
        return (new self())
            ->setProject($project)
            ->setMetricType('uptime')
            ->setValueFromFloat($isUp ? 1.0 : 0.0)
            ->setUnit('boolean');
    }

    /**
     * Vérifie si la métrique indique un problème de performance
     */
    public function isPerformanceIssue(): bool
    {
        $value = $this->getValueAsFloat();
        if ($value === null) return false;

        return match($this->metricType) {
            'response_time' => $value > 1000, // Plus de 1 seconde
            'error_rate' => $value > 5, // Plus de 5%
            'cpu_usage' => $value > 80, // Plus de 80%
            'memory_usage' => $value > 1024, // Plus de 1GB
            'db_query_time' => $value > 500, // Plus de 500ms
            'uptime' => $value < 1, // Down
            default => false
        };
    }

    /**
     * Retourne le niveau de sévérité de la métrique
     */
    public function getSeverityLevel(): string
    {
        if (!$this->isPerformanceIssue()) {
            return 'normal';
        }

        $value = $this->getValueAsFloat();
        
        return match($this->metricType) {
            'response_time' => $value > 5000 ? 'critical' : ($value > 2000 ? 'high' : 'medium'),
            'error_rate' => $value > 20 ? 'critical' : ($value > 10 ? 'high' : 'medium'),
            'cpu_usage' => $value > 95 ? 'critical' : ($value > 90 ? 'high' : 'medium'),
            'memory_usage' => $value > 2048 ? 'critical' : ($value > 1536 ? 'high' : 'medium'),
            'db_query_time' => $value > 2000 ? 'critical' : ($value > 1000 ? 'high' : 'medium'),
            'uptime' => 'critical',
            default => 'medium'
        };
    }

    /**
     * Retourne une description lisible de la métrique
     */
    public function getDescription(): string
    {
        $value = $this->getValueAsFloat();
        
        return match($this->metricType) {
            'response_time' => "Temps de réponse: {$value}ms",
            'throughput' => "Débit: {$value} req/s",
            'error_rate' => "Taux d'erreur: {$value}%",
            'cpu_usage' => "Usage CPU: {$value}%",
            'memory_usage' => "Usage mémoire: {$value}MB",
            'db_query_time' => "Temps de requête DB: {$value}ms",
            'uptime' => $value > 0 ? "Service en ligne" : "Service hors ligne",
            default => "{$this->metricType}: {$value} {$this->unit}"
        };
    }
}