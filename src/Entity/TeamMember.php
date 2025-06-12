<?php

namespace App\Entity;

use App\Repository\TeamMemberRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TeamMemberRepository::class)]
#[ORM\Table(name: 'team_members')]
#[ORM\Index(name: 'idx_team_user', columns: ['team_id', 'user_id'])]
#[ORM\UniqueConstraint(name: 'team_user_unique', columns: ['team_id', 'user_id'])]
class TeamMember
{
    public const ROLE_OWNER = 'owner';
    public const ROLE_ADMIN = 'admin';
    public const ROLE_MEMBER = 'member';
    public const ROLE_VIEWER = 'viewer';

    public const ROLES = [
        self::ROLE_OWNER => 'Propriétaire',
        self::ROLE_ADMIN => 'Administrateur',
        self::ROLE_MEMBER => 'Membre',
        self::ROLE_VIEWER => 'Visualisateur',
    ];

    public const PERMISSIONS = [
        'view' => 'Voir les projets et erreurs',
        'edit' => 'Modifier les projets',
        'manage_members' => 'Gérer les membres',
        'manage_projects' => 'Gérer les projets',
        'manage_team' => 'Gérer l\'équipe',
        'delete_team' => 'Supprimer l\'équipe',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Team::class, inversedBy: 'members')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Team $team = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'teamMemberships')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\Column(length: 50)]
    private ?string $role = self::ROLE_MEMBER;

    #[ORM\Column]
    private ?bool $isActive = true;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $joinedAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $invitedBy = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $customPermissions = [];

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $lastActivityAt = null;

    public function __construct()
    {
        $this->joinedAt = new \DateTime();
        $this->updatedAt = new \DateTime();
        $this->lastActivityAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTeam(): ?Team
    {
        return $this->team;
    }

    public function setTeam(?Team $team): static
    {
        $this->team = $team;
        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;
        return $this;
    }

    public function getRole(): ?string
    {
        return $this->role;
    }

    public function setRole(string $role): static
    {
        if (!array_key_exists($role, self::ROLES)) {
            throw new \InvalidArgumentException('Invalid role: ' . $role);
        }

        $this->role = $role;
        $this->updatedAt = new \DateTime();
        return $this;
    }

    public function getRoleLabel(): string
    {
        return self::ROLES[$this->role] ?? $this->role;
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

    public function getJoinedAt(): ?\DateTimeInterface
    {
        return $this->joinedAt;
    }

    public function setJoinedAt(\DateTimeInterface $joinedAt): static
    {
        $this->joinedAt = $joinedAt;
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

    public function getInvitedBy(): ?User
    {
        return $this->invitedBy;
    }

    public function setInvitedBy(?User $invitedBy): static
    {
        $this->invitedBy = $invitedBy;
        return $this;
    }

    public function getCustomPermissions(): array
    {
        return $this->customPermissions ?? [];
    }

    public function setCustomPermissions(?array $customPermissions): static
    {
        $this->customPermissions = $customPermissions ?? [];
        $this->updatedAt = new \DateTime();
        return $this;
    }

    public function getLastActivityAt(): ?\DateTimeInterface
    {
        return $this->lastActivityAt;
    }

    public function setLastActivityAt(?\DateTimeInterface $lastActivityAt): static
    {
        $this->lastActivityAt = $lastActivityAt;
        return $this;
    }

    public function updateLastActivity(): static
    {
        $this->lastActivityAt = new \DateTime();
        return $this;
    }

    public function hasPermission(string $permission): bool
    {
        $rolePermissions = $this->getRolePermissions();

        if (in_array($permission, $rolePermissions)) {
            return true;
        }

        return in_array($permission, $this->customPermissions);
    }

    public function getRolePermissions(): array
    {
        return match ($this->role) {
            self::ROLE_OWNER => array_keys(self::PERMISSIONS),
            self::ROLE_ADMIN => ['view', 'edit', 'manage_members', 'manage_projects'],
            self::ROLE_MEMBER => ['view', 'edit'],
            self::ROLE_VIEWER => ['view'],
            default => [],
        };
    }

    public function getAllPermissions(): array
    {
        if (null === $this->customPermissions) {
            $this->customPermissions = [];
        }

        return array_unique(array_merge($this->getRolePermissions(), $this->customPermissions));
    }

    public function addCustomPermission(string $permission): static
    {
        if (!array_key_exists($permission, self::PERMISSIONS)) {
            throw new \InvalidArgumentException('Invalid permission: ' . $permission);
        }

        if (!in_array($permission, $this->customPermissions)) {
            $this->customPermissions[] = $permission;
            $this->updatedAt = new \DateTime();
        }

        return $this;
    }

    public function removeCustomPermission(string $permission): static
    {
        $key = array_search($permission, $this->customPermissions);
        if ($key !== false) {
            unset($this->customPermissions[$key]);
            $this->customPermissions = array_values($this->customPermissions);
            $this->updatedAt = new \DateTime();
        }

        return $this;
    }

    public function isOwner(): bool
    {
        return $this->role === self::ROLE_OWNER;
    }

    public function isAdmin(): bool
    {
        return in_array($this->role, [self::ROLE_OWNER, self::ROLE_ADMIN]);
    }

    public function canManageTeam(): bool
    {
        return $this->hasPermission('manage_team');
    }

    public function canManageMembers(): bool
    {
        return $this->hasPermission('manage_members');
    }

    public function canManageProjects(): bool
    {
        return $this->hasPermission('manage_projects');
    }

    public function canEdit(): bool
    {
        return $this->hasPermission('edit');
    }

    public function canView(): bool
    {
        return $this->hasPermission('view');
    }

    public function getDaysSinceJoined(): int
    {
        return $this->joinedAt->diff(new \DateTime())->days;
    }

    public function getDaysSinceLastActivity(): ?int
    {
        return $this->lastActivityAt?->diff(new \DateTime())->days;
    }

    public function isRecentlyActive(int $days = 7): bool
    {
        if (!$this->lastActivityAt) {
            return false;
        }

        $threshold = new \DateTime("-{$days} days");
        return $this->lastActivityAt >= $threshold;
    }

    public function __toString(): string
    {
        return sprintf(
            '%s (%s) dans %s',
            $this->user?->getFullName() ?? 'Utilisateur inconnu',
            $this->getRoleLabel(),
            $this->team?->getName() ?? 'Équipe inconnue'
        );
    }
}
