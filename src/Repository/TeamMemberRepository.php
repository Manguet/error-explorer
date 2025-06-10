<?php

namespace App\Repository;

use App\Entity\Team;
use App\Entity\TeamMember;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TeamMember>
 */
class TeamMemberRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TeamMember::class);
    }

    public function save(TeamMember $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(TeamMember $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Find a specific team membership
     */
    public function findByTeamAndUser(Team $team, User $user): ?TeamMember
    {
        return $this->createQueryBuilder('tm')
            ->where('tm.team = :team')
            ->andWhere('tm.user = :user')
            ->setParameter('team', $team)
            ->setParameter('user', $user)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find active members of a team
     */
    public function findActiveByTeam(Team $team): array
    {
        return $this->createQueryBuilder('tm')
            ->join('tm.user', 'u')
            ->where('tm.team = :team')
            ->andWhere('tm.isActive = true')
            ->andWhere('u.isActive = true')
            ->setParameter('team', $team)
            ->orderBy('tm.joinedAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find all memberships for a user
     */
    public function findByUser(User $user): array
    {
        return $this->createQueryBuilder('tm')
            ->join('tm.team', 't')
            ->where('tm.user = :user')
            ->andWhere('tm.isActive = true')
            ->andWhere('t.isActive = true')
            ->setParameter('user', $user)
            ->orderBy('tm.joinedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find members by role
     */
    public function findByTeamAndRole(Team $team, string $role): array
    {
        return $this->createQueryBuilder('tm')
            ->join('tm.user', 'u')
            ->where('tm.team = :team')
            ->andWhere('tm.role = :role')
            ->andWhere('tm.isActive = true')
            ->andWhere('u.isActive = true')
            ->setParameter('team', $team)
            ->setParameter('role', $role)
            ->orderBy('tm.joinedAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find team admins (owners and admins)
     */
    public function findAdminsByTeam(Team $team): array
    {
        return $this->createQueryBuilder('tm')
            ->join('tm.user', 'u')
            ->where('tm.team = :team')
            ->andWhere('tm.role IN (:roles)')
            ->andWhere('tm.isActive = true')
            ->andWhere('u.isActive = true')
            ->setParameter('team', $team)
            ->setParameter('roles', [TeamMember::ROLE_OWNER, TeamMember::ROLE_ADMIN])
            ->orderBy('tm.role', 'ASC')
            ->addOrderBy('tm.joinedAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Count active members by team
     */
    public function countActiveByTeam(Team $team): int
    {
        return $this->createQueryBuilder('tm')
            ->select('COUNT(tm.id)')
            ->join('tm.user', 'u')
            ->where('tm.team = :team')
            ->andWhere('tm.isActive = true')
            ->andWhere('u.isActive = true')
            ->setParameter('team', $team)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Find recently active members
     */
    public function findRecentlyActiveByTeam(Team $team, int $days = 7): array
    {
        $threshold = new \DateTime("-{$days} days");

        return $this->createQueryBuilder('tm')
            ->join('tm.user', 'u')
            ->where('tm.team = :team')
            ->andWhere('tm.isActive = true')
            ->andWhere('u.isActive = true')
            ->andWhere('tm.lastActivityAt >= :threshold')
            ->setParameter('team', $team)
            ->setParameter('threshold', $threshold)
            ->orderBy('tm.lastActivityAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find inactive members
     */
    public function findInactiveByTeam(Team $team, int $days = 30): array
    {
        $threshold = new \DateTime("-{$days} days");

        return $this->createQueryBuilder('tm')
            ->join('tm.user', 'u')
            ->where('tm.team = :team')
            ->andWhere('tm.isActive = true')
            ->andWhere('u.isActive = true')
            ->andWhere('tm.lastActivityAt < :threshold OR tm.lastActivityAt IS NULL')
            ->setParameter('team', $team)
            ->setParameter('threshold', $threshold)
            ->orderBy('tm.lastActivityAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Search members by name or email
     */
    public function searchByTeam(Team $team, string $query): array
    {
        return $this->createQueryBuilder('tm')
            ->join('tm.user', 'u')
            ->where('tm.team = :team')
            ->andWhere('tm.isActive = true')
            ->andWhere('u.isActive = true')
            ->andWhere('u.firstName LIKE :query OR u.lastName LIKE :query OR u.email LIKE :query')
            ->setParameter('team', $team)
            ->setParameter('query', '%' . $query . '%')
            ->orderBy('u.firstName', 'ASC')
            ->addOrderBy('u.lastName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get member statistics for a team
     */
    public function getTeamMemberStats(Team $team): array
    {
        $result = $this->createQueryBuilder('tm')
            ->select([
                'COUNT(tm.id) as totalMembers',
                'COUNT(CASE WHEN tm.role = :owner THEN 1 ELSE 0 END) as ownerCount',
                'COUNT(CASE WHEN tm.role = :admin THEN 1 ELSE 0 END) as adminCount',
                'COUNT(CASE WHEN tm.role = :member THEN 1 ELSE 0 END) as memberCount',
                'COUNT(CASE WHEN tm.role = :viewer THEN 1 ELSE 0 END) as viewerCount',
                'COUNT(CASE WHEN tm.lastActivityAt >= :recentThreshold THEN 1 ELSE 0 END) as recentlyActiveCount',
                'AVG(DATEDIFF(CURRENT_DATE(), tm.joinedAt)) as avgDaysSinceJoined'
            ])
            ->where('tm.team = :team')
            ->andWhere('tm.isActive = true')
            ->setParameter('team', $team)
            ->setParameter('owner', TeamMember::ROLE_OWNER)
            ->setParameter('admin', TeamMember::ROLE_ADMIN)
            ->setParameter('member', TeamMember::ROLE_MEMBER)
            ->setParameter('viewer', TeamMember::ROLE_VIEWER)
            ->setParameter('recentThreshold', new \DateTime('-7 days'))
            ->getQuery()
            ->getSingleResult();

        return [
            'total_members' => (int) $result['totalMembers'],
            'owner_count' => (int) $result['ownerCount'],
            'admin_count' => (int) $result['adminCount'],
            'member_count' => (int) $result['memberCount'],
            'viewer_count' => (int) $result['viewerCount'],
            'recently_active_count' => (int) $result['recentlyActiveCount'],
            'avg_days_since_joined' => round((float) $result['avgDaysSinceJoined'], 1),
        ];
    }

    /**
     * Find members with specific permissions
     */
    public function findByTeamAndPermission(Team $team, string $permission): array
    {
        // Since permissions are role-based, we need to check which roles have this permission
        $rolesWithPermission = [];

        foreach (TeamMember::ROLES as $role => $label) {
            $tempMember = new TeamMember();
            $tempMember->setRole($role);
            if ($tempMember->hasPermission($permission)) {
                $rolesWithPermission[] = $role;
            }
        }

        return $this->createQueryBuilder('tm')
            ->join('tm.user', 'u')
            ->where('tm.team = :team')
            ->andWhere('tm.isActive = true')
            ->andWhere('u.isActive = true')
            ->andWhere('tm.role IN (:roles)')
            ->setParameter('team', $team)
            ->setParameter('roles', $rolesWithPermission)
            ->orderBy('tm.role', 'ASC')
            ->addOrderBy('tm.joinedAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get member activity statistics
     */
    public function getMemberActivityStats(int $days = 30): array
    {
        $startDate = new \DateTime("-{$days} days");

        return $this->createQueryBuilder('tm')
            ->select([
                'DATE(tm.lastActivityAt) as activityDate',
                'COUNT(DISTINCT tm.id) as activeMembers'
            ])
            ->where('tm.lastActivityAt >= :startDate')
            ->andWhere('tm.isActive = true')
            ->setParameter('startDate', $startDate)
            ->groupBy('activityDate')
            ->orderBy('activityDate', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
