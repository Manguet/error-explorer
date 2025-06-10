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
}
