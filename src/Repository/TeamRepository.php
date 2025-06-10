<?php

namespace App\Repository;

use App\Entity\Team;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Team>
 */
class TeamRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Team::class);
    }

    public function save(Team $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Team $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * @return Team[] Returns an array of Team objects that the user owns or is a member of
     */
    public function findByUser(User $user): array
    {
        return $this->createQueryBuilder('t')
            ->leftJoin('t.members', 'm')
            ->where('t.owner = :user')
            ->orWhere('m.user = :user AND m.isActive = true')
            ->andWhere('t.isActive = true')
            ->setParameter('user', $user)
            ->orderBy('t.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Team[] Returns teams owned by the user
     */
    public function findOwnedByUser(User $user): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.owner = :user')
            ->andWhere('t.isActive = true')
            ->setParameter('user', $user)
            ->orderBy('t.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Team[] Returns teams where user is a member (not owner)
     */
    public function findMembershipsByUser(User $user): array
    {
        return $this->createQueryBuilder('t')
            ->join('t.members', 'm')
            ->where('m.user = :user')
            ->andWhere('m.isActive = true')
            ->andWhere('t.isActive = true')
            ->andWhere('t.owner != :user')
            ->setParameter('user', $user)
            ->orderBy('t.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findBySlug(string $slug): ?Team
    {
        return $this->createQueryBuilder('t')
            ->where('t.slug = :slug')
            ->andWhere('t.isActive = true')
            ->setParameter('slug', $slug)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find teams with statistics
     */
    public function findAllWithStats(): array
    {
        return $this->createQueryBuilder('t')
            ->select('t, COUNT(DISTINCT m.id) as memberCount, COUNT(DISTINCT p.id) as projectCount')
            ->leftJoin('t.members', 'm')
            ->leftJoin('t.projects', 'p')
            ->where('t.isActive = true')
            ->groupBy('t.id')
            ->orderBy('t.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Search teams by name or description
     */
    public function search(string $query, ?User $user = null): array
    {
        $qb = $this->createQueryBuilder('t')
            ->where('t.isActive = true')
            ->andWhere('t.name LIKE :query OR t.description LIKE :query')
            ->setParameter('query', '%' . $query . '%');

        if ($user) {
            $qb->leftJoin('t.members', 'm')
                ->andWhere('t.owner = :user OR (m.user = :user AND m.isActive = true)')
                ->setParameter('user', $user);
        }

        return $qb->orderBy('t.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get team statistics
     */
    public function getTeamStats(Team $team): array
    {
        $result = $this->createQueryBuilder('t')
            ->select([
                'COUNT(DISTINCT m.id) as memberCount',
                'COUNT(DISTINCT p.id) as projectCount',
                'COUNT(DISTINCT CASE WHEN m.lastActivityAt >= :recentThreshold THEN m.id ELSE 0 END) as recentlyActiveMemberCount'
            ])
            ->leftJoin('t.members', 'm')
            ->leftJoin('t.projects', 'p')
            ->where('t.id = :teamId')
            ->setParameter('teamId', $team->getId())
            ->setParameter('recentThreshold', new \DateTime('-7 days'))
            ->getQuery()
            ->getSingleResult();

        return [
            'member_count' => (int) $result['memberCount'],
            'project_count' => (int) $result['projectCount'],
            'recently_active_member_count' => (int) $result['recentlyActiveMemberCount'],
        ];
    }

    /**
     * Find teams that need attention (inactive members, no recent activity, etc.)
     */
    public function findTeamsNeedingAttention(?User $owner = null): array
    {
        $qb = $this->createQueryBuilder('t')
            ->leftJoin('t.members', 'm')
            ->where('t.isActive = true')
            ->having('COUNT(m.id) = 0 OR MAX(m.lastActivityAt) < :inactiveThreshold')
            ->setParameter('inactiveThreshold', new \DateTime('-30 days'))
            ->groupBy('t.id');

        if ($owner) {
            $qb->andWhere('t.owner = :owner')
                ->setParameter('owner', $owner);
        }

        return $qb->orderBy('t.updatedAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get monthly team creation stats
     */
    public function getMonthlyCreationStats(int $months = 12): array
    {
        $startDate = new \DateTime("-{$months} months");

        $result = $this->createQueryBuilder('t')
            ->select([
                'YEAR(t.createdAt) as year',
                'MONTH(t.createdAt) as month',
                'COUNT(t.id) as count'
            ])
            ->where('t.createdAt >= :startDate')
            ->setParameter('startDate', $startDate)
            ->groupBy('year, month')
            ->orderBy('year, month')
            ->getQuery()
            ->getResult();

        return $result;
    }
}
