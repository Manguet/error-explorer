<?php

namespace App\Entity;

use App\Repository\InvoiceRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: InvoiceRepository::class)]
#[ORM\Table(name: 'invoices')]
class Invoice
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Subscription::class, inversedBy: 'invoices')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Subscription $subscription = null;

    #[ORM\Column(length: 255, unique: true)]
    private ?string $stripeInvoiceId = null;

    #[ORM\Column(length: 50)]
    private ?string $number = null;

    #[ORM\Column(length: 50)]
    private ?string $status = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $subtotal = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $tax = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $total = null;

    #[ORM\Column(length: 3)]
    private ?string $currency = 'EUR';

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $periodStart = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $periodEnd = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $dueDate = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $paidAt = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private array $lineItems = [];

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $pdfPath = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $updatedAt = null;

    // Statuts possibles
    public const STATUS_DRAFT = 'draft';
    public const STATUS_OPEN = 'open';
    public const STATUS_PAID = 'paid';
    public const STATUS_VOID = 'void';
    public const STATUS_UNCOLLECTIBLE = 'uncollectible';

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
        $this->status = self::STATUS_DRAFT;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSubscription(): ?Subscription
    {
        return $this->subscription;
    }

    public function setSubscription(?Subscription $subscription): static
    {
        $this->subscription = $subscription;
        return $this;
    }

    public function getStripeInvoiceId(): ?string
    {
        return $this->stripeInvoiceId;
    }

    public function setStripeInvoiceId(string $stripeInvoiceId): static
    {
        $this->stripeInvoiceId = $stripeInvoiceId;
        return $this;
    }

    public function getNumber(): ?string
    {
        return $this->number;
    }

    public function setNumber(string $number): static
    {
        $this->number = $number;
        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
        $this->updatedAt = new \DateTime();
        return $this;
    }

    public function getSubtotal(): ?string
    {
        return $this->subtotal;
    }

    public function setSubtotal(string $subtotal): static
    {
        $this->subtotal = $subtotal;
        return $this;
    }

    public function getTax(): ?string
    {
        return $this->tax;
    }

    public function setTax(string $tax): static
    {
        $this->tax = $tax;
        return $this;
    }

    public function getTotal(): ?string
    {
        return $this->total;
    }

    public function setTotal(string $total): static
    {
        $this->total = $total;
        return $this;
    }

    public function getCurrency(): ?string
    {
        return $this->currency;
    }

    public function setCurrency(string $currency): static
    {
        $this->currency = $currency;
        return $this;
    }

    public function getPeriodStart(): ?\DateTimeInterface
    {
        return $this->periodStart;
    }

    public function setPeriodStart(\DateTimeInterface $periodStart): static
    {
        $this->periodStart = $periodStart;
        return $this;
    }

    public function getPeriodEnd(): ?\DateTimeInterface
    {
        return $this->periodEnd;
    }

    public function setPeriodEnd(\DateTimeInterface $periodEnd): static
    {
        $this->periodEnd = $periodEnd;
        return $this;
    }

    public function getDueDate(): ?\DateTimeInterface
    {
        return $this->dueDate;
    }

    public function setDueDate(?\DateTimeInterface $dueDate): static
    {
        $this->dueDate = $dueDate;
        return $this;
    }

    public function getPaidAt(): ?\DateTimeInterface
    {
        return $this->paidAt;
    }

    public function setPaidAt(?\DateTimeInterface $paidAt): static
    {
        $this->paidAt = $paidAt;
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

    public function getLineItems(): array
    {
        return $this->lineItems;
    }

    public function setLineItems(?array $lineItems): static
    {
        $this->lineItems = $lineItems ?? [];
        return $this;
    }

    public function getPdfPath(): ?string
    {
        return $this->pdfPath;
    }

    public function setPdfPath(?string $pdfPath): static
    {
        $this->pdfPath = $pdfPath;
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

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isVoid(): bool
    {
        return $this->status === self::STATUS_VOID;
    }

    public function isOverdue(): bool
    {
        return $this->isOpen() && $this->dueDate && $this->dueDate < new \DateTime();
    }

    public function getFormattedTotal(): string
    {
        return number_format((float) $this->total, 2) . ' ' . strtoupper($this->currency);
    }

    public function getFormattedSubtotal(): string
    {
        return number_format((float) $this->subtotal, 2) . ' ' . strtoupper($this->currency);
    }

    public function getFormattedTax(): string
    {
        return number_format((float) $this->tax, 2) . ' ' . strtoupper($this->currency);
    }

    public function getUser(): ?User
    {
        return $this->subscription?->getUser();
    }

    public function getPlan(): ?Plan
    {
        return $this->subscription?->getPlan();
    }

    public function getStatusBadgeClass(): string
    {
        return match($this->status) {
            self::STATUS_PAID => 'badge-success',
            self::STATUS_OPEN => 'badge-warning',
            self::STATUS_VOID => 'badge-secondary',
            self::STATUS_UNCOLLECTIBLE => 'badge-danger',
            default => 'badge-secondary'
        };
    }

    public function getStatusLabel(): string
    {
        return match($this->status) {
            self::STATUS_PAID => 'Payée',
            self::STATUS_OPEN => 'En attente',
            self::STATUS_VOID => 'Annulée',
            self::STATUS_UNCOLLECTIBLE => 'Impayée',
            self::STATUS_DRAFT => 'Brouillon',
            default => 'Inconnu'
        };
    }

    public function getPeriodLabel(): string
    {
        if (!$this->periodStart || !$this->periodEnd) {
            return '';
        }

        return sprintf(
            'Du %s au %s',
            $this->periodStart->format('d/m/Y'),
            $this->periodEnd->format('d/m/Y')
        );
    }
}