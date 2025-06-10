<?php

namespace App\Entity;

use App\Repository\PaymentMethodRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PaymentMethodRepository::class)]
#[ORM\Table(name: 'payment_methods')]
class PaymentMethod
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\Column(length: 255, unique: true)]
    private ?string $stripePaymentMethodId = null;

    #[ORM\Column(length: 50)]
    private ?string $type = null;

    #[ORM\Column(length: 4, nullable: true)]
    private ?string $cardLast4 = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $cardBrand = null;

    #[ORM\Column(nullable: true)]
    private ?int $cardExpMonth = null;

    #[ORM\Column(nullable: true)]
    private ?int $cardExpYear = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $cardFingerprint = null;

    #[ORM\Column(length: 2, nullable: true)]
    private ?string $cardCountry = null;

    #[ORM\Column]
    private ?bool $isDefault = false;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $updatedAt = null;

    // Types de moyens de paiement
    public const TYPE_CARD = 'card';
    public const TYPE_SEPA_DEBIT = 'sepa_debit';
    public const TYPE_IDEAL = 'ideal';
    public const TYPE_SOFORT = 'sofort';

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
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

    public function getStripePaymentMethodId(): ?string
    {
        return $this->stripePaymentMethodId;
    }

    public function setStripePaymentMethodId(string $stripePaymentMethodId): static
    {
        $this->stripePaymentMethodId = $stripePaymentMethodId;
        return $this;
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

    public function getCardLast4(): ?string
    {
        return $this->cardLast4;
    }

    public function setCardLast4(?string $cardLast4): static
    {
        $this->cardLast4 = $cardLast4;
        return $this;
    }

    public function getCardBrand(): ?string
    {
        return $this->cardBrand;
    }

    public function setCardBrand(?string $cardBrand): static
    {
        $this->cardBrand = $cardBrand;
        return $this;
    }

    public function getCardExpMonth(): ?int
    {
        return $this->cardExpMonth;
    }

    public function setCardExpMonth(?int $cardExpMonth): static
    {
        $this->cardExpMonth = $cardExpMonth;
        return $this;
    }

    public function getCardExpYear(): ?int
    {
        return $this->cardExpYear;
    }

    public function setCardExpYear(?int $cardExpYear): static
    {
        $this->cardExpYear = $cardExpYear;
        return $this;
    }

    public function getCardFingerprint(): ?string
    {
        return $this->cardFingerprint;
    }

    public function setCardFingerprint(?string $cardFingerprint): static
    {
        $this->cardFingerprint = $cardFingerprint;
        return $this;
    }

    public function getCardCountry(): ?string
    {
        return $this->cardCountry;
    }

    public function setCardCountry(?string $cardCountry): static
    {
        $this->cardCountry = $cardCountry;
        return $this;
    }

    public function isDefault(): ?bool
    {
        return $this->isDefault;
    }

    public function setIsDefault(bool $isDefault): static
    {
        $this->isDefault = $isDefault;
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

    // Méthodes utilitaires

    public function isCard(): bool
    {
        return $this->type === self::TYPE_CARD;
    }

    public function getDisplayName(): string
    {
        if ($this->isCard()) {
            $brand = ucfirst($this->cardBrand ?? 'Card');
            return sprintf('%s •••• %s', $brand, $this->cardLast4);
        }

        return match($this->type) {
            self::TYPE_SEPA_DEBIT => 'Virement SEPA',
            self::TYPE_IDEAL => 'iDEAL',
            self::TYPE_SOFORT => 'SOFORT',
            default => ucfirst($this->type)
        };
    }

    public function isExpired(): bool
    {
        if (!$this->isCard() || !$this->cardExpMonth || !$this->cardExpYear) {
            return false;
        }

        $now = new \DateTime();
        $expiration = new \DateTime(sprintf('%d-%02d-01', $this->cardExpYear, $this->cardExpMonth));
        $expiration->modify('last day of this month');

        return $expiration < $now;
    }

    public function isExpiringSoon(): bool
    {
        if (!$this->isCard() || !$this->cardExpMonth || !$this->cardExpYear) {
            return false;
        }

        $now = new \DateTime();
        $expiration = new \DateTime(sprintf('%d-%02d-01', $this->cardExpYear, $this->cardExpMonth));
        $expiration->modify('last day of this month');
        
        $threeMonthsFromNow = clone $now;
        $threeMonthsFromNow->modify('+3 months');

        return $expiration <= $threeMonthsFromNow && $expiration >= $now;
    }

    public function getExpirationDate(): ?string
    {
        if (!$this->isCard() || !$this->cardExpMonth || !$this->cardExpYear) {
            return null;
        }

        return sprintf('%02d/%d', $this->cardExpMonth, $this->cardExpYear);
    }

    public function getBrandIcon(): string
    {
        return match($this->cardBrand) {
            'visa' => 'fab fa-cc-visa',
            'mastercard' => 'fab fa-cc-mastercard',
            'amex' => 'fab fa-cc-amex',
            'discover' => 'fab fa-cc-discover',
            'diners' => 'fab fa-cc-diners-club',
            'jcb' => 'fab fa-cc-jcb',
            default => 'far fa-credit-card'
        };
    }

    public function getStatusBadgeClass(): string
    {
        if ($this->isExpired()) {
            return 'badge-danger';
        }
        
        if ($this->isExpiringSoon()) {
            return 'badge-warning';
        }
        
        return 'badge-success';
    }

    public function getStatusLabel(): string
    {
        if ($this->isExpired()) {
            return 'Expirée';
        }
        
        if ($this->isExpiringSoon()) {
            return 'Expire bientôt';
        }
        
        return 'Active';
    }
}