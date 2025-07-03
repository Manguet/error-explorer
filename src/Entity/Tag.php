<?php

namespace App\Entity;

use App\Repository\TagRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: TagRepository::class)]
#[ORM\Table(name: 'tags')]
#[ORM\Index(name: 'idx_tag_owner', columns: ['owner_id'])]
#[ORM\Index(name: 'idx_tag_slug', columns: ['slug'])]
#[ORM\UniqueConstraint(name: 'unique_tag_per_user', columns: ['slug', 'owner_id'])]
class Tag
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank(message: 'Le nom du tag ne peut pas être vide')]
    #[Assert\Length(
        min: 2,
        max: 50,
        minMessage: 'Le nom du tag doit faire au moins {{ limit }} caractères',
        maxMessage: 'Le nom du tag ne peut pas dépasser {{ limit }} caractères'
    )]
    #[Assert\Regex(
        pattern: '/^[a-zA-Z0-9\s\-_àâäéèêëïîôöùûüÿç]+$/u',
        message: 'Le nom du tag ne peut contenir que des lettres, chiffres, espaces et tirets'
    )]
    private ?string $name = null;

    #[ORM\Column(length: 60)]
    private ?string $slug = null;

    #[ORM\Column(length: 7)]
    #[Assert\Regex(
        pattern: '/^#[0-9a-fA-F]{6}$/',
        message: 'La couleur doit être un code hexadécimal valide (ex: #FF5733)'
    )]
    private ?string $color = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(
        max: 255,
        maxMessage: 'La description ne peut pas dépasser {{ limit }} caractères'
    )]
    private ?string $description = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $owner = null;

    #[ORM\ManyToMany(targetEntity: ErrorGroup::class, mappedBy: 'tags')]
    private Collection $errorGroups;

    #[ORM\Column]
    private int $usageCount = 0;

    #[ORM\Column]
    private bool $isSystem = false;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $updatedAt = null;

    public function __construct()
    {
        $this->errorGroups = new ArrayCollection();
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
        $this->name = trim($name);
        $this->updateSlug();
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

    public function getColor(): ?string
    {
        return $this->color;
    }

    public function setColor(string $color): static
    {
        $this->color = $color;
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

    public function getOwner(): ?User
    {
        return $this->owner;
    }

    public function setOwner(?User $owner): static
    {
        $this->owner = $owner;
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
            $errorGroup->addTag($this);
        }

        return $this;
    }

    public function removeErrorGroup(ErrorGroup $errorGroup): static
    {
        if ($this->errorGroups->removeElement($errorGroup)) {
            $errorGroup->removeTag($this);
        }

        return $this;
    }

    public function getUsageCount(): int
    {
        return $this->usageCount;
    }

    public function setUsageCount(int $usageCount): static
    {
        $this->usageCount = $usageCount;
        return $this;
    }

    public function incrementUsageCount(): static
    {
        $this->usageCount++;
        return $this;
    }

    public function decrementUsageCount(): static
    {
        if ($this->usageCount > 0) {
            $this->usageCount--;
        }
        return $this;
    }

    public function isSystem(): bool
    {
        return $this->isSystem;
    }

    public function setIsSystem(bool $isSystem): static
    {
        $this->isSystem = $isSystem;
        return $this;
    }

    /**
     * Génère automatiquement le slug basé sur le nom
     */
    private function updateSlug(): void
    {
        if ($this->name) {
            $slug = strtolower(trim($this->name));
            $slug = preg_replace('/[^a-z0-9\s\-_]/', '', $slug);
            $slug = preg_replace('/[\s\-_]+/', '-', $slug);
            $slug = trim($slug, '-');
            $this->slug = $slug;
        }
    }

    /**
     * Génère automatiquement une couleur basée sur le nom du tag
     */
    public function generateColorFromName(): string
    {
        $colors = [
            '#3B82F6', // blue
            '#10B981', // emerald
            '#F59E0B', // amber
            '#EF4444', // red
            '#8B5CF6', // violet
            '#06B6D4', // cyan
            '#84CC16', // lime
            '#F97316', // orange
            '#EC4899', // pink
            '#6366F1', // indigo
            '#14B8A6', // teal
            '#A855F7', // purple
            '#22C55E', // green
            '#F43F5E', // rose
        ];

        $hash = crc32($this->name ?? 'default');
        $index = abs($hash) % count($colors);

        return $colors[$index];
    }

    /**
     * Initialise le tag avec des valeurs par défaut
     */
    public function initialize(): static
    {
        if (!$this->color) {
            $this->color = $this->generateColorFromName();
        }

        if (!$this->slug) {
            $this->updateSlug();
        }

        return $this;
    }

    /**
     * Vérifie si l'utilisateur peut modifier ce tag
     */
    public function canEdit(?User $user): bool
    {
        if (!$user) {
            return false;
        }

        // Les tags système ne peuvent pas être modifiés
        if ($this->isSystem) {
            return false;
        }

        // Seul le propriétaire peut modifier le tag
        return $this->owner && $this->owner->getId() === $user->getId();
    }

    /**
     * Vérifie si l'utilisateur peut supprimer ce tag
     */
    public function canDelete(?User $user): bool
    {
        if (!$user) {
            return false;
        }

        // Les tags système ne peuvent pas être supprimés
        if ($this->isSystem) {
            return false;
        }

        // Seul le propriétaire peut supprimer le tag
        return $this->owner && $this->owner->getId() === $user->getId();
    }

    /**
     * Retourne une représentation texte du tag
     */
    public function __toString(): string
    {
        return $this->name ?? '';
    }

    /**
     * Retourne les données du tag pour l'API
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'color' => $this->color,
            'description' => $this->description,
            'usage_count' => $this->usageCount,
            'is_system' => $this->isSystem,
            'created_at' => $this->createdAt?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt?->format('Y-m-d H:i:s')
        ];
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
}
