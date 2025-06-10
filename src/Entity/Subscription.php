<?php

namespace App\Entity;

use App\Repository\SubscriptionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SubscriptionRepository::class)]
#[ORM\Table(name: 'subscriptions')]
class Subscription
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\ManyToOne(targetEntity: Plan::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Plan $plan = null;

    #[ORM\Column(length: 255, unique: true)]
    private ?string $stripeSubscriptionId = null;

    #[ORM\Column(length: 255)]
    private ?string $stripeCustomerId = null;

    #[ORM\Column(length: 50)]
    private ?string $status = null;

    #[ORM\Column(length: 50)]
    private ?string $billingPeriod = null; // monthly, yearly

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $amount = null;

    #[ORM\Column(length: 3)]
    private ?string $currency = 'EUR';

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $currentPeriodStart = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $currentPeriodEnd = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $cancelAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $canceledAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $trialStart = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $trialEnd = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\OneToMany(targetEntity: Invoice::class, mappedBy: 'subscription', orphanRemoval: true)]
    private Collection $invoices;

    // Statuts possibles
    public const STATUS_ACTIVE = 'active';
    public const STATUS_TRIALING = 'trialing';
    public const STATUS_PAST_DUE = 'past_due';
    public const STATUS_CANCELED = 'canceled';
    public const STATUS_UNPAID = 'unpaid';
    public const STATUS_INCOMPLETE = 'incomplete';
    public const STATUS_INCOMPLETE_EXPIRED = 'incomplete_expired';

    public const BILLING_PERIOD_MONTHLY = 'monthly';
    public const BILLING_PERIOD_YEARLY = 'yearly';

    public function __construct()
    {
        $this->invoices = new ArrayCollection();
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
        $this->status = self::STATUS_INCOMPLETE;
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getPlan(): ?Plan
    {
        return $this->plan;
    }

    public function setPlan(?Plan $plan): static
    {
        $this->plan = $plan;
        return $this;
    }

    public function getStripeSubscriptionId(): ?string
    {
        return $this->stripeSubscriptionId;
    }

    public function setStripeSubscriptionId(string $stripeSubscriptionId): static
    {
        $this->stripeSubscriptionId = $stripeSubscriptionId;
        return $this;
    }

    public function getStripeCustomerId(): ?string
    {
        return $this->stripeCustomerId;
    }

    public function setStripeCustomerId(string $stripeCustomerId): static
    {
        $this->stripeCustomerId = $stripeCustomerId;
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

    public function getBillingPeriod(): ?string
    {
        return $this->billingPeriod;
    }

    public function setBillingPeriod(string $billingPeriod): static
    {
        $this->billingPeriod = $billingPeriod;
        return $this;
    }

    public function getAmount(): ?string
    {
        return $this->amount;
    }

    public function setAmount(string $amount): static
    {
        $this->amount = $amount;
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

    public function getCurrentPeriodStart(): ?\DateTimeInterface
    {
        return $this->currentPeriodStart;
    }

    public function setCurrentPeriodStart(\DateTimeInterface $currentPeriodStart): static
    {
        $this->currentPeriodStart = $currentPeriodStart;
        return $this;
    }

    public function getCurrentPeriodEnd(): ?\DateTimeInterface
    {
        return $this->currentPeriodEnd;
    }

    public function setCurrentPeriodEnd(\DateTimeInterface $currentPeriodEnd): static
    {
        $this->currentPeriodEnd = $currentPeriodEnd;
        return $this;
    }

    public function getCancelAt(): ?\DateTimeInterface
    {
        return $this->cancelAt;
    }

    public function setCancelAt(?\DateTimeInterface $cancelAt): static
    {
        $this->cancelAt = $cancelAt;
        return $this;
    }

    public function getCanceledAt(): ?\DateTimeInterface
    {
        return $this->canceledAt;
    }

    public function setCanceledAt(?\DateTimeInterface $canceledAt): static
    {
        $this->canceledAt = $canceledAt;
        return $this;
    }

    public function getTrialStart(): ?\DateTimeInterface
    {
        return $this->trialStart;
    }

    public function setTrialStart(?\DateTimeInterface $trialStart): static
    {
        $this->trialStart = $trialStart;
        return $this;
    }

    public function getTrialEnd(): ?\DateTimeInterface
    {
        return $this->trialEnd;
    }

    public function setTrialEnd(?\DateTimeInterface $trialEnd): static
    {
        $this->trialEnd = $trialEnd;
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
     * @return Collection<int, Invoice>
     */
    public function getInvoices(): Collection
    {
        return $this->invoices;
    }

    public function addInvoice(Invoice $invoice): static
    {
        if (!$this->invoices->contains($invoice)) {
            $this->invoices->add($invoice);
            $invoice->setSubscription($this);
        }

        return $this;
    }

    public function removeInvoice(Invoice $invoice): static
    {
        if ($this->invoices->removeElement($invoice) && $invoice->getSubscription() === $this) {
            $invoice->setSubscription(null);
        }

        return $this;
    }

    // Méthodes utilitaires

    public function isActive(): bool
    {
        return in_array($this->status, [self::STATUS_ACTIVE, self::STATUS_TRIALING]);
    }

    public function isCanceled(): bool
    {
        return $this->status === self::STATUS_CANCELED;
    }

    public function isTrialing(): bool
    {
        return $this->status === self::STATUS_TRIALING;
    }

    public function isPastDue(): bool
    {
        return $this->status === self::STATUS_PAST_DUE;
    }

    public function isOnTrial(): bool
    {
        return $this->trialEnd && $this->trialEnd > new \DateTime();
    }

    public function getTrialDaysRemaining(): int
    {
        if (!$this->isOnTrial()) {
            return 0;
        }

        $now = new \DateTime();
        $diff = $now->diff($this->trialEnd);
        return $diff->days;
    }

    public function getDaysUntilRenewal(): int
    {
        if (!$this->currentPeriodEnd) {
            return 0;
        }

        $now = new \DateTime();
        if ($this->currentPeriodEnd < $now) {
            return 0;
        }

        $diff = $now->diff($this->currentPeriodEnd);
        return $diff->days;
    }

    public function getFormattedAmount(): string
    {
        return number_format((float) $this->amount, 2) . ' ' . strtoupper($this->currency);
    }

    public function isYearly(): bool
    {
        return $this->billingPeriod === self::BILLING_PERIOD_YEARLY;
    }

    public function isMonthly(): bool
    {
        return $this->billingPeriod === self::BILLING_PERIOD_MONTHLY;
    }
}