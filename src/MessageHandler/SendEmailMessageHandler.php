<?php

namespace App\MessageHandler;

use App\Message\SendEmailMessage;
use App\Repository\UserRepository;
use App\Service\Email\EmailService;
use Exception;
use Psr\Log\LoggerInterface;

/**
 * Handler pour traiter les messages d'email
 */
class SendEmailMessageHandler
{
    public function __construct(
        private readonly EmailService $emailService,
        private readonly UserRepository $userRepository,
        private readonly LoggerInterface $logger
    ) {}

    public function __invoke(SendEmailMessage $message): void
    {
        $user = $this->userRepository->find($message->recipientId);

        if (!$user) {
            $this->logger->error('Utilisateur introuvable pour envoi email', [
                'user_id' => $message->recipientId,
                'email_type' => $message->type
            ]);
            return;
        }

        try {
            $result = $this->emailService->sendEmail(
                type: $message->type,
                recipient: $user,
                context: $message->context,
                subject: $message->subject,
                template: $message->template,
                metadata: $message->metadata,
                priority: $message->priority
            );

            if (!$result->isSuccess()) {
                $this->logger->error('Échec envoi email depuis la queue', [
                    'user_id' => $message->recipientId,
                    'email_type' => $message->type,
                    'error' => $result->getErrorMessage(),
                    'attempts' => $result->getAttempts()
                ]);
            }

        } catch (Exception $e) {
            $this->logger->error('Exception lors de l\'envoi email depuis la queue', [
                'user_id' => $message->recipientId,
                'email_type' => $message->type,
                'exception' => $e->getMessage()
            ]);

            throw $e;
        }
    }
}
