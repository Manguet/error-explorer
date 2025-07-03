<?php

namespace App\Entity;

use App\Repository\ErrorOccurrenceRepository;
use DateTime;
use DateTimeInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ErrorOccurrenceRepository::class)]
#[ORM\Table(name: 'error_occurrences')]
#[ORM\Index(name: 'idx_error_group', columns: ['error_group_id'])]
#[ORM\Index(name: 'idx_created_at', columns: ['created_at'])]
#[ORM\Index(name: 'idx_environment', columns: ['environment'])]
class ErrorOccurrence
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'occurrences')]
    #[ORM\JoinColumn(nullable: false)]
    private ?ErrorGroup $errorGroup = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $stackTrace = null;

    #[ORM\Column(length: 100)]
    private ?string $environment = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private array $request = [];

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private array $server = [];

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private array $context = [];

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?DateTimeInterface $createdAt;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $userId = null;

    #[ORM\Column(length: 45, nullable: true)]
    private ?string $ipAddress = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $userAgent = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $url = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $httpMethod = null;

    #[ORM\Column(nullable: true)]
    private ?int $memoryUsage = null;

    #[ORM\Column(nullable: true)]
    private ?float $executionTime = null;

    #[ORM\Column(length: 40, nullable: true)]
    private ?string $commitHash = null;

    public function __construct()
    {
        $this->createdAt = new DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getErrorGroup(): ?ErrorGroup
    {
        return $this->errorGroup;
    }

    public function setErrorGroup(?ErrorGroup $errorGroup): static
    {
        $this->errorGroup = $errorGroup;
        return $this;
    }

    public function getStackTrace(): ?string
    {
        return $this->stackTrace;
    }

    public function setStackTrace(string $stackTrace): static
    {
        $this->stackTrace = $stackTrace;
        return $this;
    }

    public function getEnvironment(): ?string
    {
        return $this->environment;
    }

    public function setEnvironment(string $environment): static
    {
        $this->environment = $environment;
        return $this;
    }

    public function getRequest(): array
    {
        return $this->request;
    }

    public function setRequest(?array $request): static
    {
        $this->request = $request ?? [];
        return $this;
    }

    public function getServer(): array
    {
        return $this->server;
    }

    public function setServer(?array $server): static
    {
        $this->server = $server ?? [];
        return $this;
    }

    public function getContext(): array
    {
        return $this->context;
    }

    public function setContext(?array $context): static
    {
        $this->context = $context ?? [];
        return $this;
    }

    public function getCreatedAt(): ?DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUserId(): ?string
    {
        return $this->userId;
    }

    public function setUserId(?string $userId): static
    {
        $this->userId = $userId;
        return $this;
    }

    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function setIpAddress(?string $ipAddress): static
    {
        $this->ipAddress = $ipAddress;
        return $this;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function setUserAgent(?string $userAgent): static
    {
        $this->userAgent = $userAgent;
        return $this;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setUrl(?string $url): static
    {
        $this->url = $url;
        return $this;
    }

    public function getHttpMethod(): ?string
    {
        return $this->httpMethod;
    }

    public function setHttpMethod(?string $httpMethod): static
    {
        $this->httpMethod = $httpMethod;
        return $this;
    }

    public function getMemoryUsage(): ?int
    {
        return $this->memoryUsage;
    }

    public function setMemoryUsage(?int $memoryUsage): static
    {
        $this->memoryUsage = $memoryUsage;
        return $this;
    }

    public function getExecutionTime(): ?float
    {
        return $this->executionTime;
    }

    public function setExecutionTime(?float $executionTime): static
    {
        $this->executionTime = $executionTime;
        return $this;
    }

    public function getCommitHash(): ?string
    {
        return $this->commitHash;
    }

    public function setCommitHash(?string $commitHash): static
    {
        $this->commitHash = $commitHash;
        return $this;
    }

    /**
     * Vérifie si cette occurrence a des données de contexte utilisateur
     */
    public function hasUserContext(): bool
    {
        return !empty($this->userId) || !empty($this->context['user']) ?? false;
    }

    /**
     * Retourne les informations utilisateur disponibles
     */
    public function getUserContext(): array
    {
        $userInfo = [];

        if ($this->userId) {
            $userInfo['id'] = $this->userId;
        }

        if (isset($this->context['user'])) {
            $userInfo = array_merge($userInfo, $this->context['user']);
        }

        return $userInfo;
    }
}
