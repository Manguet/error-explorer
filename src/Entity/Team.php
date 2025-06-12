<?php

namespace App\Entity;

use App\Repository\TeamRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TeamRepository::class)]
#[ORM\Table(name: 'teams')]
#[ORM\Index(name: 'idx_slug', columns: ['slug'])]
#[ORM\Index(name: 'idx_owner', columns: ['owner_id'])]
class Team
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private ?string $name = null;

    #[ORM\Column(length: 120, unique: true)]
    private ?string $slug = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'ownedTeams')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $owner = null;

    #[ORM\Column]
    private ?bool $isActive = true;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\OneToMany(targetEntity: TeamMember::class, mappedBy: 'team', orphanRemoval: true)]
    private Collection $members;

    #[ORM\OneToMany(targetEntity: Project::class, mappedBy: 'team')]
    private Collection $projects;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $settings = [];

    #[ORM\Column]
    private ?int $maxMembers = 10;

    #[ORM\Column]
    private ?int $maxProjects = 5;

    public function __construct()
    {
        $this->members = new ArrayCollection();
        $this->projects = new ArrayCollection();
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

    public function getOwner(): ?User
    {
        return $this->owner;
    }

    public function setOwner(?User $owner): static
    {
        $this->owner = $owner;
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

    /**
     * @return Collection<int, TeamMember>
     */
    public function getMembers(): Collection
    {
        return $this->members;
    }

    public function addMember(TeamMember $member): static
    {
        if (!$this->members->contains($member)) {
            $this->members->add($member);
            $member->setTeam($this);
        }

        return $this;
    }

    public function removeMember(TeamMember $member): static
    {
        if ($this->members->removeElement($member)) {
            if ($member->getTeam() === $this) {
                $member->setTeam(null);
            }
        }

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
            $project->setTeam($this);
        }

        return $this;
    }

    public function removeProject(Project $project): static
    {
        if ($this->projects->removeElement($project)) {
            if ($project->getTeam() === $this) {
                $project->setTeam(null);
            }
        }

        return $this;
    }

    public function getSettings(): array
    {
        return $this->settings ?? [];
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

    public function getMaxMembers(): ?int
    {
        return $this->maxMembers;
    }

    public function setMaxMembers(int $maxMembers): static
    {
        $this->maxMembers = $maxMembers;
        $this->updatedAt = new \DateTime();
        return $this;
    }

    public function getMaxProjects(): ?int
    {
        return $this->maxProjects;
    }

    public function setMaxProjects(int $maxProjects): static
    {
        $this->maxProjects = $maxProjects;
        $this->updatedAt = new \DateTime();
        return $this;
    }

    public function canAddMember(): bool
    {
        return $this->members->count() < $this->maxMembers;
    }

    public function canAddProject(): bool
    {
        return $this->projects->count() < $this->maxProjects;
    }

    public function getMembersCount(): int
    {
        return $this->members->count();
    }

    public function getProjectsCount(): int
    {
        return $this->projects->count();
    }

    public function isMember(User $user): bool
    {
        if ($this->owner && $this->owner->getId() === $user->getId()) {
            return true;
        }

        foreach ($this->members as $member) {
            if ($member->getUser() && $member->getUser()->getId() === $user->getId()) {
                return true;
            }
        }

        return false;
    }

    public function getMemberRole(User $user): ?string
    {
        if ($this->owner && $this->owner->getId() === $user->getId()) {
            return 'owner';
        }

        foreach ($this->members as $member) {
            if ($member->getUser() && $member->getUser()->getId() === $user->getId()) {
                return $member->getRole();
            }
        }

        return null;
    }

    public function hasPermission(User $user, string $permission): bool
    {
        $role = $this->getMemberRole($user);

        if (!$role) {
            return false;
        }

        return match ($role) {
            'owner' => true,
            'admin' => in_array($permission, ['view', 'edit', 'manage_members', 'manage_projects']),
            'member' => in_array($permission, ['view', 'edit']),
            'viewer' => in_array($permission, ['view']),
            default => false,
        };
    }

    private function generateSlug(): void
    {
        if (!$this->name) {
            return;
        }

        $slug = strtolower($this->name);
        $slug = preg_replace('/[^a-z0-9\-_]/', '-', $slug);
        $slug = preg_replace('/-+/', '-', $slug);
        $slug = trim($slug, '-');

        if ($this->owner) {
            $this->slug = $this->owner->getId() . '-' . substr($slug, 0, 100);
        } else {
            $this->slug = substr($slug, 0, 120);
        }
    }

    public function __toString(): string
    {
        return $this->name ?: 'Équipe #' . $this->id;
    }
}
