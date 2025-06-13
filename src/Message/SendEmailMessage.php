<?php

namespace App\Message;

use App\Service\Email\EmailPriority;

/**
 * Message pour l'envoi d'email via Messenger
 */
class SendEmailMessage
{
    public function __construct(
        public readonly string $type,
        public readonly int $recipientId,
        public readonly array $context = [],
        public readonly ?string $subject = null,
        public readonly ?string $template = null,
        public readonly array $metadata = [],
        public readonly EmailPriority $priority = EmailPriority::NORMAL
    ) {}
}
