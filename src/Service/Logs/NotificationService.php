<?php

namespace App\Service\Logs;

use App\Builder\Logs\NotificationBuilder;
use App\Entity\Notification;
use App\Entity\User;
use App\Repository\NotificationRepository;
use Doctrine\ORM\EntityManagerInterface;

class NotificationService
{
    public const AUDIENCE_ADMIN = 'admin';
    public const AUDIENCE_USER_DASHBOARD = 'user_dashboard';
    public const AUDIENCE_SPECIFIC_USER = 'specific_user';

    public const TYPE_USER_REGISTERED = 'user_registered';
    public const TYPE_PLAN_EXPIRED = 'plan_expired';
    public const TYPE_PLAN_UPGRADE_SUGGESTED = 'plan_upgrade_suggested';
    public const TYPE_ERROR_THRESHOLD_REACHED = 'error_threshold_reached';
    public const TYPE_PAYMENT_FAILED = 'payment_failed';
    public const TYPE_SYSTEM_MAINTENANCE = 'system_maintenance';

    public const PRIORITY_LOW = 'low';
    public const PRIORITY_NORMAL = 'normal';
    public const PRIORITY_HIGH = 'high';
    public const PRIORITY_URGENT = 'urgent';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly NotificationRepository $notificationRepository
    ) {}

    /**
     * Méthode principale - API fluide
     */
    public static function create(
        ?User $author = null,
        ?string $expireAfter = null,
        array $audiences = [self::AUDIENCE_ADMIN],
        string $title = '',
        ?string $description = null
    ): NotificationBuilder {
        return new NotificationBuilder($author, $expireAfter, $audiences, $title, $description);
    }

    /**
     * Créer une notification directement
     */
    public function createNotification(
        string $title,
        ?string $description = null,
        array $audiences = [self::AUDIENCE_ADMIN],
        ?User $author = null,
        ?User $targetUser = null,
        ?string $expireAfter = null,
        array $data = [],
        string $type = 'info',
        string $priority = self::PRIORITY_NORMAL
    ): Notification {
        $notification = new Notification();
        $notification->setTitle($title);
        $notification->setDescription($description);
        $notification->setAudience($audiences);
        $notification->setAuthor($author);
        $notification->setTargetUser($targetUser);
        $notification->setData($data);
        $notification->setType($type);
        $notification->setPriority($priority);

        if ($expireAfter) {
            $notification->setExpiresAt(new \DateTimeImmutable($expireAfter));
        }

        $this->entityManager->persist($notification);
        $this->entityManager->flush();

        return $notification;
    }

    /**
     * Notifications pré-définies pour les événements courants
     */
    public function notifyUserRegistered(User $user): Notification
    {
        return $this->createNotification(
            title: "Nouvel utilisateur inscrit",
            description: "L'utilisateur {$user->getEmail()} vient de s'inscrire sur la plateforme.",
            targetUser: $user,
            data: [
                'user_id' => $user->getId(),
                'user_email' => $user->getEmail(),
                'registered_at' => $user->getCreatedAt()?->format('Y-m-d H:i:s')
            ],
            type: self::TYPE_USER_REGISTERED
        );
    }

    public function notifyPlanExpired(User $user): Notification
    {
        return $this->createNotification(
            title: "Plan expiré",
            description: "Votre plan a expiré. Renouvelez-le pour continuer à utiliser toutes les fonctionnalités.",
            audiences: [self::AUDIENCE_USER_DASHBOARD, self::AUDIENCE_ADMIN],
            targetUser: $user,
            data: [
                'user_id' => $user->getId(),
                'expired_at' => new \DateTimeImmutable()
            ],
            type: self::TYPE_PLAN_EXPIRED,
            priority: self::PRIORITY_HIGH
        );
    }

    public function notifyPlanUpgradeSuggested(User $user, string $currentPlan, string $suggestedPlan): Notification
    {
        return $this->createNotification(
            title: "Mise à niveau suggérée",
            description: "Basé sur votre utilisation, nous vous suggérons de passer au plan {$suggestedPlan}.",
            audiences: [self::AUDIENCE_USER_DASHBOARD],
            targetUser: $user,
            expireAfter: '+7 days',
            data: [
                'current_plan' => $currentPlan,
                'suggested_plan' => $suggestedPlan,
                'user_id' => $user->getId()
            ],
            type: self::TYPE_PLAN_UPGRADE_SUGGESTED
        );
    }

    /**
     * Marquer comme lu
     */
    public function markAsRead(int $notificationId): bool
    {
        $notification = $this->notificationRepository->find($notificationId);
        if (!$notification) {
            return false;
        }

        $notification->setIsRead(true);
        $this->entityManager->flush();

        return true;
    }

    /**
     * Supprimer les notifications expirées
     */
    public function cleanupExpired(): int
    {
        return $this->notificationRepository->deleteExpired();
    }

    /**
     * Supprimer les notifications anciennes
     */
    public function cleanupOld(int $days = 30): int
    {
        return $this->notificationRepository->deleteOlderThan($days);
    }

    /**
     * Récupérer les notifications pour l'admin
     */
    public function getForAdmin(int $limit = 50, bool $unreadOnly = false): array
    {
        return $this->notificationRepository->findForAdmin($limit, $unreadOnly);
    }

    /**
     * Récupérer les notifications pour un utilisateur
     */
    public function getForUser(User $user, int $limit = 50, bool $unreadOnly = false): array
    {
        return $this->notificationRepository->findForUser($user, $limit, $unreadOnly);
    }

    /**
     * Compter les notifications non lues
     */
    public function getUnreadCountForAdmin(): int
    {
        return $this->notificationRepository->countUnreadForAdmin();
    }

    public function getUnreadCountForUser(User $user): int
    {
        return $this->notificationRepository->countUnreadForUser($user);
    }
}
