<?php

namespace App\Entity;

use App\Repository\NotificationRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: NotificationRepository::class)]
#[ORM\Table(name: 'notifications')]
#[ORM\Index(name: 'idx_notification_type', columns: ['type'])]
#[ORM\Index(name: 'idx_notification_created_at', columns: ['created_at'])]
#[ORM\Index(name: 'idx_notification_expires_at', columns: ['expires_at'])]
#[ORM\Index(name: 'idx_notification_is_read', columns: ['is_read'])]
#[ORM\Index(name: 'idx_notification_priority', columns: ['priority'])]
#[ORM\Index(name: 'idx_notification_author_date', columns: ['author_id', 'created_at'])]
#[ORM\Index(name: 'idx_notification_target_read', columns: ['target_user_id', 'is_read'])]
class Notification
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50)]
    private string $type = 'info';

    #[ORM\Column(type: Types::TEXT)]
    private string $title = '';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'author_id', referencedColumnName: 'id', onDelete: 'SET NULL')]
    private ?User $author = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'target_user_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private ?User $targetUser = null;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $visibleToAdmin = false;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $visibleToUserDashboard = false;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $visibleToSpecificUser = false;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $customAudiences = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $data = null;

    #[ORM\Column(length: 20)]
    private string $priority = 'normal'; // low, normal, high, urgent

    #[ORM\Column]
    private bool $isRead = false;

    #[ORM\Column]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $expiresAt = null;

    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $readAt = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $actionUrl = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $actionLabel = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $icon = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $color = null;

    public function __construct()
    {
        $this->createdAt = new DateTimeImmutable();
    }

    // Getters and Setters
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;
        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;
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

    public function getAuthor(): ?User
    {
        return $this->author;
    }

    public function setAuthor(?User $author): static
    {
        $this->author = $author;
        return $this;
    }

    public function getTargetUser(): ?User
    {
        return $this->targetUser;
    }

    public function setTargetUser(?User $targetUser): static
    {
        $this->targetUser = $targetUser;
        return $this;
    }

    public function getAudience(): array
    {
        $audiences = [];

        if ($this->visibleToAdmin) {
            $audiences[] = 'admin';
        }

        if ($this->visibleToUserDashboard) {
            $audiences[] = 'user_dashboard';
        }

        if ($this->visibleToSpecificUser) {
            $audiences[] = 'specific_user';
        }

        if ($this->customAudiences) {
            $audiences = array_merge($audiences, $this->customAudiences);
        }

        return array_unique($audiences);
    }

    public function setAudience(array $audiences): static
    {
        $this->visibleToAdmin = false;
        $this->visibleToUserDashboard = false;
        $this->visibleToSpecificUser = false;
        $this->customAudiences = null;

        $customAudiences = [];

        foreach ($audiences as $audience) {
            switch ($audience) {
                case 'admin':
                    $this->visibleToAdmin = true;
                    break;
                case 'user_dashboard':
                    $this->visibleToUserDashboard = true;
                    break;
                case 'specific_user':
                    $this->visibleToSpecificUser = true;
                    break;
                default:
                    $customAudiences[] = $audience;
            }
        }

        if (!empty($customAudiences)) {
            $this->customAudiences = $customAudiences;
        }

        return $this;
    }

    public function hasAudience(string $audience): bool
    {
        return in_array($audience, $this->getAudience(), true);
    }

    public function addAudience(string $audience): static
    {
        if (!$this->hasAudience($audience)) {
            $currentAudiences = $this->getAudience();
            $currentAudiences[] = $audience;
            $this->setAudience($currentAudiences);
        }
        return $this;
    }

    public function removeAudience(string $audience): static
    {
        $currentAudiences = $this->getAudience();
        $currentAudiences = array_filter($currentAudiences, static fn($a) => $a !== $audience);
        $this->setAudience($currentAudiences);
        return $this;
    }

    public function isVisibleToAdmin(): bool
    {
        return $this->visibleToAdmin;
    }

    public function setVisibleToAdmin(bool $visibleToAdmin): static
    {
        $this->visibleToAdmin = $visibleToAdmin;
        return $this;
    }

    public function isVisibleToUserDashboard(): bool
    {
        return $this->visibleToUserDashboard;
    }

    public function setVisibleToUserDashboard(bool $visibleToUserDashboard): static
    {
        $this->visibleToUserDashboard = $visibleToUserDashboard;
        return $this;
    }

    public function isVisibleToSpecificUser(): bool
    {
        return $this->visibleToSpecificUser;
    }

    public function setVisibleToSpecificUser(bool $visibleToSpecificUser): static
    {
        $this->visibleToSpecificUser = $visibleToSpecificUser;
        return $this;
    }

    public function getCustomAudiences(): ?array
    {
        return $this->customAudiences;
    }

    public function setCustomAudiences(?array $customAudiences): static
    {
        $this->customAudiences = $customAudiences;
        return $this;
    }

    public function getData(): ?array
    {
        return $this->data;
    }

    public function setData(?array $data): static
    {
        $this->data = $data;
        return $this;
    }

    public function getPriority(): string
    {
        return $this->priority;
    }

    public function setPriority(string $priority): static
    {
        $this->priority = $priority;
        return $this;
    }

    public function isRead(): bool
    {
        return $this->isRead;
    }

    public function setIsRead(bool $isRead): static
    {
        $this->isRead = $isRead;
        if ($isRead && !$this->readAt) {
            $this->readAt = new DateTimeImmutable();
        }
        return $this;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getExpiresAt(): ?DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(?DateTimeImmutable $expiresAt): static
    {
        $this->expiresAt = $expiresAt;
        return $this;
    }

    public function getReadAt(): ?DateTimeImmutable
    {
        return $this->readAt;
    }

    public function setReadAt(?DateTimeImmutable $readAt): static
    {
        $this->readAt = $readAt;
        return $this;
    }

    public function getActionUrl(): ?string
    {
        return $this->actionUrl;
    }

    public function setActionUrl(?string $actionUrl): static
    {
        $this->actionUrl = $actionUrl;
        return $this;
    }

    public function getActionLabel(): ?string
    {
        return $this->actionLabel;
    }

    public function setActionLabel(?string $actionLabel): static
    {
        $this->actionLabel = $actionLabel;
        return $this;
    }

    public function getIcon(): ?string
    {
        return $this->icon;
    }

    public function setIcon(?string $icon): static
    {
        $this->icon = $icon;
        return $this;
    }

    public function getColor(): ?string
    {
        return $this->color;
    }

    public function setColor(?string $color): static
    {
        $this->color = $color;
        return $this;
    }

    public function isExpired(): bool
    {
        return $this->expiresAt && $this->expiresAt < new DateTimeImmutable();
    }

    public function isActive(): bool
    {
        return !$this->isExpired();
    }
}
