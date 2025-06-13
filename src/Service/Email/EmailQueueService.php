<?php

namespace App\Service\Email;

use App\Entity\User;
use App\Message\SendEmailMessage;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;

/**
 * Service de gestion de la queue d'emails
 *
 * Permet l'envoi d'emails en arrière-plan avec priorités,
 * délais et batching pour optimiser les performances.
 */
class EmailQueueService
{
    public function __construct(
        private readonly MessageBusInterface $messageBus,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Ajoute un email à la queue
     */
    public function queueEmail(
        string $type,
        User $recipient,
        array $context = [],
        ?string $subject = null,
        ?string $template = null,
        array $metadata = [],
        EmailPriority $priority = EmailPriority::NORMAL,
        int $delaySeconds = 0
    ): void {
        $message = new SendEmailMessage(
            type: $type,
            recipientId: $recipient->getId(),
            context: $context,
            subject: $subject,
            template: $template,
            metadata: $metadata,
            priority: $priority
        );

        $stamps = [];

        if ($delaySeconds > 0) {
            $stamps[] = new DelayStamp($delaySeconds * 1000);
        }

        $stamps[] = $this->getPriorityStamp($priority);

        $this->messageBus->dispatch($message, $stamps);

        $this->logger->info('Email ajouté à la queue', [
            'type' => $type,
            'recipient_id' => $recipient->getId(),
            'priority' => $priority->value,
            'delay_seconds' => $delaySeconds
        ]);
    }

    /**
     * Envoi d'email de vérification en queue
     */
    public function queueEmailVerification(User $user, int $delaySeconds = 30): void
    {
        $this->queueEmail(
            type: 'email_verification',
            recipient: $user,
            metadata: ['user_id' => $user->getId()],
            priority: EmailPriority::HIGH,
            delaySeconds: $delaySeconds
        );
    }

    /**
     * Envoi d'email de réinitialisation en queue
     */
    public function queuePasswordReset(User $user): void
    {
        $this->queueEmail(
            type: 'password_reset',
            recipient: $user,
            metadata: ['user_id' => $user->getId()],
            priority: EmailPriority::HIGH
        );
    }

    /**
     * Envoi d'email de bienvenue avec délai
     */
    public function queueWelcomeEmail(User $user, int $delayMinutes = 5): void
    {
        $this->queueEmail(
            type: 'welcome',
            recipient: $user,
            metadata: ['user_id' => $user->getId()],
            priority: EmailPriority::LOW,
            delaySeconds: $delayMinutes * 60
        );
    }

    /**
     * Envoi de digest hebdomadaire
     */
    public function queueWeeklyDigest(User $user, array $digestData): void
    {
        $this->queueEmail(
            type: 'weekly_digest',
            recipient: $user,
            context: ['digest' => $digestData],
            metadata: [
                'user_id' => $user->getId(),
                'digest_type' => 'weekly'
            ],
            priority: EmailPriority::LOW
        );
    }

    /**
     * Envoi d'alerte d'erreur en urgence
     */
    public function queueErrorAlert(User $user, array $errorData): void
    {
        $this->queueEmail(
            type: 'error_threshold_reached',
            recipient: $user,
            context: ['project' => $errorData],
            metadata: [
                'user_id' => $user->getId(),
                'project_id' => $errorData['id'] ?? null,
                'alert_type' => 'error_threshold'
            ],
            priority: EmailPriority::HIGH
        );
    }

    /**
     * Envoi d'emails en batch pour plusieurs utilisateurs
     */
    public function queueBatchEmails(
        string $type,
        array $users,
        array $context = [],
        EmailPriority $priority = EmailPriority::LOW,
        int $batchSize = 100,
        int $delayBetweenBatches = 30
    ): void {
        $batches = array_chunk($users, $batchSize);
        $batchDelay = 0;

        foreach ($batches as $batchIndex => $batch) {
            foreach ($batch as $user) {
                $this->queueEmail(
                    type: $type,
                    recipient: $user,
                    context: $context,
                    metadata: [
                        'batch_index' => $batchIndex,
                        'batch_size' => count($batch),
                        'user_id' => $user->getId()
                    ],
                    priority: $priority,
                    delaySeconds: $batchDelay
                );
            }

            $batchDelay += $delayBetweenBatches;
        }

        $this->logger->info('Emails batch ajoutés à la queue', [
            'type' => $type,
            'total_users' => count($users),
            'batches_count' => count($batches),
            'batch_size' => $batchSize
        ]);
    }

    /**
     * Obtient le stamp de priorité pour le routing
     */
    private function getPriorityStamp(EmailPriority $priority): object
    {
        return match($priority) {
            EmailPriority::HIGH => new TransportNamesStamp(['high_priority']),
            EmailPriority::LOW => new TransportNamesStamp(['low_priority']),
            default => new TransportNamesStamp(['normal_priority'])
        };
    }
}
