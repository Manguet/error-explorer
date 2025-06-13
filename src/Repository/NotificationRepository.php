<?php

namespace App\Repository;

use App\Entity\Notification;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class NotificationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Notification::class);
    }

    /**
     * Récupérer les notifications pour l'admin
     */
    public function findForAdmin(int $limit = 50, bool $unreadOnly = false): array
    {
        $qb = $this->createQueryBuilder('n')
            ->where('n.visibleToAdmin = true')
            ->andWhere('n.expiresAt IS NULL OR n.expiresAt > :now')
            ->setParameter('now', new \DateTimeImmutable())
            ->orderBy('n.priority', 'DESC')
            ->addOrderBy('n.createdAt', 'DESC')
            ->setMaxResults($limit);

        if ($unreadOnly) {
            $qb->andWhere('n.isRead = false');
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Récupérer les notifications pour un utilisateur
     */
    public function findForUser(User $user, int $limit = 50, bool $unreadOnly = false): array
    {
        $qb = $this->createQueryBuilder('n')
            ->where('n.visibleToUserDashboard = true')
            ->orWhere('n.targetUser = :user')
            ->setParameter('user', $user)
            ->andWhere('n.expiresAt IS NULL OR n.expiresAt > :now')
            ->setParameter('now', new \DateTimeImmutable())
            ->orderBy('n.priority', 'DESC')
            ->addOrderBy('n.createdAt', 'DESC')
            ->setMaxResults($limit);

        if ($unreadOnly) {
            $qb->andWhere('n.isRead = false');
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Compter les notifications non lues pour l'admin
     */
    public function countUnreadForAdmin(): int
    {
        return $this->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->where('n.visibleToAdmin = true')
            ->andWhere('n.isRead = false')
            ->andWhere('n.expiresAt IS NULL OR n.expiresAt > :now')
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Compter les notifications non lues pour un utilisateur
     */
    public function countUnreadForUser(User $user): int
    {
        return $this->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->where('n.visibleToUserDashboard = true')
            ->orWhere('n.targetUser = :user')
            ->setParameter('user', $user)
            ->andWhere('n.isRead = false')
            ->andWhere('n.expiresAt IS NULL OR n.expiresAt > :now')
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Supprimer les notifications expirées
     */
    public function deleteExpired(): int
    {
        return $this->createQueryBuilder('n')
            ->delete()
            ->where('n.expiresAt < :now')
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->execute();
    }

    /**
     * Supprimer les notifications anciennes (+ de X jours)
     */
    public function deleteOlderThan(int $days): int
    {
        $date = new \DateTimeImmutable("-{$days} days");

        return $this->createQueryBuilder('n')
            ->delete()
            ->where('n.createdAt < :date')
            ->setParameter('date', $date)
            ->getQuery()
            ->execute();
    }

    /**
     * Marquer toutes les notifications comme lues pour l'admin
     */
    public function markAllAsReadForAdmin(): int
    {
        return $this->createQueryBuilder('n')
            ->update()
            ->set('n.isRead', 'true')
            ->set('n.readAt', ':now')
            ->where('n.visibleToAdmin = true')
            ->setParameter('now', new \DateTimeImmutable())
            ->andWhere('n.isRead = false')
            ->getQuery()
            ->execute();
    }

    /**
     * Marquer toutes les notifications comme lues pour un utilisateur
     */
    public function markAllAsReadForUser(User $user): int
    {
        return $this->createQueryBuilder('n')
            ->update()
            ->set('n.isRead', 'true')
            ->set('n.readAt', ':now')
            ->where('n.visibleToUserDashboard = true')
            ->orWhere('n.targetUser = :user')
            ->setParameter('user', $user)
            ->setParameter('now', new \DateTimeImmutable())
            ->andWhere('n.isRead = false')
            ->getQuery()
            ->execute();
    }

    /**
     * Statistiques des notifications
     */
    public function getStats(): array
    {
        $total = $this->count();
        $unread = $this->count(['isRead' => false]);
        $expired = $this->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->where('n.expiresAt < :now')
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'total' => $total,
            'unread' => $unread,
            'read' => $total - $unread,
            'expired' => $expired,
        ];
    }

    /**
     * Recherche de notifications avec filtres avancés
     */
    public function findWithFilters(array $filters = []): array
    {
        $qb = $this->createQueryBuilder('n');

        // Filtre par type
        if (!empty($filters['type'])) {
            $qb->andWhere('n.type = :type')
                ->setParameter('type', $filters['type']);
        }

        // Filtre par priorité
        if (!empty($filters['priority'])) {
            $qb->andWhere('n.priority = :priority')
                ->setParameter('priority', $filters['priority']);
        }

        // Filtre par auteur
        if (!empty($filters['author'])) {
            $qb->andWhere('n.author = :author')
                ->setParameter('author', $filters['author']);
        }

        // Filtre par période
        if (!empty($filters['date_from'])) {
            $qb->andWhere('n.createdAt >= :dateFrom')
                ->setParameter('dateFrom', new \DateTimeImmutable($filters['date_from']));
        }

        if (!empty($filters['date_to'])) {
            $qb->andWhere('n.createdAt <= :dateTo')
                ->setParameter('dateTo', new \DateTimeImmutable($filters['date_to']));
        }

        // Filtre par statut de lecture
        if (isset($filters['is_read'])) {
            $qb->andWhere('n.isRead = :isRead')
                ->setParameter('isRead', (bool)$filters['is_read']);
        }

        // Exclure les expirées par défaut
        if (!isset($filters['include_expired']) || !$filters['include_expired']) {
            $qb->andWhere('n.expiresAt IS NULL OR n.expiresAt > :now')
                ->setParameter('now', new \DateTimeImmutable());
        }

        // Tri
        $qb->orderBy('n.priority', 'DESC')
            ->addOrderBy('n.createdAt', 'DESC');

        // Limite
        if (!empty($filters['limit'])) {
            $qb->setMaxResults($filters['limit']);
        }

        return $qb->getQuery()->getResult();
    }
}
