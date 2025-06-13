<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'users')]
#[UniqueEntity(fields: ['email'], message: 'Il existe déjà un compte avec cet email')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180, unique: true)]
    #[Assert\NotBlank(message: 'L\'adresse email est requise')]
    #[Assert\Email(message: 'Veuillez saisir une adresse email valide')]
    private ?string $email = null;

    #[ORM\Column]
    private array $roles = [];

    #[ORM\Column]
    private ?string $password = null;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank(message: 'Le prénom est requis')]
    #[Assert\Length(
        min: 2,
        max: 50,
        minMessage: 'Le prénom doit contenir au moins {{ limit }} caractères',
        maxMessage: 'Le prénom ne peut pas dépasser {{ limit }} caractères'
    )]
    #[Assert\Regex(
        pattern: '/^[a-zA-ZÀ-ÿ\s\-\']+$/',
        message: 'Le prénom contient des caractères non valides'
    )]
    private ?string $firstName = null;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank(message: 'Le nom est requis')]
    #[Assert\Length(
        min: 2,
        max: 50,
        minMessage: 'Le nom doit contenir au moins {{ limit }} caractères',
        maxMessage: 'Le nom ne peut pas dépasser {{ limit }} caractères'
    )]
    #[Assert\Regex(
        pattern: '/^[a-zA-ZÀ-ÿ\s\-\']+$/',
        message: 'Le nom contient des caractères non valides'
    )]
    private ?string $lastName = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Assert\Length(
        max: 100,
        maxMessage: 'Le nom de l\'entreprise ne peut pas dépasser {{ limit }} caractères'
    )]
    private ?string $company = null;

    #[ORM\ManyToOne(targetEntity: Plan::class, fetch: 'EAGER')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Plan $plan = null;

    #[ORM\Column]
    private ?bool $isVerified = false;

    #[ORM\Column]
    private ?bool $isActive = true;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $lastLoginAt = null;

    #[ORM\OneToMany(targetEntity: Project::class, mappedBy: 'owner', orphanRemoval: true)]
    private Collection $projects;

    #[ORM\OneToMany(targetEntity: Team::class, mappedBy: 'owner', orphanRemoval: true)]
    private Collection $ownedTeams;

    #[ORM\OneToMany(targetEntity: TeamMember::class, mappedBy: 'user', orphanRemoval: true)]
    private Collection $teamMemberships;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $planExpiresAt = null;

    #[ORM\Column]
    private ?int $currentProjectsCount = 0;

    #[ORM\Column]
    private ?int $currentMonthlyErrors = 0;

    #[ORM\Column]
    private ?int $currentMonthlyAiSuggestions = 0;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private array $settings = [];

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $emailVerificationToken = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $emailVerifiedAt = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $passwordResetToken = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $passwordResetRequestedAt = null;


    // Préférences d'alertes
    #[ORM\Column]
    private bool $emailAlertsEnabled = true;

    #[ORM\Column]
    private bool $criticalAlertsEnabled = true;

    #[ORM\Column]
    private bool $weeklyReportsEnabled = false;

    public function __construct()
    {
        $this->projects = new ArrayCollection();
        $this->ownedTeams = new ArrayCollection();
        $this->teamMemberships = new ArrayCollection();
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): static
    {
        $this->id = $id;
        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;
        return $this;
    }

    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    public function setRoles(array $roles): static
    {
        $this->roles = $roles;
        return $this;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;
        return $this;
    }

    public function eraseCredentials(): void
    {
        // Si vous stockez des données temporaires sensibles sur l'utilisateur, effacez-les ici
    }

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): static
    {
        $this->firstName = $firstName;
        return $this;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function setLastName(string $lastName): static
    {
        $this->lastName = $lastName;
        return $this;
    }

    public function getFullName(): string
    {
        return trim($this->firstName . ' ' . $this->lastName);
    }

    public function getCompany(): ?string
    {
        return $this->company;
    }

    public function setCompany(?string $company): static
    {
        $this->company = $company;
        return $this;
    }

    public function getPlan(): ?Plan
    {
        return $this->plan;
    }

    public function setPlan(?Plan $plan): static
    {
        $this->plan = $plan;
        return $this;
    }

    public function getPlanName(): ?string
    {
        return $this->plan?->getName();
    }

    public function hasAdvancedAnalytics(): bool
    {
        return $this->plan?->hasAdvancedAnalytics() ?? false;
    }

    public function isVerified(): bool
    {
        return $this->isVerified;
    }

    public function setIsVerified(bool $isVerified): static
    {
        $this->isVerified = $isVerified;
        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;
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

    public function getLastLoginAt(): ?\DateTimeInterface
    {
        return $this->lastLoginAt;
    }

    public function setLastLoginAt(?\DateTimeInterface $lastLoginAt): static
    {
        $this->lastLoginAt = $lastLoginAt;
        return $this;
    }

    /**
     * @return Collection<int, Project>
     */
    public function getProjects(): Collection
    {
        return $this->projects;
    }

    public function addProject(Project $project): static
    {
        if (!$this->projects->contains($project)) {
            $this->projects->add($project);
            $project->setOwner($this);
        }

        return $this;
    }

    public function removeProject(Project $project): static
    {
        if ($this->projects->removeElement($project) && $project->getOwner() === $this) {
            $project->setOwner(null);
        }

        return $this;
    }

    public function getPlanExpiresAt(): ?\DateTimeInterface
    {
        return $this->planExpiresAt;
    }

    public function setPlanExpiresAt(?\DateTimeInterface $planExpiresAt): static
    {
        $this->planExpiresAt = $planExpiresAt;
        return $this;
    }

    public function getCurrentProjectsCount(): ?int
    {
        return $this->currentProjectsCount;
    }

    public function setCurrentProjectsCount(int $currentProjectsCount): static
    {
        $this->currentProjectsCount = $currentProjectsCount;
        return $this;
    }

    public function getCurrentMonthlyErrors(): ?int
    {
        return $this->currentMonthlyErrors;
    }

    public function setCurrentMonthlyErrors(int $currentMonthlyErrors): static
    {
        $this->currentMonthlyErrors = $currentMonthlyErrors;
        return $this;
    }

    public function getCurrentMonthlyAiSuggestions(): ?int
    {
        return $this->currentMonthlyAiSuggestions;
    }

    public function setCurrentMonthlyAiSuggestions(int $currentMonthlyAiSuggestions): static
    {
        $this->currentMonthlyAiSuggestions = $currentMonthlyAiSuggestions;
        return $this;
    }

    public function getSettings(): array
    {
        return $this->settings;
    }

    public function setSettings(?array $settings): static
    {
        $this->settings = $settings ?? [];
        return $this;
    }

    // Méthodes de vérification des limites
    public function canCreateProject(): bool
    {
        if (!$this->plan) {
            return false;
        }

        return $this->plan->getMaxProjects() === -1 ||
            $this->currentProjectsCount < $this->plan->getMaxProjects();
    }

    public function canReceiveError(): bool
    {
        if (!$this->plan) {
            return false;
        }

        return $this->plan->getMaxMonthlyErrors() === -1 ||
            $this->currentMonthlyErrors < $this->plan->getMaxMonthlyErrors();
    }

    public function isPlanExpired(): bool
    {
        return $this->planExpiresAt && $this->planExpiresAt < new \DateTime();
    }

    public function incrementProjectsCount(): static
    {
        $this->currentProjectsCount++;
        return $this;
    }

    public function decrementProjectsCount(): static
    {
        $this->currentProjectsCount = max(0, $this->currentProjectsCount - 1);
        return $this;
    }

    public function incrementMonthlyErrors(int $count = 1): static
    {
        $this->currentMonthlyErrors += $count;
        return $this;
    }

    public function resetMonthlyErrors(): static
    {
        $this->currentMonthlyErrors = 0;
        return $this;
    }

    public function canUseAiSuggestions(): bool
    {
        if (!$this->plan || !$this->plan->hasAiSuggestions()) {
            return false;
        }

        $maxAiSuggestions = $this->plan->getMaxMonthlyAiSuggestions();
        return $maxAiSuggestions === -1 ||
               $this->currentMonthlyAiSuggestions < $maxAiSuggestions;
    }

    public function incrementAiSuggestions(int $count = 1): static
    {
        $this->currentMonthlyAiSuggestions += $count;
        return $this;
    }

    public function resetMonthlyAiSuggestions(): static
    {
        $this->currentMonthlyAiSuggestions = 0;
        return $this;
    }

    public function getAvailableAiProvider(): ?string
    {
        if (!$this->plan || !$this->plan->hasAiSuggestions()) {
            return null;
        }

        return $this->plan->getAiProvider();
    }

    // Getters et setters pour les préférences d'alertes

    public function isEmailAlertsEnabled(): bool
    {
        return $this->emailAlertsEnabled;
    }

    public function setEmailAlertsEnabled(bool $emailAlertsEnabled): static
    {
        $this->emailAlertsEnabled = $emailAlertsEnabled;
        $this->updatedAt = new \DateTime();
        return $this;
    }

    public function isCriticalAlertsEnabled(): bool
    {
        return $this->criticalAlertsEnabled;
    }

    public function setCriticalAlertsEnabled(bool $criticalAlertsEnabled): static
    {
        $this->criticalAlertsEnabled = $criticalAlertsEnabled;
        $this->updatedAt = new \DateTime();
        return $this;
    }

    public function isWeeklyReportsEnabled(): bool
    {
        return $this->weeklyReportsEnabled;
    }

    public function setWeeklyReportsEnabled(bool $weeklyReportsEnabled): static
    {
        $this->weeklyReportsEnabled = $weeklyReportsEnabled;
        $this->updatedAt = new \DateTime();
        return $this;
    }

    /**
     * @return Collection<int, Team>
     */
    public function getOwnedTeams(): Collection
    {
        return $this->ownedTeams;
    }

    public function addOwnedTeam(Team $team): static
    {
        if (!$this->ownedTeams->contains($team)) {
            $this->ownedTeams->add($team);
            $team->setOwner($this);
        }

        return $this;
    }

    public function removeOwnedTeam(Team $team): static
    {
        if ($this->ownedTeams->removeElement($team)) {
            if ($team->getOwner() === $this) {
                $team->setOwner(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, TeamMember>
     */
    public function getTeamMemberships(): Collection
    {
        return $this->teamMemberships;
    }

    public function addTeamMembership(TeamMember $teamMembership): static
    {
        if (!$this->teamMemberships->contains($teamMembership)) {
            $this->teamMemberships->add($teamMembership);
            $teamMembership->setUser($this);
        }

        return $this;
    }

    public function removeTeamMembership(TeamMember $teamMembership): static
    {
        if ($this->teamMemberships->removeElement($teamMembership)) {
            if ($teamMembership->getUser() === $this) {
                $teamMembership->setUser(null);
            }
        }

        return $this;
    }

    public function getAllTeams(): array
    {
        $teams = [];

        foreach ($this->ownedTeams as $team) {
            $teams[] = $team;
        }

        foreach ($this->teamMemberships as $membership) {
            if ($membership->isActive() && $membership->getTeam()) {
                $teams[] = $membership->getTeam();
            }
        }

        return array_unique($teams, SORT_REGULAR);
    }

    public function isTeamMember(Team $team): bool
    {
        if ($this->ownedTeams->contains($team)) {
            return true;
        }

        foreach ($this->teamMemberships as $membership) {
            if ($membership->getTeam() === $team && $membership->isActive()) {
                return true;
            }
        }

        return false;
    }

    public function getTeamRole(Team $team): ?string
    {
        if ($this->ownedTeams->contains($team)) {
            return 'owner';
        }

        foreach ($this->teamMemberships as $membership) {
            if ($membership->getTeam() === $team && $membership->isActive()) {
                return $membership->getRole();
            }
        }

        return null;
    }

    public function hasTeamPermission(Team $team, string $permission): bool
    {
        if ($this->ownedTeams->contains($team)) {
            return true;
        }

        foreach ($this->teamMemberships as $membership) {
            if ($membership->getTeam() === $team && $membership->isActive()) {
                return $membership->hasPermission($permission);
            }
        }

        return false;
    }

    public function getEmailVerificationToken(): ?string
    {
        return $this->emailVerificationToken;
    }

    public function setEmailVerificationToken(?string $emailVerificationToken): static
    {
        $this->emailVerificationToken = $emailVerificationToken;
        return $this;
    }

    public function getEmailVerifiedAt(): ?\DateTimeImmutable
    {
        return $this->emailVerifiedAt;
    }

    public function setEmailVerifiedAt(?\DateTimeImmutable $emailVerifiedAt): static
    {
        $this->emailVerifiedAt = $emailVerifiedAt;
        return $this;
    }

    public function getPasswordResetToken(): ?string
    {
        return $this->passwordResetToken;
    }

    public function setPasswordResetToken(?string $passwordResetToken): static
    {
        $this->passwordResetToken = $passwordResetToken;
        return $this;
    }

    public function getPasswordResetRequestedAt(): ?\DateTimeImmutable
    {
        return $this->passwordResetRequestedAt;
    }

    public function setPasswordResetRequestedAt(?\DateTimeImmutable $passwordResetRequestedAt): static
    {
        $this->passwordResetRequestedAt = $passwordResetRequestedAt;
        return $this;
    }

    /**
     * Retourne les initiales de l'utilisateur
     */
    public function getInitials(): string
    {
        $firstInitial = $this->firstName ? strtoupper($this->firstName[0]) : '';
        $lastInitial = $this->lastName ? strtoupper($this->lastName[0]) : '';
        return $firstInitial . $lastInitial;
    }

    /**
     * Vérifie si l'utilisateur a un plan gratuit
     */
    public function hasFreePlan(): bool
    {
        return $this->plan && $this->plan->isFree();
    }

    /**
     * Génère un token de vérification d'email
     */
    public function generateEmailVerificationToken(): string
    {
        $this->emailVerificationToken = bin2hex(random_bytes(32));
        $this->updatedAt = new \DateTimeImmutable();
        return $this->emailVerificationToken;
    }

    /**
     * Génère un token de réinitialisation de mot de passe
     */
    public function generatePasswordResetToken(): string
    {
        $this->passwordResetToken = bin2hex(random_bytes(32));
        $this->passwordResetRequestedAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        return $this->passwordResetToken;
    }

    /**
     * Vérifie si le token de réinitialisation de mot de passe est valide
     */
    public function isPasswordResetTokenValid(int $maxAgeInHours = 24): bool
    {
        if (!$this->passwordResetToken || !$this->passwordResetRequestedAt) {
            return false;
        }

        $expirationDate = $this->passwordResetRequestedAt->modify("+{$maxAgeInHours} hours");
        return new \DateTimeImmutable() < $expirationDate;
    }

    /**
     * Marque l'email comme vérifié
     */
    public function markEmailAsVerified(): static
    {
        $this->isVerified = true;
        $this->emailVerifiedAt = new \DateTimeImmutable();
        $this->emailVerificationToken = null;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    /**
     * Met à jour la date de dernière connexion
     */
    public function updateLastLoginAt(): static
    {
        $this->lastLoginAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function hasRecentLogin(int $hours = 24): bool
    {
        if (!$this->lastLoginAt) {
            return false;
        }

        $threshold = new \DateTimeImmutable("-{$hours} hours");
        return $this->lastLoginAt >= $threshold;
    }

    public function isFirstLogin(): bool
    {
        return $this->lastLoginAt === null;
    }

    /**
     * Réinitialise le token de mot de passe
     */
    public function clearPasswordResetToken(): static
    {
        $this->passwordResetToken = null;
        $this->passwordResetRequestedAt = null;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    /**
     * Retourne une représentation string de l'utilisateur
     */
    public function __toString(): string
    {
        return $this->getFullName() ?: $this->email ?: 'Utilisateur';
    }
}
