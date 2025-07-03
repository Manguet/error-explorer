<?php

namespace App\Entity;

use App\Repository\ErrorGroupRepository;
use DateTime;
use DateTimeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ErrorGroupRepository::class)]
#[ORM\Table(name: 'error_groups')]
#[ORM\Index(name: 'idx_fingerprint', columns: ['fingerprint'])]
#[ORM\Index(name: 'idx_project', columns: ['project'])]
#[ORM\Index(name: 'idx_status', columns: ['status'])]
#[ORM\Index(name: 'idx_last_seen', columns: ['last_seen'])]
#[ORM\Index(name: 'idx_project_id', columns: ['project_id'])]
class ErrorGroup
{
    public const STATUS_OPEN = 'open';
    public const STATUS_RESOLVED = 'resolved';
    public const STATUS_IGNORED = 'ignored';

    public const ERROR_TYPE_EXCEPTION = 'exception';
    public const ERROR_TYPE_ERROR = 'error';
    public const ERROR_TYPE_WARNING = 'warning';
    public const ERROR_TYPE_NOTICE = 'notice';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 64, unique: true)]
    private ?string $fingerprint = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $message = null;

    #[ORM\Column(length: 255)]
    private ?string $exceptionClass = null;

    #[ORM\Column(length: 500)]
    private ?string $file = null;

    #[ORM\Column]
    private ?int $line = null;

    #[ORM\Column(length: 100)]
    private ?string $project = null;

    #[ORM\ManyToOne(targetEntity: Project::class, inversedBy: 'errorGroups')]
    #[ORM\JoinColumn(name: 'project_id', referencedColumnName: 'id', nullable: true)]
    private ?Project $projectEntity = null;

    #[ORM\Column(nullable: true)]
    private ?int $httpStatusCode = null;

    #[ORM\Column(length: 50)]
    private ?string $errorType = self::ERROR_TYPE_EXCEPTION;

    #[ORM\Column]
    private ?int $occurrenceCount = 0;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?DateTimeInterface $firstSeen;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?DateTimeInterface $lastSeen;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?DateTimeInterface $lastAlertSentAt = null;

    #[ORM\Column(length: 20)]
    private ?string $status = self::STATUS_OPEN;

    #[ORM\OneToMany(targetEntity: ErrorOccurrence::class, mappedBy: 'errorGroup', orphanRemoval: true)]
    private Collection $occurrences;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $stackTracePreview = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $environment = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $aiSuggestions = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?DateTimeInterface $aiSuggestionsGeneratedAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'assigned_to_id', referencedColumnName: 'id', nullable: true)]
    private ?User $assignedTo = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?DateTimeInterface $assignedAt = null;

    #[ORM\ManyToMany(targetEntity: Tag::class, inversedBy: 'errorGroups')]
    #[ORM\JoinTable(name: 'error_group_tags')]
    private Collection $tags;

    public function __construct()
    {
        $this->occurrences = new ArrayCollection();
        $this->tags = new ArrayCollection();
        $this->firstSeen = new DateTime();
        $this->lastSeen = new DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFingerprint(): ?string
    {
        return $this->fingerprint;
    }

    public function setFingerprint(string $fingerprint): static
    {
        $this->fingerprint = $fingerprint;
        return $this;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(string $message): static
    {
        $this->message = $message;
        return $this;
    }

    public function getExceptionClass(): ?string
    {
        return $this->exceptionClass;
    }

    public function setExceptionClass(string $exceptionClass): static
    {
        $this->exceptionClass = $exceptionClass;
        return $this;
    }

    public function getFile(): ?string
    {
        return $this->file;
    }

    public function setFile(string $file): static
    {
        $this->file = $file;
        return $this;
    }

    public function getLine(): ?int
    {
        return $this->line;
    }

    public function setLine(int $line): static
    {
        $this->line = $line;
        return $this;
    }

    public function getProject(): ?string
    {
        return $this->project;
    }

    public function setProject(string $project): static
    {
        $this->project = $project;
        return $this;
    }

    public function getProjectEntity(): ?Project
    {
        return $this->projectEntity;
    }

    public function setProjectEntity(?Project $projectEntity): static
    {
        $this->projectEntity = $projectEntity;
        return $this;
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

    public function getErrorType(): ?string
    {
        return $this->errorType;
    }

    public function setErrorType(string $errorType): static
    {
        $this->errorType = $errorType;
        return $this;
    }

    public function getOccurrenceCount(): ?int
    {
        return $this->occurrenceCount;
    }

    public function setOccurrenceCount(int $occurrenceCount): static
    {
        $this->occurrenceCount = $occurrenceCount;
        return $this;
    }

    public function incrementOccurrenceCount(): static
    {
        $this->occurrenceCount++;
        $this->lastSeen = new DateTime();
        return $this;
    }

    public function getFirstSeen(): ?DateTimeInterface
    {
        return $this->firstSeen;
    }

    public function setFirstSeen(DateTimeInterface $firstSeen): static
    {
        $this->firstSeen = $firstSeen;
        return $this;
    }

    public function getLastSeen(): ?DateTimeInterface
    {
        return $this->lastSeen;
    }

    public function setLastSeen(DateTimeInterface $lastSeen): static
    {
        $this->lastSeen = $lastSeen;
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

    public function isResolved(): bool
    {
        return $this->status === self::STATUS_RESOLVED;
    }

    public function isIgnored(): bool
    {
        return $this->status === self::STATUS_IGNORED;
    }

    public function resolve(): static
    {
        $this->status = self::STATUS_RESOLVED;
        return $this;
    }

    public function ignore(): static
    {
        $this->status = self::STATUS_IGNORED;
        return $this;
    }

    public function reopen(): static
    {
        $this->status = self::STATUS_OPEN;
        return $this;
    }

    public function isError(): bool
    {
        return $this->errorType === self::ERROR_TYPE_ERROR;
    }

    public function isWarning(): bool
    {
        return $this->errorType === self::ERROR_TYPE_WARNING;
    }

    public function isNotice(): bool
    {
        return $this->errorType === self::ERROR_TYPE_NOTICE;
    }

    /**
     * @return Collection<int, ErrorOccurrence>
     */
    public function getOccurrences(): Collection
    {
        return $this->occurrences;
    }

    public function addOccurrence(ErrorOccurrence $occurrence): static
    {
        if (!$this->occurrences->contains($occurrence)) {
            $this->occurrences->add($occurrence);
            $occurrence->setErrorGroup($this);
        }

        return $this;
    }

    public function removeOccurrence(ErrorOccurrence $occurrence): static
    {
        if ($this->occurrences->removeElement($occurrence) && $occurrence->getErrorGroup() === $this) {
            $occurrence->setErrorGroup(null);
        }

        return $this;
    }

    /**
     * Retourne la dernière occurrence (la plus récente)
     */
    public function getLatestOccurrence(): ?ErrorOccurrence
    {
        if ($this->occurrences->isEmpty()) {
            return null;
        }

        // Trier par date de création décroissante et prendre le premier
        $criteria = \Doctrine\Common\Collections\Criteria::create()
            ->orderBy(['createdAt' => \Doctrine\Common\Collections\Order::Descending])
            ->setMaxResults(1);

        $latest = $this->occurrences->matching($criteria);
        
        return $latest->isEmpty() ? null : $latest->first();
    }

    public function getStackTracePreview(): ?string
    {
        return $this->stackTracePreview;
    }

    public function setStackTracePreview(?string $stackTracePreview): static
    {
        $this->stackTracePreview = $stackTracePreview;
        return $this;
    }

    public function getEnvironment(): ?string
    {
        return $this->environment;
    }

    public function setEnvironment(?string $environment): static
    {
        $this->environment = $environment;
        return $this;
    }

    /**
     * Retourne un titre lisible pour l'erreur
     */
    public function getTitle(): string
    {
        return sprintf('%s in %s:%d',
            $this->exceptionClass,
            basename($this->file),
            $this->line
        );
    }


    public function getLastAlertSentAt(): ?DateTimeInterface
    {
        return $this->lastAlertSentAt;
    }

    public function setLastAlertSentAt(?DateTimeInterface $lastAlertSentAt): static
    {
        $this->lastAlertSentAt = $lastAlertSentAt;
        return $this;
    }


    public function getAiSuggestions(): ?array
    {
        return $this->aiSuggestions;
    }

    public function setAiSuggestions(?array $aiSuggestions): static
    {
        // Nettoyer les données avant stockage - enlever les objets DateTime qui pourraient s'y glisser
        if ($aiSuggestions && isset($aiSuggestions['generated_at']) && $aiSuggestions['generated_at'] instanceof \DateTimeInterface) {
            $aiSuggestions['generated_at'] = $aiSuggestions['generated_at']->format('d/m/Y H:i');
        }
        
        $this->aiSuggestions = $aiSuggestions;
        $this->aiSuggestionsGeneratedAt = $aiSuggestions ? new DateTime() : null;
        return $this;
    }

    public function getAiSuggestionsGeneratedAt(): ?DateTimeInterface
    {
        return $this->aiSuggestionsGeneratedAt;
    }

    public function setAiSuggestionsGeneratedAt(?DateTimeInterface $aiSuggestionsGeneratedAt): static
    {
        $this->aiSuggestionsGeneratedAt = $aiSuggestionsGeneratedAt;
        return $this;
    }

    public function hasAiSuggestions(): bool
    {
        return !empty($this->aiSuggestions);
    }

    public function isAiSuggestionsExpired(int $hoursValid = 24): bool
    {
        if (!$this->aiSuggestionsGeneratedAt) {
            return true;
        }

        $expiryTime = (clone $this->aiSuggestionsGeneratedAt)->add(new \DateInterval("PT{$hoursValid}H"));
        return new DateTime() > $expiryTime;
    }

    public function getAssignedTo(): ?User
    {
        return $this->assignedTo;
    }

    public function setAssignedTo(?User $assignedTo): static
    {
        $this->assignedTo = $assignedTo;
        $this->assignedAt = $assignedTo ? new DateTime() : null;
        return $this;
    }

    public function getAssignedAt(): ?DateTimeInterface
    {
        return $this->assignedAt;
    }

    public function setAssignedAt(?DateTimeInterface $assignedAt): static
    {
        $this->assignedAt = $assignedAt;
        return $this;
    }

    public function isAssigned(): bool
    {
        return $this->assignedTo !== null;
    }

    public function assign(User $user): static
    {
        $this->assignedTo = $user;
        $this->assignedAt = new DateTime();
        return $this;
    }

    public function unassign(): static
    {
        $this->assignedTo = null;
        $this->assignedAt = null;
        return $this;
    }

    /**
     * @return Collection<int, Tag>
     */
    public function getTags(): Collection
    {
        return $this->tags;
    }

    public function addTag(Tag $tag): static
    {
        if (!$this->tags->contains($tag)) {
            $this->tags->add($tag);
            $tag->incrementUsageCount();
        }

        return $this;
    }

    public function removeTag(Tag $tag): static
    {
        if ($this->tags->removeElement($tag)) {
            $tag->decrementUsageCount();
        }

        return $this;
    }

    public function hasTag(Tag $tag): bool
    {
        return $this->tags->contains($tag);
    }

    public function hasTagByName(string $tagName): bool
    {
        foreach ($this->tags as $tag) {
            if ($tag->getName() === $tagName) {
                return true;
            }
        }
        return false;
    }

    public function getTagNames(): array
    {
        return $this->tags->map(fn(Tag $tag) => $tag->getName())->toArray();
    }

    public function getTagsAsArray(): array
    {
        return $this->tags->map(fn(Tag $tag) => $tag->toArray())->toArray();
    }

    public function clearTags(): static
    {
        foreach ($this->tags as $tag) {
            $tag->decrementUsageCount();
        }
        $this->tags->clear();
        return $this;
    }

    public function syncTags(array $tags): static
    {
        // Supprimer tous les tags actuels
        $this->clearTags();
        
        // Ajouter les nouveaux tags
        foreach ($tags as $tag) {
            if ($tag instanceof Tag) {
                $this->addTag($tag);
            }
        }
        
        return $this;
    }
}
