<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * Used to upgrade (rehash) the user's password automatically over time.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

    /**
     * Trouve les utilisateurs actifs avec pagination
     */
    public function findActiveUsers(int $limit = 20, int $offset = 0): array
    {
        return $this->createQueryBuilder('u')
            ->where('u.isActive = true')
            ->orderBy('u.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();
    }

    /**
     * Compte les utilisateurs actifs
     */
    public function countActiveUsers(?string $search = null): int
    {
        $query = $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->andWhere('u.isActive = :true')
            ->setParameter('true', true)
            ->groupBy('u.id')
        ;

        if ($search) {
            $searchTerm = '%' . strtolower($search) . '%';
            $query
                ->andWhere('LOWER(u.email) LIKE :search')
                ->orWhere('LOWER(u.firstName) LIKE :search')
                ->orWhere('LOWER(u.lastName) LIKE :search')
                ->orWhere('LOWER(u.company) LIKE :search')
                ->setParameter('search', $searchTerm)
            ;
        }

        return count($query->getQuery()->getScalarResult());
    }

    /**
     * Compte les utilisateurs créés dans une période donnée
     */
    public function countUsersBetweenDates(\DateTime $startDate, \DateTime $endDate): int
    {
        return (int) $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.createdAt >= :start')
            ->andWhere('u.createdAt < :end')
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Obtient les statistiques de croissance mensuelle
     */
    public function getMonthlyGrowthStats(): array
    {
        $now = new \DateTime();
        $months = [];

        // Obtenir les 6 derniers mois
        for ($i = 5; $i >= 0; $i--) {
            $month = (clone $now)->modify("-{$i} months");
            $startOfMonth = (clone $month)->modify('first day of this month')->setTime(0, 0, 0);
            $endOfMonth = (clone $month)->modify('last day of this month')->setTime(23, 59, 59);

            $userCount = $this->countUsersBetweenDates($startOfMonth, $endOfMonth);

            $months[] = [
                'month' => $month->format('Y-m'),
                'month_name' => $month->format('F Y'),
                'users' => $userCount
            ];
        }

        return $months;
    }

    public function countUsersThisMonth(?string $search = null): int
    {
        $query = $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->andWhere('u.createdAt >= :startOfMonth')
            ->setParameter('startOfMonth', (new \DateTime())->modify('first day of this month'))
        ;

        if ($search) {
            $searchTerm = '%' . strtolower($search) . '%';
            $query
                ->andWhere('LOWER(u.email) LIKE :search')
                ->orWhere('LOWER(u.firstName) LIKE :search')
                ->orWhere('LOWER(u.lastName) LIKE :search')
                ->orWhere('LOWER(u.company) LIKE :search')
                ->setParameter('search', $searchTerm)
            ;
        }

        return (int) $query->getQuery()->getSingleScalarResult();
    }

    public function countActivePlans(?string $search = null): int
    {
        $query = $this->createQueryBuilder('u')
            ->select('COUNT(DISTINCT u.plan)')
            ->join('u.plan', 'p')
            ->andWhere('u.isActive = true')
        ;

        if ($search) {
            $searchTerm = '%' . strtolower($search) . '%';
            $query
                ->andWhere('LOWER(p.name) LIKE :search')
                ->setParameter('search', $searchTerm)
            ;
        }

        return (int) $query->getQuery()->getSingleScalarResult();
    }

    /**
     * Trouve les utilisateurs par plan
     */
    public function findByPlan(string $planSlug): array
    {
        return $this->createQueryBuilder('u')
            ->join('u.plan', 'p')
            ->where('p.slug = :planSlug')
            ->andWhere('u.isActive = true')
            ->setParameter('planSlug', $planSlug)
            ->orderBy('u.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les utilisateurs dont le plan expire bientôt
     */
    public function findUsersWithExpiringPlan(int $daysBeforeExpiry = 7): array
    {
        $date = new \DateTime("+{$daysBeforeExpiry} days");

        return $this->createQueryBuilder('u')
            ->where('u.planExpiresAt <= :date')
            ->andWhere('u.planExpiresAt > :now')
            ->andWhere('u.isActive = true')
            ->setParameter('date', $date)
            ->setParameter('now', new \DateTime())
            ->orderBy('u.planExpiresAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les utilisateurs qui ont dépassé leurs limites
     */
    public function findUsersOverLimits(): array
    {
        return $this->createQueryBuilder('u')
            ->join('u.plan', 'p')
            ->where('u.currentMonthlyErrors >= p.maxMonthlyErrors')
            ->andWhere('p.maxMonthlyErrors > 0') // Exclure les plans illimités
            ->andWhere('u.isActive = true')
            ->getQuery()
            ->getResult();
    }

    /**
     * Statistiques globales des utilisateurs
     */
    public function getGlobalStats(): array
    {
        $qb = $this->createQueryBuilder('u');

        $stats = $qb->select([
            'COUNT(u.id) as total_users',
            'SUM(CASE WHEN u.isActive = true THEN 1 ELSE 0 END) as active_users',
            'SUM(CASE WHEN u.isVerified = true THEN 1 ELSE 0 END) as verified_users',
            'SUM(u.currentProjectsCount) as total_projects',
            'SUM(u.currentMonthlyErrors) as total_monthly_errors'
        ])
            ->getQuery()
            ->getSingleResult();

        // Statistiques par plan
        $planStats = $this->createQueryBuilder('u')
            ->select('p.name as plan_name, COUNT(u.id) as user_count')
            ->join('u.plan', 'p')
            ->where('u.isActive = true')
            ->groupBy('p.id')
            ->orderBy('user_count', 'DESC')
            ->getQuery()
            ->getResult();

        $stats['by_plan'] = $planStats;

        return $stats;
    }

    /**
     * Recherche d'utilisateurs
     */
    public function search(string $query, int $limit = 20): array
    {
        $searchTerm = '%' . strtolower($query) . '%';

        return $this->createQueryBuilder('u')
            ->where('LOWER(u.email) LIKE :search')
            ->orWhere('LOWER(u.firstName) LIKE :search')
            ->orWhere('LOWER(u.lastName) LIKE :search')
            ->orWhere('LOWER(u.company) LIKE :search')
            ->setParameter('search', $searchTerm)
            ->orderBy('u.lastName', 'ASC')
            ->addOrderBy('u.firstName', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Reset des compteurs mensuels pour tous les utilisateurs
     */
    public function resetMonthlyCounters(): int
    {
        return $this->createQueryBuilder('u')
            ->update()
            ->set('u.currentMonthlyErrors', 0)
            ->getQuery()
            ->execute();
    }

    public function save(User $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(User $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Trouve un utilisateur par son email
     */
    public function findByEmail(string $email): ?User
    {
        return $this->findOneBy(['email' => $email]);
    }

    /**
     * Trouve un utilisateur par son token de vérification d'email
     */
    public function findByEmailVerificationToken(string $token): ?User
    {
        return $this->findOneBy(['emailVerificationToken' => $token]);
    }

    /**
     * Trouve un utilisateur par son token de réinitialisation de mot de passe
     */
    public function findByPasswordResetToken(string $token): ?User
    {
        return $this->findOneBy(['passwordResetToken' => $token]);
    }

    /**
     * Trouve les utilisateurs non vérifiés depuis plus de X jours
     */
    public function findUnverifiedUsers(int $daysOld = 7): array
    {
        $date = new \DateTime();
        $date->sub(new \DateInterval('P' . $daysOld . 'D'));

        return $this->createQueryBuilder('u')
            ->where('u.isVerified = :verified')
            ->andWhere('u.createdAt < :date')
            ->setParameter('verified', false)
            ->setParameter('date', $date)
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les utilisateurs avec des tokens de réinitialisation expirés
     */
    public function findExpiredPasswordResetTokens(int $hoursOld = 24): array
    {
        $date = new \DateTime();
        $date->sub(new \DateInterval('PT' . $hoursOld . 'H'));

        return $this->createQueryBuilder('u')
            ->where('u.passwordResetToken IS NOT NULL')
            ->andWhere('u.passwordResetRequestedAt < :date')
            ->setParameter('date', $date)
            ->getQuery()
            ->getResult();
    }

    /**
     * Nettoie les tokens expirés
     */
    public function cleanupExpiredTokens(): int
    {
        // Nettoyer les tokens de réinitialisation expirés
        $passwordResetCount = $this->createQueryBuilder('u')
            ->update()
            ->set('u.passwordResetToken', 'NULL')
            ->set('u.passwordResetRequestedAt', 'NULL')
            ->where('u.passwordResetToken IS NOT NULL')
            ->andWhere('u.passwordResetRequestedAt < :date')
            ->setParameter('date', new \DateTime('-24 hours'))
            ->getQuery()
            ->execute();

        return $passwordResetCount;
    }

    /**
     * Compte les utilisateurs par statut
     */
    public function countByStatus(): array
    {
        $result = $this->createQueryBuilder('u')
            ->select('
                COUNT(u.id) as total,
                SUM(CASE WHEN u.isActive = true THEN 1 ELSE 0 END) as active,
                SUM(CASE WHEN u.isVerified = true THEN 1 ELSE 0 END) as verified,
                SUM(CASE WHEN u.isActive = true AND u.isVerified = true THEN 1 ELSE 0 END) as active_verified
            ')
            ->getQuery()
            ->getSingleResult();

        return [
            'total' => (int) $result['total'],
            'active' => (int) $result['active'],
            'verified' => (int) $result['verified'],
            'active_verified' => (int) $result['active_verified'],
            'inactive' => (int) $result['total'] - (int) $result['active'],
            'unverified' => (int) $result['total'] - (int) $result['verified'],
        ];
    }

    /**
     * Trouve les utilisateurs récemment inscrits
     */
    public function findRecentlyRegistered(int $limit = 10): array
    {
        return $this->createQueryBuilder('u')
            ->orderBy('u.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les utilisateurs avec des connexions récentes
     */
    public function findRecentlyActive(int $hours = 24): array
    {
        $date = new \DateTime();
        $date->sub(new \DateInterval('PT' . $hours . 'H'));

        return $this->createQueryBuilder('u')
            ->where('u.lastLoginAt > :date')
            ->setParameter('date', $date)
            ->orderBy('u.lastLoginAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les utilisateurs dont le plan expire bientôt
     */
    public function findExpiringPlans(int $daysBeforeExpiration = 7): array
    {
        $date = new \DateTime();
        $date->add(new \DateInterval('P' . $daysBeforeExpiration . 'D'));

        return $this->createQueryBuilder('u')
            ->where('u.planExpiresAt IS NOT NULL')
            ->andWhere('u.planExpiresAt <= :date')
            ->andWhere('u.planExpiresAt > :now')
            ->setParameter('date', $date)
            ->setParameter('now', new \DateTime())
            ->orderBy('u.planExpiresAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les utilisateurs avec des plans expirés
     */
    public function findExpiredPlans(): array
    {
        return $this->createQueryBuilder('u')
            ->where('u.planExpiresAt IS NOT NULL')
            ->andWhere('u.planExpiresAt < :now')
            ->setParameter('now', new \DateTime())
            ->orderBy('u.planExpiresAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Met à jour le compteur de projets pour un utilisateur
     */
    public function updateProjectsCount(int $userId): void
    {
        $this->createQueryBuilder('u')
            ->update()
            ->set('u.currentProjectsCount', '(
                SELECT COUNT(p.id) 
                FROM App\Entity\Project p 
                WHERE p.owner = u.id AND p.isActive = true
            )')
            ->where('u.id = :userId')
            ->setParameter('userId', $userId)
            ->getQuery()
            ->execute();
    }

    /**
     * Statistiques d'inscription par mois
     */
    public function getRegistrationStats(int $months = 12): array
    {
        $date = new \DateTime();
        $date->sub(new \DateInterval('P' . $months . 'M'));

        return $this->createQueryBuilder('u')
            ->select('
                YEAR(u.createdAt) as year,
                MONTH(u.createdAt) as month,
                COUNT(u.id) as count
            ')
            ->where('u.createdAt >= :date')
            ->setParameter('date', $date)
            ->groupBy('year, month')
            ->orderBy('year, month')
            ->getQuery()
            ->getResult();
    }

    /**
     * Supprime les utilisateurs non vérifiés anciens
     */
    public function deleteUnverifiedOldUsers(int $daysOld = 30): int
    {
        $date = new \DateTime();
        $date->sub(new \DateInterval('P' . $daysOld . 'D'));

        return $this->createQueryBuilder('u')
            ->delete()
            ->where('u.isVerified = false')
            ->andWhere('u.createdAt < :date')
            ->setParameter('date', $date)
            ->getQuery()
            ->execute();
    }
}
