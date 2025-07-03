<?php

namespace App\Entity;

use App\Repository\ErrorCommentRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ErrorCommentRepository::class)]
#[ORM\Table(name: 'error_comments')]
#[ORM\HasLifecycleCallbacks]
class ErrorComment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: ErrorGroup::class)]
    #[ORM\JoinColumn(name: 'error_group_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?ErrorGroup $errorGroup = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'author_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?User $author = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: 'Le commentaire ne peut pas être vide')]
    #[Assert\Length(
        min: 1,
        max: 10000,
        minMessage: 'Le commentaire doit contenir au moins {{ limit }} caractère',
        maxMessage: 'Le commentaire ne peut pas dépasser {{ limit }} caractères'
    )]
    private ?string $content = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $attachments = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    private bool $isEdited = false;

    #[ORM\ManyToOne(targetEntity: ErrorComment::class)]
    #[ORM\JoinColumn(name: 'parent_id', referencedColumnName: 'id', nullable: true, onDelete: 'CASCADE')]
    private ?ErrorComment $parent = null;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    private bool $isInternal = false;

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

    public function getAuthor(): ?User
    {
        return $this->author;
    }

    public function setAuthor(?User $author): static
    {
        $this->author = $author;
        return $this;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(string $content): static
    {
        $this->content = $content;
        return $this;
    }

    public function getAttachments(): ?array
    {
        return $this->attachments;
    }

    public function setAttachments(?array $attachments): static
    {
        $this->attachments = $attachments;
        return $this;
    }

    public function addAttachment(array $attachment): static
    {
        if ($this->attachments === null) {
            $this->attachments = [];
        }
        $this->attachments[] = $attachment;
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

    public function setUpdatedAt(?\DateTimeInterface $updatedAt): static
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public function isEdited(): bool
    {
        return $this->isEdited;
    }

    public function setIsEdited(bool $isEdited): static
    {
        $this->isEdited = $isEdited;
        return $this;
    }

    public function getParent(): ?ErrorComment
    {
        return $this->parent;
    }

    public function setParent(?ErrorComment $parent): static
    {
        $this->parent = $parent;
        return $this;
    }

    public function isInternal(): bool
    {
        return $this->isInternal;
    }

    public function setIsInternal(bool $isInternal): static
    {
        $this->isInternal = $isInternal;
        return $this;
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->createdAt = new \DateTime();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTime();
        $this->isEdited = true;
    }

    /**
     * Retourne la durée écoulée depuis la création du commentaire
     */
    public function getTimeAgo(): string
    {
        $now = new \DateTime();
        $diff = $now->diff($this->createdAt);

        if ($diff->days > 365) {
            $years = floor($diff->days / 365);
            return $years > 1 ? "{$years} ans" : "1 an";
        } elseif ($diff->days > 30) {
            $months = floor($diff->days / 30);
            return $months > 1 ? "{$months} mois" : "1 mois";
        } elseif ($diff->days > 0) {
            return $diff->days > 1 ? "{$diff->days} jours" : "1 jour";
        } elseif ($diff->h > 0) {
            return $diff->h > 1 ? "{$diff->h} heures" : "1 heure";
        } elseif ($diff->i > 0) {
            return $diff->i > 1 ? "{$diff->i} minutes" : "1 minute";
        } else {
            return "À l'instant";
        }
    }

    /**
     * Vérifie si l'utilisateur peut modifier ce commentaire
     */
    public function canEdit(User $user): bool
    {
        // L'auteur peut toujours modifier son commentaire
        if ($this->author->getId() === $user->getId()) {
            return true;
        }

        // Le propriétaire du projet peut modifier tous les commentaires
        if ($this->errorGroup->getProjectEntity() && 
            $this->errorGroup->getProjectEntity()->getOwner()->getId() === $user->getId()) {
            return true;
        }

        return false;
    }

    /**
     * Vérifie si l'utilisateur peut supprimer ce commentaire
     */
    public function canDelete(User $user): bool
    {
        return $this->canEdit($user);
    }

    /**
     * Retourne les pièces jointes d'images uniquement
     */
    public function getImageAttachments(): array
    {
        if (!$this->attachments) {
            return [];
        }

        return array_filter($this->attachments, function($attachment) {
            return isset($attachment['type']) && $attachment['type'] === 'image';
        });
    }

    /**
     * Retourne les autres types de pièces jointes
     */
    public function getFileAttachments(): array
    {
        if (!$this->attachments) {
            return [];
        }

        return array_filter($this->attachments, function($attachment) {
            return isset($attachment['type']) && $attachment['type'] !== 'image';
        });
    }
}