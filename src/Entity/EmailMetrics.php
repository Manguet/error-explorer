<?php

namespace App\Entity;

use App\Service\Email\EmailPriority;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'email_metrics')]
#[ORM\Index(name: 'idx_type', columns: ['type'])]
#[ORM\Index(name: 'idx_success', columns: ['success'])]
#[ORM\Index(name: 'idx_sent_at', columns: ['sent_at'])]
#[ORM\Index(name: 'idx_priority', columns: ['priority'])]
#[ORM\Index(name: 'idx_type_sent_at', columns: ['type', 'sent_at'])]
class EmailMetrics
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 100)]
    private ?string $type = null;

    #[ORM\Column(name: 'recipient_email', type: Types::STRING, length: 255)]
    private ?string $recipientEmail = null;

    #[ORM\Column(type: Types::BOOLEAN)]
    private ?bool $success = null;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 1])]
    private int $attempts = 1;

    #[ORM\Column(name: 'execution_time_ms', type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $executionTimeMs = null;

    #[ORM\Column(type: Types::STRING, length: 10, enumType: EmailPriority::class, options: ['default' => 'normal'])]
    private EmailPriority $priority = EmailPriority::NORMAL;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $metadata = null;

    #[ORM\Column(name: 'sent_at', type: Types::DATETIME_MUTABLE, options: ['default' => 'CURRENT_TIMESTAMP'])]
    private ?\DateTimeInterface $sentAt = null;

    public function __construct()
    {
        $this->sentAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;
        return $this;
    }

    public function getRecipientEmail(): ?string
    {
        return $this->recipientEmail;
    }

    public function setRecipientEmail(string $recipientEmail): static
    {
        $this->recipientEmail = $recipientEmail;
        return $this;
    }

    public function isSuccess(): ?bool
    {
        return $this->success;
    }

    public function setSuccess(bool $success): static
    {
        $this->success = $success;
        return $this;
    }

    public function getAttempts(): int
    {
        return $this->attempts;
    }

    public function setAttempts(int $attempts): static
    {
        $this->attempts = $attempts;
        return $this;
    }

    public function getExecutionTimeMs(): ?string
    {
        return $this->executionTimeMs;
    }

    public function setExecutionTimeMs(string $executionTimeMs): static
    {
        $this->executionTimeMs = $executionTimeMs;
        return $this;
    }

    public function getPriority(): EmailPriority
    {
        return $this->priority;
    }

    public function setPriority(EmailPriority $priority): static
    {
        $this->priority = $priority;
        return $this;
    }

    public function getMetadata(): ?array
    {
        return $this->metadata;
    }

    public function setMetadata(?array $metadata): static
    {
        $this->metadata = $metadata;
        return $this;
    }

    public function getSentAt(): ?\DateTimeInterface
    {
        return $this->sentAt;
    }

    public function setSentAt(\DateTimeInterface $sentAt): static
    {
        $this->sentAt = $sentAt;
        return $this;
    }
}
