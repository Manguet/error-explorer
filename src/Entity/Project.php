<?php

namespace App\Entity;

use App\Repository\ProjectRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProjectRepository::class)]
#[ORM\Table(name: 'projects')]
#[ORM\Index(name: 'idx_webhook_token', columns: ['webhook_token'])]
#[ORM\Index(name: 'idx_slug', columns: ['slug'])]
#[ORM\Index(name: 'idx_owner', columns: ['owner_id'])]
class Project
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private ?string $name = null;

    #[ORM\Column(length: 120)]
    private ?string $slug = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 64, unique: true)]
    private ?string $webhookToken = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $environment = null;

    #[ORM\Column]
    private ?bool $isActive = true;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $lastErrorAt = null;

    #[ORM\Column(nullable: true)]
    private ?int $totalErrors = 0;

    #[ORM\Column(nullable: true)]
    private ?int $totalOccurrences = 0;

    #[ORM\OneToMany(targetEntity: ErrorGroup::class, mappedBy: 'projectEntity', orphanRemoval: true)]
    private Collection $errorGroups;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private array $settings = [];

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $notificationEmail = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $repositoryUrl = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $gitProvider = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $gitAccessCredentials = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'projects')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $owner = null;

    #[ORM\ManyToOne(targetEntity: Team::class, inversedBy: 'projects')]
    #[ORM\JoinColumn(nullable: true)]
    private ?Team $team = null;

    #[ORM\Column]
    private ?int $currentMonthErrors = 0;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $monthlyCounterResetAt = null;

    // Configuration des alertes
    #[ORM\Column]
    private bool $alertsEnabled = true;

    #[ORM\Column]
    private bool $dailySummaryEnabled = false;

    #[ORM\Column(nullable: true)]
    private ?int $alertThreshold = 1;

    #[ORM\Column(nullable: true)]
    private ?int $alertCooldownMinutes = 30;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $alertStatusFilters = ['open'];

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $alertEnvironmentFilters = [];

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $alertsEmail = null;

    // Configuration Slack
    #[ORM\Column]
    private bool $slackAlertsEnabled = false;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $slackWebhookUrl = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $slackChannel = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $slackUsername = 'Error Explorer';

    // Configuration Discord
    #[ORM\Column]
    private bool $discordAlertsEnabled = false;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $discordWebhookUrl = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $discordChannel = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $discordUsername = 'Error Explorer';

    // Configuration des webhooks externes
    #[ORM\Column]
    private bool $externalWebhooksEnabled = false;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $externalWebhooks = [];

    public function __construct()
    {
        $this->errorGroups = new ArrayCollection();
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
        $this->generateWebhookToken();
        $this->resetMonthlyCounter();
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
        $this->generateSlug();
        $this->updatedAt = new \DateTime();
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
        $this->updatedAt = new \DateTime();
        return $this;
    }

    public function getWebhookToken(): ?string
    {
        return $this->webhookToken;
    }

    public function setWebhookToken(string $webhookToken): static
    {
        $this->webhookToken = $webhookToken;
        return $this;
    }

    public function getEnvironment(): ?string
    {
        return $this->environment;
    }

    public function setEnvironment(?string $environment): static
    {
        $this->environment = $environment;
        $this->updatedAt = new \DateTime();
        return $this;
    }

    public function isActive(): ?bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;
        $this->updatedAt = new \DateTime();
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

    public function getLastErrorAt(): ?\DateTimeInterface
    {
        return $this->lastErrorAt;
    }

    public function setLastErrorAt(?\DateTimeInterface $lastErrorAt): static
    {
        $this->lastErrorAt = $lastErrorAt;
        return $this;
    }

    public function getTotalErrors(): ?int
    {
        return $this->totalErrors;
    }

    public function setTotalErrors(int $totalErrors): static
    {
        $this->totalErrors = $totalErrors;
        return $this;
    }

    public function incrementTotalErrors(): static
    {
        $this->totalErrors++;
        $this->lastErrorAt = new \DateTime();
        return $this;
    }

    public function getTotalOccurrences(): ?int
    {
        return $this->totalOccurrences;
    }

    public function setTotalOccurrences(int $totalOccurrences): static
    {
        $this->totalOccurrences = $totalOccurrences;
        return $this;
    }

    public function incrementTotalOccurrences(int $count = 1): static
    {
        $this->totalOccurrences += $count;
        $this->lastErrorAt = new \DateTime();
        return $this;
    }

    /**
     * @return Collection<int, ErrorGroup>
     */
    public function getErrorGroups(): Collection
    {
        return $this->errorGroups;
    }

    public function addErrorGroup(ErrorGroup $errorGroup): static
    {
        if (!$this->errorGroups->contains($errorGroup)) {
            $this->errorGroups->add($errorGroup);
            $errorGroup->setProjectEntity($this);
        }

        return $this;
    }

    public function removeErrorGroup(ErrorGroup $errorGroup): static
    {
        if ($this->errorGroups->removeElement($errorGroup)) {
            if ($errorGroup->getProjectEntity() === $this) {
                $errorGroup->setProjectEntity(null);
            }
        }

        return $this;
    }

    public function getSettings(): array
    {
        return $this->settings;
    }

    public function setSettings(?array $settings): static
    {
        $this->settings = $settings ?? [];
        $this->updatedAt = new \DateTime();
        return $this;
    }

    public function getSetting(string $key, mixed $default = null): mixed
    {
        return $this->settings[$key] ?? $default;
    }

    public function setSetting(string $key, mixed $value): static
    {
        $this->settings[$key] = $value;
        $this->updatedAt = new \DateTime();
        return $this;
    }

    public function getNotificationEmail(): ?string
    {
        return $this->notificationEmail;
    }

    public function setNotificationEmail(?string $notificationEmail): static
    {
        $this->notificationEmail = $notificationEmail;
        $this->updatedAt = new \DateTime();
        return $this;
    }

    public function getRepositoryUrl(): ?string
    {
        return $this->repositoryUrl;
    }

    public function setRepositoryUrl(?string $repositoryUrl): static
    {
        $this->repositoryUrl = $repositoryUrl;
        $this->updatedAt = new \DateTime();
        return $this;
    }

    public function getGitProvider(): ?string
    {
        return $this->gitProvider;
    }

    public function setGitProvider(?string $gitProvider): static
    {
        $this->gitProvider = $gitProvider;
        return $this;
    }

    public function getGitAccessCredentials(): ?array
    {
        return $this->gitAccessCredentials;
    }

    public function setGitAccessCredentials(?array $gitAccessCredentials): static
    {
        $this->gitAccessCredentials = $gitAccessCredentials;
        return $this;
    }

    /**
     * Récupère le token d'accès Git chiffré
     */
    public function getGitAccessToken(): ?string
    {
        return $this->gitAccessCredentials['encrypted_token'] ?? null;
    }

    /**
     * Définit le token d'accès Git chiffré
     */
    public function setGitAccessToken(?string $encryptedToken): static
    {
        if (!$this->gitAccessCredentials) {
            $this->gitAccessCredentials = [];
        }
        
        if ($encryptedToken) {
            $this->gitAccessCredentials['encrypted_token'] = $encryptedToken;
        } else {
            unset($this->gitAccessCredentials['encrypted_token']);
        }
        
        $this->updatedAt = new \DateTime();
        return $this;
    }

    /**
     * Vérifie si l'intégration Git est configurée et prête à l'emploi.
     */
    public function isGitConfigured(): bool
    {
        return !empty($this->repositoryUrl) &&
               !empty($this->gitProvider) &&
               !empty($this->gitAccessCredentials['encrypted_token']);
    }

    public function getOwner(): ?User
    {
        return $this->owner;
    }

    public function setOwner(?User $owner): static
    {
        $this->owner = $owner;
        return $this;
    }

    public function getTeam(): ?Team
    {
        return $this->team;
    }

    public function setTeam(?Team $team): static
    {
        $this->team = $team;
        $this->updatedAt = new \DateTime();
        return $this;
    }

    public function getCurrentMonthErrors(): ?int
    {
        return $this->currentMonthErrors;
    }

    public function setCurrentMonthErrors(int $currentMonthErrors): static
    {
        $this->currentMonthErrors = $currentMonthErrors;
        return $this;
    }

    public function getMonthlyCounterResetAt(): ?\DateTimeInterface
    {
        return $this->monthlyCounterResetAt;
    }

    public function setMonthlyCounterResetAt(?\DateTimeInterface $monthlyCounterResetAt): static
    {
        $this->monthlyCounterResetAt = $monthlyCounterResetAt;
        return $this;
    }

    /**
     * Incrémente le compteur d'erreurs mensuelles
     */
    public function incrementMonthlyErrors(int $count = 1): static
    {
        // Vérifier si on doit reset le compteur mensuel
        if ($this->shouldResetMonthlyCounter()) {
            $this->resetMonthlyCounter();
        }

        $this->currentMonthErrors += $count;
        return $this;
    }

    /**
     * Vérifie si le projet peut recevoir plus d'erreurs ce mois
     */
    public function canReceiveError(): bool
    {
        if (!$this->owner || !$this->owner->getPlan()) {
            return false;
        }

        $plan = $this->owner->getPlan();

        // Plan illimité
        if ($plan->getMaxMonthlyErrors() === -1) {
            return true;
        }

        // Vérifier si on doit reset le compteur
        if ($this->shouldResetMonthlyCounter()) {
            $this->resetMonthlyCounter();
        }

        return $this->currentMonthErrors < $plan->getMaxMonthlyErrors();
    }

    /**
     * Vérifie si le compteur mensuel doit être remis à zéro
     */
    private function shouldResetMonthlyCounter(): bool
    {
        if (!$this->monthlyCounterResetAt) {
            return true;
        }

        return $this->monthlyCounterResetAt < new \DateTime('first day of this month');
    }

    /**
     * Remet à zéro le compteur mensuel
     */
    private function resetMonthlyCounter(): void
    {
        $this->currentMonthErrors = 0;
        $this->monthlyCounterResetAt = new \DateTime('first day of next month');
    }

    /**
     * Génère un slug unique en incluant l'ID du propriétaire
     */
    public function generateUniqueSlug(): static
    {
        if (!$this->name) {
            return $this;
        }

        $baseSlug = strtolower($this->name);
        $baseSlug = preg_replace('/[^a-z0-9\-_]/', '-', $baseSlug);
        $baseSlug = preg_replace('/-+/', '-', $baseSlug);
        $baseSlug = trim($baseSlug, '-');
        $baseSlug = substr($baseSlug, 0, 100);

        // Inclure l'ID du owner pour éviter les conflits
        if ($this->owner) {
            $this->slug = $this->owner->getId() . '-' . $baseSlug;
        } else {
            $this->slug = $baseSlug;
        }

        return $this;
    }

    /**
     * Vérifie si le projet appartient à l'utilisateur donné
     */
    public function belongsTo(User $user): bool
    {
        return $this->owner && $this->owner->getId() === $user->getId();
    }

    /**
     * Vérifie si l'utilisateur a accès au projet (propriétaire ou membre de l'équipe)
     */
    public function hasAccess(User $user): bool
    {
        if ($this->belongsTo($user)) {
            return true;
        }

        if ($this->team && $user->isTeamMember($this->team)) {
            return true;
        }

        return false;
    }

    /**
     * Vérifie si l'utilisateur peut modifier le projet
     */
    public function canEdit(User $user): bool
    {
        if ($this->belongsTo($user)) {
            return true;
        }

        if ($this->team && $user->hasTeamPermission($this->team, 'edit')) {
            return true;
        }

        return false;
    }

    /**
     * Vérifie si l'utilisateur peut gérer le projet (paramètres, alertes, etc.)
     */
    public function canManage(User $user): bool
    {
        if ($this->belongsTo($user)) {
            return true;
        }

        if ($this->team && $user->hasTeamPermission($this->team, 'manage_projects')) {
            return true;
        }

        return false;
    }

    /**
     * Retourne les statistiques avec prise en compte des limites
     */
    public function getPlanStatsSummary(): array
    {
        $stats = $this->getStatsSummary();

        if ($this->owner && $this->owner->getPlan()) {
            $plan = $this->owner->getPlan();

            $stats['current_month_errors'] = $this->currentMonthErrors;
            $stats['max_monthly_errors'] = $plan?->getMaxMonthlyErrors();
            $stats['monthly_errors_percentage'] = $plan?->getMaxMonthlyErrors() > 0
                ? round(($this->currentMonthErrors / $plan?->getMaxMonthlyErrors()) * 100, 1)
                : 0;
            $stats['can_receive_errors'] = $this->canReceiveError();
            $stats['monthly_counter_reset_at'] = $this->monthlyCounterResetAt;
        }

        return $stats;
    }

    /**
     * Génère un token webhook unique et sécurisé
     */
    public function generateWebhookToken(): static
    {
        // Générer un token de 32 bytes (64 caractères hex)
        $this->webhookToken = bin2hex(random_bytes(32));
        $this->updatedAt = new \DateTime();
        return $this;
    }

    /**
     * Régénère le token webhook (pour révoquer l'ancien)
     */
    public function regenerateWebhookToken(): static
    {
        return $this->generateWebhookToken();
    }

    /**
     * Génère un slug basé sur le nom
     */
    private function generateSlug(): void
    {
        if (!$this->name) {
            return;
        }

        $slug = strtolower($this->name);
        $slug = preg_replace('/[^a-z0-9\-_]/', '-', $slug);
        $slug = preg_replace('/-+/', '-', $slug);
        $slug = trim($slug, '-');

        $this->slug = substr($slug, 0, 120);
    }

    /**
     * Retourne l'URL complète du webhook
     */
    public function getWebhookUrl(?string $baseUrl = null): string
    {
        $baseUrl = $baseUrl ?: 'https://your-error-monitoring.com';
        return rtrim($baseUrl, '/') . '/webhook/error/' . $this->webhookToken;
    }

    /**
     * Retourne les instructions d'installation pour ce projet
     */
    public function getInstallationInstructions(?string $baseUrl = null): array
    {
        return [
            'env_variables' => [
                'ERROR_WEBHOOK_URL' => $this->getWebhookUrl($baseUrl),
                'ERROR_WEBHOOK_TOKEN' => $this->webhookToken,
                'PROJECT_NAME' => $this->slug,
                'ERROR_REPORTING_ENABLED' => 'true'
            ],
            'composer_command' => 'composer require votrenom/error-reporter',
            'config_example' => [
                'error_reporter' => [
                    'webhook_url' => '%env(ERROR_WEBHOOK_URL)%',
                    'token' => '%env(ERROR_WEBHOOK_TOKEN)%',
                    'project_name' => '%env(PROJECT_NAME)%',
                    'enabled' => '%env(bool:ERROR_REPORTING_ENABLED)%'
                ]
            ]
        ];
    }

    /**
     * Retourne un résumé des statistiques du projet
     */
    public function getStatsSummary(): array
    {
        $daysSinceCreation = $this->createdAt->diff(new \DateTime())->days + 1;

        return [
            'total_errors' => $this->totalErrors,
            'total_occurrences' => $this->totalOccurrences,
            'avg_errors_per_day' => $daysSinceCreation > 0 ? round($this->totalErrors / $daysSinceCreation, 2) : 0,
            'avg_occurrences_per_day' => $daysSinceCreation > 0 ? round($this->totalOccurrences / $daysSinceCreation, 2) : 0,
            'last_error_at' => $this->lastErrorAt,
            'days_since_last_error' => $this->lastErrorAt?->diff(new \DateTime())->days,
            'is_active' => $this->isActive,
            'created_at' => $this->createdAt
        ];
    }

    /**
     * Vérifie si le projet a eu des erreurs récemment
     */
    public function hasRecentErrors(int $days = 7): bool
    {
        if (!$this->lastErrorAt) {
            return false;
        }

        $threshold = new \DateTime("-{$days} days");
        return $this->lastErrorAt >= $threshold;
    }

    // Getters et setters pour la configuration des alertes

    public function isAlertsEnabled(): bool
    {
        return $this->alertsEnabled;
    }

    public function setAlertsEnabled(bool $alertsEnabled): static
    {
        $this->alertsEnabled = $alertsEnabled;
        $this->updatedAt = new \DateTime();
        return $this;
    }

    public function isDailySummaryEnabled(): bool
    {
        return $this->dailySummaryEnabled;
    }

    public function setDailySummaryEnabled(bool $dailySummaryEnabled): static
    {
        $this->dailySummaryEnabled = $dailySummaryEnabled;
        $this->updatedAt = new \DateTime();
        return $this;
    }

    public function getAlertThreshold(): ?int
    {
        return $this->alertThreshold;
    }

    public function setAlertThreshold(?int $alertThreshold): static
    {
        $this->alertThreshold = $alertThreshold;
        $this->updatedAt = new \DateTime();
        return $this;
    }

    public function getAlertCooldownMinutes(): ?int
    {
        return $this->alertCooldownMinutes;
    }

    public function setAlertCooldownMinutes(?int $alertCooldownMinutes): static
    {
        $this->alertCooldownMinutes = $alertCooldownMinutes;
        $this->updatedAt = new \DateTime();
        return $this;
    }

    public function getAlertStatusFilters(): array
    {
        return $this->alertStatusFilters ?? ['open'];
    }

    public function setAlertStatusFilters(?array $alertStatusFilters): static
    {
        $this->alertStatusFilters = $alertStatusFilters ?? ['open'];
        $this->updatedAt = new \DateTime();
        return $this;
    }

    public function getAlertEnvironmentFilters(): array
    {
        return $this->alertEnvironmentFilters ?? [];
    }

    public function setAlertEnvironmentFilters(?array $alertEnvironmentFilters): static
    {
        $this->alertEnvironmentFilters = $alertEnvironmentFilters ?? [];
        $this->updatedAt = new \DateTime();
        return $this;
    }

    public function getAlertsEmail(): ?string
    {
        return $this->alertsEmail ?: $this->notificationEmail;
    }

    public function setAlertsEmail(?string $alertsEmail): static
    {
        $this->alertsEmail = $alertsEmail;
        $this->updatedAt = new \DateTime();
        return $this;
    }

    // Getters et setters pour Slack
    public function isSlackAlertsEnabled(): bool
    {
        return $this->slackAlertsEnabled;
    }

    public function setSlackAlertsEnabled(bool $slackAlertsEnabled): static
    {
        $this->slackAlertsEnabled = $slackAlertsEnabled;
        $this->updatedAt = new \DateTime();
        return $this;
    }

    public function getSlackWebhookUrl(): ?string
    {
        return $this->slackWebhookUrl;
    }

    public function setSlackWebhookUrl(?string $slackWebhookUrl): static
    {
        $this->slackWebhookUrl = $slackWebhookUrl;
        $this->updatedAt = new \DateTime();
        return $this;
    }

    public function getSlackChannel(): ?string
    {
        return $this->slackChannel;
    }

    public function setSlackChannel(?string $slackChannel): static
    {
        $this->slackChannel = $slackChannel;
        $this->updatedAt = new \DateTime();
        return $this;
    }

    public function getSlackUsername(): ?string
    {
        return $this->slackUsername;
    }

    public function setSlackUsername(?string $slackUsername): static
    {
        $this->slackUsername = $slackUsername ?: 'Error Explorer';
        $this->updatedAt = new \DateTime();
        return $this;
    }

    // Getters et setters pour Discord
    public function isDiscordAlertsEnabled(): bool
    {
        return $this->discordAlertsEnabled;
    }

    public function setDiscordAlertsEnabled(bool $discordAlertsEnabled): static
    {
        $this->discordAlertsEnabled = $discordAlertsEnabled;
        $this->updatedAt = new \DateTime();
        return $this;
    }

    public function getDiscordWebhookUrl(): ?string
    {
        return $this->discordWebhookUrl;
    }

    public function setDiscordWebhookUrl(?string $discordWebhookUrl): static
    {
        $this->discordWebhookUrl = $discordWebhookUrl;
        $this->updatedAt = new \DateTime();
        return $this;
    }

    public function getDiscordChannel(): ?string
    {
        return $this->discordChannel;
    }

    public function setDiscordChannel(?string $discordChannel): static
    {
        $this->discordChannel = $discordChannel;
        $this->updatedAt = new \DateTime();
        return $this;
    }

    public function getDiscordUsername(): ?string
    {
        return $this->discordUsername;
    }

    public function setDiscordUsername(?string $discordUsername): static
    {
        $this->discordUsername = $discordUsername ?: 'Error Explorer';
        $this->updatedAt = new \DateTime();
        return $this;
    }

    // Getters et setters pour les webhooks externes
    public function isExternalWebhooksEnabled(): bool
    {
        return $this->externalWebhooksEnabled;
    }

    public function setExternalWebhooksEnabled(bool $externalWebhooksEnabled): static
    {
        $this->externalWebhooksEnabled = $externalWebhooksEnabled;
        $this->updatedAt = new \DateTime();
        return $this;
    }

    public function getExternalWebhooks(): array
    {
        return $this->externalWebhooks ?? [];
    }

    public function setExternalWebhooks(?array $externalWebhooks): static
    {
        $this->externalWebhooks = $externalWebhooks ?? [];
        $this->updatedAt = new \DateTime();
        return $this;
    }

    public function addExternalWebhook(string $name, string $url, array $events = [], array $headers = [], bool $enabled = true): static
    {
        $webhook = [
            'id' => uniqid(),
            'name' => $name,
            'url' => $url,
            'events' => $events ?: ['error.created', 'error.critical'],
            'headers' => $headers,
            'enabled' => $enabled,
            'created_at' => (new \DateTime())->format('Y-m-d H:i:s')
        ];
        
        $webhooks = $this->getExternalWebhooks();
        $webhooks[] = $webhook;
        $this->setExternalWebhooks($webhooks);
        
        return $this;
    }

    public function removeExternalWebhook(string $webhookId): static
    {
        $webhooks = $this->getExternalWebhooks();
        $webhooks = array_filter($webhooks, fn($webhook) => $webhook['id'] !== $webhookId);
        $this->setExternalWebhooks(array_values($webhooks));
        
        return $this;
    }

    public function getActiveExternalWebhooks(): array
    {
        return array_filter($this->getExternalWebhooks(), fn($webhook) => $webhook['enabled'] ?? false);
    }

    public function getExternalWebhooksForEvent(string $event): array
    {
        return array_filter(
            $this->getActiveExternalWebhooks(),
            fn($webhook) => in_array($event, $webhook['events'] ?? [])
        );
    }

    /**
     * Méthode toString pour l'affichage
     */
    public function __toString(): string
    {
        return $this->name ?: 'Projet #' . $this->id;
    }
}
