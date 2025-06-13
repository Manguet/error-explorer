<?php

namespace App\Service\Email;

/**
 * Résultat d'envoi d'email
 */
class EmailResult
{
    public function __construct(
        private readonly bool $success,
        private readonly int $attempts,
        private readonly ?string $errorMessage = null,
        private readonly array $metadata = []
    ) {}

    public static function success(int $attempts, array $metadata = []): self
    {
        return new self(true, $attempts, null, $metadata);
    }

    public static function failure(int $attempts, ?string $errorMessage = null, array $metadata = []): self
    {
        return new self(false, $attempts, $errorMessage, $metadata);
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function getAttempts(): int
    {
        return $this->attempts;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }
}
