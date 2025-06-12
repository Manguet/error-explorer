<?php

// src/Entity/Plan.php
namespace App\Entity;

use App\Repository\PlanRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PlanRepository::class)]
#[ORM\Table(name: 'plans')]
class Plan
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100, unique: true)]
    private ?string $name = null;

    #[ORM\Column(length: 100, unique: true)]
    private ?string $slug = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 8, scale: 2)]
    private ?string $priceMonthly = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 8, scale: 2, nullable: true)]
    private ?string $priceYearly = null;

    #[ORM\Column]
    private ?int $maxProjects = null;

    #[ORM\Column]
    private ?int $maxMonthlyErrors = null;

    #[ORM\Column]
    private ?bool $hasAdvancedFilters = false;

    #[ORM\Column]
    private ?bool $hasApiAccess = false;

    #[ORM\Column]
    private ?bool $hasEmailAlerts = false;

    #[ORM\Column]
    private ?bool $hasSlackIntegration = false;

    #[ORM\Column]
    private ?bool $hasAdvancedAnalytics = false;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $stripePriceIdMonthly = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $stripePriceIdYearly = null;

    #[ORM\Column(nullable: true)]
    private ?int $trialDays = null;

    #[ORM\Column]
    private ?bool $hasPrioritySupport = false;

    #[ORM\Column]
    private ?bool $hasCustomRetention = false;

    #[ORM\Column]
    private ?int $dataRetentionDays = 30;

    #[ORM\Column]
    private ?bool $isActive = true;

    #[ORM\Column]
    private ?bool $isPopular = false;

    #[ORM\Column]
    private ?bool $isBuyable = false;

    #[ORM\Column]
    private ?int $sortOrder = 0;

    #[ORM\Column(type: "text", nullable: true)]
    private ?string $features = null;

    #[ORM\Column]
    private ?bool $hasAiSuggestions = false;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $aiProvider = null;

    #[ORM\Column(nullable: true)]
    private ?int $maxMonthlyAiSuggestions = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $updatedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): static
    {
        $this->slug = $slug;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getPriceMonthly(): ?string
    {
        return $this->priceMonthly;
    }

    public function setPriceMonthly(string $priceMonthly): static
    {
        $this->priceMonthly = $priceMonthly;
        return $this;
    }

    public function getPriceYearly(): ?string
    {
        return $this->priceYearly;
    }

    public function setPriceYearly(?string $priceYearly): static
    {
        $this->priceYearly = $priceYearly;
        return $this;
    }

    public function getMaxProjects(): ?int
    {
        return $this->maxProjects;
    }

    public function setMaxProjects(int $maxProjects): static
    {
        $this->maxProjects = $maxProjects;
        return $this;
    }

    public function getMaxMonthlyErrors(): ?int
    {
        return $this->maxMonthlyErrors;
    }

    public function setMaxMonthlyErrors(int $maxMonthlyErrors): static
    {
        $this->maxMonthlyErrors = $maxMonthlyErrors;
        return $this;
    }

    public function hasAdvancedFilters(): ?bool
    {
        return $this->hasAdvancedFilters;
    }

    public function setHasAdvancedFilters(bool $hasAdvancedFilters): static
    {
        $this->hasAdvancedFilters = $hasAdvancedFilters;
        return $this;
    }

    public function hasApiAccess(): ?bool
    {
        return $this->hasApiAccess;
    }

    public function setHasApiAccess(bool $hasApiAccess): static
    {
        $this->hasApiAccess = $hasApiAccess;
        return $this;
    }

    public function hasEmailAlerts(): ?bool
    {
        return $this->hasEmailAlerts;
    }

    public function setHasEmailAlerts(bool $hasEmailAlerts): static
    {
        $this->hasEmailAlerts = $hasEmailAlerts;
        return $this;
    }

    public function hasSlackIntegration(): ?bool
    {
        return $this->hasSlackIntegration;
    }

    public function setHasSlackIntegration(bool $hasSlackIntegration): static
    {
        $this->hasSlackIntegration = $hasSlackIntegration;
        return $this;
    }

    public function hasAdvancedAnalytics(): ?bool
    {
        return $this->hasAdvancedAnalytics;
    }

    public function setHasAdvancedAnalytics(bool $hasAdvancedAnalytics): static
    {
        $this->hasAdvancedAnalytics = $hasAdvancedAnalytics;
        return $this;
    }

    public function hasPrioritySupport(): ?bool
    {
        return $this->hasPrioritySupport;
    }

    public function setHasPrioritySupport(bool $hasPrioritySupport): static
    {
        $this->hasPrioritySupport = $hasPrioritySupport;
        return $this;
    }

    public function hasCustomRetention(): ?bool
    {
        return $this->hasCustomRetention;
    }

    public function setHasCustomRetention(bool $hasCustomRetention): static
    {
        $this->hasCustomRetention = $hasCustomRetention;
        return $this;
    }

    public function getDataRetentionDays(): ?int
    {
        return $this->dataRetentionDays;
    }

    public function setDataRetentionDays(int $dataRetentionDays): static
    {
        $this->dataRetentionDays = $dataRetentionDays;
        return $this;
    }

    public function isActive(): ?bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;
        return $this;
    }

    public function isPopular(): ?bool
    {
        return $this->isPopular;
    }

    public function setIsPopular(bool $isPopular): static
    {
        $this->isPopular = $isPopular;
        return $this;
    }

    public function isBuyable(): bool
    {
        return $this->isBuyable ?? false;
    }

    public function setIsBuyable(?bool $isBuyable): static
    {
        $this->isBuyable = $isBuyable;
        return $this;
    }

    public function getSortOrder(): ?int
    {
        return $this->sortOrder;
    }

    public function getStripePriceIdMonthly(): ?string
    {
        return $this->stripePriceIdMonthly;
    }

    public function setStripePriceIdMonthly(?string $stripePriceIdMonthly): static
    {
        $this->stripePriceIdMonthly = $stripePriceIdMonthly;
        return $this;
    }

    public function getStripePriceIdYearly(): ?string
    {
        return $this->stripePriceIdYearly;
    }

    public function setStripePriceIdYearly(?string $stripePriceIdYearly): static
    {
        $this->stripePriceIdYearly = $stripePriceIdYearly;
        return $this;
    }

    public function getTrialDays(): ?int
    {
        return $this->trialDays;
    }

    public function setTrialDays(?int $trialDays): static
    {
        $this->trialDays = $trialDays;
        return $this;
    }

    public function setSortOrder(int $sortOrder): static
    {
        $this->sortOrder = $sortOrder;
        return $this;
    }

    public function getFeatures(): ?string
    {
        return $this->features;
    }

    public function setFeatures(?string $features): self
    {
        $this->features = $features;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeInterface $updatedAt): static
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    // Méthodes utilitaires
    public function getFormattedPriceMonthly(): string
    {
        return number_format((float) $this->priceMonthly, 2, ',', ' ') . ' €';
    }

    public function getFormattedPriceYearly(): string
    {
        if (!$this->priceYearly) {
            return '';
        }
        return number_format((float) $this->priceYearly, 2, ',', ' ') . ' €';
    }

    public function getYearlySavings(): ?float
    {
        if (!$this->priceYearly) {
            return null;
        }

        $monthlyTotal = (float) $this->priceMonthly * 12;
        $yearlyPrice = (float) $this->priceYearly;

        return $monthlyTotal - $yearlyPrice;
    }

    public function getMaxProjectsLabel(): string
    {
        return $this->maxProjects === -1 ? 'Illimité' : (string) $this->maxProjects;
    }

    public function getMaxMonthlyErrorsLabel(): string
    {
        if ($this->maxMonthlyErrors === -1) {
            return 'Illimité';
        }

        if ($this->maxMonthlyErrors >= 1000000) {
            return number_format($this->maxMonthlyErrors / 1000000, 1) . 'M';
        }

        if ($this->maxMonthlyErrors >= 1000) {
            return number_format($this->maxMonthlyErrors / 1000, 0) . 'K';
        }

        return number_format($this->maxMonthlyErrors);
    }

    public function isFree(): bool
    {
        return (float) $this->priceMonthly === 0.0;
    }

    public function hasAiSuggestions(): ?bool
    {
        return $this->hasAiSuggestions;
    }

    public function setHasAiSuggestions(bool $hasAiSuggestions): static
    {
        $this->hasAiSuggestions = $hasAiSuggestions;
        return $this;
    }

    public function getAiProvider(): ?string
    {
        return $this->aiProvider;
    }

    public function setAiProvider(?string $aiProvider): static
    {
        $this->aiProvider = $aiProvider;
        return $this;
    }

    public function getMaxMonthlyAiSuggestions(): ?int
    {
        return $this->maxMonthlyAiSuggestions;
    }

    public function setMaxMonthlyAiSuggestions(?int $maxMonthlyAiSuggestions): static
    {
        $this->maxMonthlyAiSuggestions = $maxMonthlyAiSuggestions;
        return $this;
    }

    public function getMaxMonthlyAiSuggestionsLabel(): string
    {
        if ($this->maxMonthlyAiSuggestions === null || $this->maxMonthlyAiSuggestions === -1) {
            return 'Illimité';
        }

        return number_format($this->maxMonthlyAiSuggestions);
    }

    public function supportsAiProvider(string $provider): bool
    {
        if (!$this->hasAiSuggestions) {
            return false;
        }

        return $this->aiProvider === $provider || $this->aiProvider === 'all';
    }
}
