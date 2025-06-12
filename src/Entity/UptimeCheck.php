<?php

namespace App\Entity;

use App\Repository\UptimeCheckRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UptimeCheckRepository::class)]
#[ORM\Table(name: 'uptime_checks')]
#[ORM\Index(name: 'idx_project_timestamp', columns: ['project_id', 'checked_at'])]
#[ORM\Index(name: 'idx_status', columns: ['status', 'checked_at'])]
class UptimeCheck
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Project::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Project $project = null;

    #[ORM\Column(length: 500)]
    private ?string $url = null;

    #[ORM\Column(length: 20)]
    private ?string $status = null; // 'up', 'down', 'timeout', 'error'

    #[ORM\Column(nullable: true)]
    private ?int $httpStatusCode = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 8, scale: 3, nullable: true)]
    private ?string $responseTime = null; // en millisecondes

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $errorMessage = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $checkedAt = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $responseHeaders = [];

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $metadata = [];

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $checkLocation = null; // Lieu du check (serveur de monitoring)

    #[ORM\Column(nullable: true)]
    private ?int $contentLength = null;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $contentChecksum = null; // MD5 du contenu pour détecter les changements

    public function __construct()
    {
        $this->checkedAt = new \DateTime();
        $this->responseHeaders = [];
        $this->metadata = [];
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

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setUrl(string $url): static
    {
        $this->url = $url;
        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function isUp(): bool
    {
        return $this->status === 'up';
    }

    public function isDown(): bool
    {
        return !$this->isUp();
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

    public function getResponseTime(): ?string
    {
        return $this->responseTime;
    }

    public function setResponseTime(?string $responseTime): static
    {
        $this->responseTime = $responseTime;
        return $this;
    }

    public function getResponseTimeAsFloat(): ?float
    {
        return $this->responseTime ? (float) $this->responseTime : null;
    }

    public function setResponseTimeFromFloat(?float $responseTime): static
    {
        $this->responseTime = $responseTime ? (string) $responseTime : null;
        return $this;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function setErrorMessage(?string $errorMessage): static
    {
        $this->errorMessage = $errorMessage;
        return $this;
    }

    public function getCheckedAt(): ?\DateTimeInterface
    {
        return $this->checkedAt;
    }

    public function setCheckedAt(\DateTimeInterface $checkedAt): static
    {
        $this->checkedAt = $checkedAt;
        return $this;
    }

    public function getResponseHeaders(): array
    {
        return $this->responseHeaders ?? [];
    }

    public function setResponseHeaders(?array $responseHeaders): static
    {
        $this->responseHeaders = $responseHeaders ?? [];
        return $this;
    }

    public function getResponseHeader(string $header, mixed $default = null): mixed
    {
        return $this->responseHeaders[strtolower($header)] ?? $default;
    }

    public function addResponseHeader(string $header, string $value): static
    {
        $this->responseHeaders[strtolower($header)] = $value;
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

    public function getCheckLocation(): ?string
    {
        return $this->checkLocation;
    }

    public function setCheckLocation(?string $checkLocation): static
    {
        $this->checkLocation = $checkLocation;
        return $this;
    }

    public function getContentLength(): ?int
    {
        return $this->contentLength;
    }

    public function setContentLength(?int $contentLength): static
    {
        $this->contentLength = $contentLength;
        return $this;
    }

    public function getContentChecksum(): ?string
    {
        return $this->contentChecksum;
    }

    public function setContentChecksum(?string $contentChecksum): static
    {
        $this->contentChecksum = $contentChecksum;
        return $this;
    }

    /**
     * Méthodes statiques pour créer des checks facilement
     */
    public static function createSuccessfulCheck(Project $project, string $url, int $httpStatus, float $responseTime): self
    {
        return (new self())
            ->setProject($project)
            ->setUrl($url)
            ->setStatus('up')
            ->setHttpStatusCode($httpStatus)
            ->setResponseTimeFromFloat($responseTime);
    }

    public static function createFailedCheck(Project $project, string $url, string $errorMessage, int $httpStatus = null): self
    {
        return (new self())
            ->setProject($project)
            ->setUrl($url)
            ->setStatus('down')
            ->setHttpStatusCode($httpStatus)
            ->setErrorMessage($errorMessage);
    }

    public static function createTimeoutCheck(Project $project, string $url, float $timeoutDuration): self
    {
        return (new self())
            ->setProject($project)
            ->setUrl($url)
            ->setStatus('timeout')
            ->setResponseTimeFromFloat($timeoutDuration)
            ->setErrorMessage('Request timeout');
    }

    /**
     * Détermine si le check indique un problème critique
     */
    public function isCritical(): bool
    {
        if ($this->status === 'down') {
            return true;
        }

        if ($this->status === 'timeout') {
            return true;
        }

        if ($this->httpStatusCode && $this->httpStatusCode >= 500) {
            return true;
        }

        $responseTime = $this->getResponseTimeAsFloat();
        if ($responseTime && $responseTime > 10000) { // Plus de 10 secondes
            return true;
        }

        return false;
    }

    /**
     * Détermine si le check indique un problème de performance
     */
    public function hasPerformanceIssue(): bool
    {
        $responseTime = $this->getResponseTimeAsFloat();
        if ($responseTime && $responseTime > 2000) { // Plus de 2 secondes
            return true;
        }

        if ($this->httpStatusCode && ($this->httpStatusCode >= 400 && $this->httpStatusCode < 500)) {
            return true; // Erreurs client qui peuvent indiquer des problèmes
        }

        return false;
    }

    /**
     * Retourne le niveau de sévérité du check
     */
    public function getSeverityLevel(): string
    {
        if ($this->isCritical()) {
            return 'critical';
        }

        if ($this->hasPerformanceIssue()) {
            return 'warning';
        }

        return 'normal';
    }

    /**
     * Retourne une description lisible du check
     */
    public function getDescription(): string
    {
        $responseTime = $this->getResponseTimeAsFloat();
        
        return match($this->status) {
            'up' => "Service en ligne" . ($responseTime ? " ({$responseTime}ms)" : ""),
            'down' => "Service hors ligne" . ($this->errorMessage ? ": {$this->errorMessage}" : ""),
            'timeout' => "Timeout" . ($responseTime ? " après {$responseTime}ms" : ""),
            'error' => "Erreur" . ($this->errorMessage ? ": {$this->errorMessage}" : ""),
            default => "Status: {$this->status}"
        };
    }

    /**
     * Retourne les informations de performance du check
     */
    public function getPerformanceInfo(): array
    {
        return [
            'response_time' => $this->getResponseTimeAsFloat(),
            'http_status' => $this->httpStatusCode,
            'status' => $this->status,
            'is_up' => $this->isUp(),
            'is_critical' => $this->isCritical(),
            'has_performance_issue' => $this->hasPerformanceIssue(),
            'severity' => $this->getSeverityLevel(),
            'description' => $this->getDescription()
        ];
    }
}