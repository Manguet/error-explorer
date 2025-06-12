<?php

namespace App\Repository;

use App\Entity\UptimeCheck;
use App\Entity\Project;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UptimeCheck>
 */
class UptimeCheckRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UptimeCheck::class);
    }

    /**
     * Récupère les derniers checks d'uptime pour un projet
     */
    public function findLatestByProject(Project $project, int $limit = 100): array
    {
        return $this->createQueryBuilder('uc')
            ->where('uc.project = :project')
            ->setParameter('project', $project)
            ->orderBy('uc.checkedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupère le dernier check pour un projet
     */
    public function findLatestByProjectAndUrl(Project $project, string $url): ?UptimeCheck
    {
        return $this->createQueryBuilder('uc')
            ->where('uc.project = :project')
            ->andWhere('uc.url = :url')
            ->setParameter('project', $project)
            ->setParameter('url', $url)
            ->orderBy('uc.checkedAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Calcule les statistiques d'uptime pour un projet sur une période
     */
    public function getUptimeStats(Project $project, \DateTimeInterface $since, \DateTimeInterface $until = null): array
    {
        $until = $until ?: new \DateTime();
        
        $sql = "
            SELECT 
                COUNT(*) as total_checks,
                COUNT(CASE WHEN status = 'up' THEN 1 END) as up_checks,
                COUNT(CASE WHEN status = 'down' THEN 1 END) as down_checks,
                COUNT(CASE WHEN status = 'timeout' THEN 1 END) as timeout_checks,
                AVG(CASE WHEN response_time IS NOT NULL THEN CAST(response_time AS DECIMAL(8,3)) END) as avg_response_time,
                MIN(CASE WHEN response_time IS NOT NULL THEN CAST(response_time AS DECIMAL(8,3)) END) as min_response_time,
                MAX(CASE WHEN response_time IS NOT NULL THEN CAST(response_time AS DECIMAL(8,3)) END) as max_response_time,
                COUNT(DISTINCT url) as monitored_urls
            FROM uptime_checks 
            WHERE project_id = :projectId 
                AND checked_at >= :since 
                AND checked_at <= :until
        ";

        $conn = $this->getEntityManager()->getConnection();
        $stmt = $conn->prepare($sql);
        $result = $stmt->executeQuery([
            'projectId' => $project->getId(),
            'since' => $since->format('Y-m-d H:i:s'),
            'until' => $until->format('Y-m-d H:i:s')
        ]);

        $data = $result->fetchAssociative();
        
        if (!$data || $data['total_checks'] == 0) {
            return [
                'uptime_percent' => null,
                'total_checks' => 0,
                'up_checks' => 0,
                'down_checks' => 0,
                'timeout_checks' => 0,
                'avg_response_time' => null,
                'min_response_time' => null,
                'max_response_time' => null,
                'monitored_urls' => 0
            ];
        }

        return [
            'uptime_percent' => round(($data['up_checks'] / $data['total_checks']) * 100, 2),
            'total_checks' => (int)$data['total_checks'],
            'up_checks' => (int)$data['up_checks'],
            'down_checks' => (int)$data['down_checks'],
            'timeout_checks' => (int)$data['timeout_checks'],
            'avg_response_time' => $data['avg_response_time'] ? round((float)$data['avg_response_time'], 2) : null,
            'min_response_time' => $data['min_response_time'] ? round((float)$data['min_response_time'], 2) : null,
            'max_response_time' => $data['max_response_time'] ? round((float)$data['max_response_time'], 2) : null,
            'monitored_urls' => (int)$data['monitored_urls']
        ];
    }

    /**
     * Récupère les tendances d'uptime avec regroupement temporel
     */
    public function getUptimeTrend(Project $project, int $days = 7, string $interval = 'hour'): array
    {
        $since = new \DateTime("-{$days} days");
        
        $dateFormat = match($interval) {
            'minute' => '%Y-%m-%d %H:%i:00',
            'hour' => '%Y-%m-%d %H:00:00',
            'day' => '%Y-%m-%d 00:00:00',
            default => '%Y-%m-%d %H:00:00'
        };

        $sql = "
            SELECT 
                DATE_FORMAT(checked_at, '{$dateFormat}') as time_bucket,
                COUNT(*) as total_checks,
                COUNT(CASE WHEN status = 'up' THEN 1 END) as up_checks,
                AVG(CASE WHEN response_time IS NOT NULL THEN CAST(response_time AS DECIMAL(8,3)) END) as avg_response_time
            FROM uptime_checks 
            WHERE project_id = :projectId 
                AND checked_at >= :since
            GROUP BY time_bucket
            ORDER BY time_bucket ASC
        ";

        $conn = $this->getEntityManager()->getConnection();
        $stmt = $conn->prepare($sql);
        $result = $stmt->executeQuery([
            'projectId' => $project->getId(),
            'since' => $since->format('Y-m-d H:i:s')
        ]);

        $trend = [];
        foreach ($result->fetchAllAssociative() as $row) {
            $uptimePercent = $row['total_checks'] > 0 
                ? round(($row['up_checks'] / $row['total_checks']) * 100, 2) 
                : 0;

            $trend[] = [
                'timestamp' => $row['time_bucket'],
                'uptime_percent' => $uptimePercent,
                'total_checks' => (int)$row['total_checks'],
                'up_checks' => (int)$row['up_checks'],
                'avg_response_time' => $row['avg_response_time'] ? round((float)$row['avg_response_time'], 2) : null
            ];
        }

        return $trend;
    }

    /**
     * Récupère les incidents d'uptime (périodes de downtime)
     */
    public function findDowntimeIncidents(Project $project, \DateTimeInterface $since, int $limit = 50): array
    {
        $sql = "
            SELECT 
                uc.*,
                LAG(status) OVER (PARTITION BY url ORDER BY checked_at) as previous_status,
                LEAD(status) OVER (PARTITION BY url ORDER BY checked_at) as next_status
            FROM uptime_checks uc
            WHERE uc.project_id = :projectId 
                AND uc.checked_at >= :since
                AND uc.status IN ('down', 'timeout', 'error')
            ORDER BY uc.checked_at DESC
            LIMIT :limit
        ";

        $conn = $this->getEntityManager()->getConnection();
        $stmt = $conn->prepare($sql);
        $result = $stmt->executeQuery([
            'projectId' => $project->getId(),
            'since' => $since->format('Y-m-d H:i:s'),
            'limit' => $limit
        ]);

        $incidents = [];
        foreach ($result->fetchAllAssociative() as $row) {
            $check = $this->find($row['id']);
            if ($check) {
                $incidents[] = [
                    'check' => $check,
                    'is_start_of_incident' => $row['previous_status'] === 'up',
                    'is_end_of_incident' => $row['next_status'] === 'up'
                ];
            }
        }

        return $incidents;
    }

    /**
     * Récupère les checks avec des problèmes de performance
     */
    public function findPerformanceIssues(Project $project = null, \DateTimeInterface $since = null, int $limit = 100): array
    {
        $since = $since ?: new \DateTime('-24 hours');
        
        $qb = $this->createQueryBuilder('uc')
            ->where('uc.checkedAt >= :since')
            ->andWhere('(uc.status != :up OR uc.responseTime > :slowThreshold)')
            ->setParameter('since', $since)
            ->setParameter('up', 'up')
            ->setParameter('slowThreshold', '2000') // Plus de 2 secondes
            ->orderBy('uc.checkedAt', 'DESC')
            ->setMaxResults($limit);

        if ($project) {
            $qb->andWhere('uc.project = :project')
               ->setParameter('project', $project);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Récupère le statut actuel de tous les projets d'un utilisateur
     */
    public function getCurrentStatusByUser(User $user): array
    {
        $sql = "
            SELECT 
                p.id as project_id,
                p.name as project_name,
                p.slug as project_slug,
                uc.url,
                uc.status,
                uc.http_status_code,
                uc.response_time,
                uc.checked_at,
                uc.error_message
            FROM projects p
            LEFT JOIN (
                SELECT DISTINCT 
                    uc1.project_id,
                    uc1.url,
                    uc1.status,
                    uc1.http_status_code,
                    uc1.response_time,
                    uc1.checked_at,
                    uc1.error_message
                FROM uptime_checks uc1
                INNER JOIN (
                    SELECT project_id, url, MAX(checked_at) as max_checked_at
                    FROM uptime_checks
                    WHERE checked_at >= DATE_SUB(NOW(), INTERVAL 30 MINUTE)
                    GROUP BY project_id, url
                ) uc2 ON uc1.project_id = uc2.project_id 
                    AND uc1.url = uc2.url 
                    AND uc1.checked_at = uc2.max_checked_at
            ) uc ON p.id = uc.project_id
            WHERE p.owner_id = :userId AND p.is_active = 1
            ORDER BY p.name, uc.url
        ";

        $conn = $this->getEntityManager()->getConnection();
        $stmt = $conn->prepare($sql);
        $result = $stmt->executeQuery(['userId' => $user->getId()]);

        $projects = [];
        foreach ($result->fetchAllAssociative() as $row) {
            $projectId = (int)$row['project_id'];
            
            if (!isset($projects[$projectId])) {
                $projects[$projectId] = [
                    'project_id' => $projectId,
                    'project_name' => $row['project_name'],
                    'project_slug' => $row['project_slug'],
                    'urls' => [],
                    'overall_status' => 'unknown'
                ];
            }

            if ($row['url']) {
                $projects[$projectId]['urls'][] = [
                    'url' => $row['url'],
                    'status' => $row['status'],
                    'http_status_code' => $row['http_status_code'],
                    'response_time' => $row['response_time'] ? round((float)$row['response_time'], 2) : null,
                    'checked_at' => $row['checked_at'],
                    'error_message' => $row['error_message']
                ];
            }
        }

        // Calculer le statut global de chaque projet
        foreach ($projects as &$project) {
            if (empty($project['urls'])) {
                $project['overall_status'] = 'no_monitoring';
            } else {
                $allUp = true;
                $anyDown = false;
                
                foreach ($project['urls'] as $url) {
                    if ($url['status'] !== 'up') {
                        $allUp = false;
                        if ($url['status'] === 'down') {
                            $anyDown = true;
                        }
                    }
                }
                
                $project['overall_status'] = $anyDown ? 'down' : ($allUp ? 'up' : 'warning');
            }
        }

        return array_values($projects);
    }

    /**
     * Calcule l'uptime global pour tous les projets d'un utilisateur
     */
    public function getGlobalUptimeStats(User $user, \DateTimeInterface $since): array
    {
        $sql = "
            SELECT 
                COUNT(*) as total_checks,
                COUNT(CASE WHEN uc.status = 'up' THEN 1 END) as up_checks,
                COUNT(DISTINCT p.id) as monitored_projects,
                COUNT(DISTINCT uc.url) as monitored_urls,
                AVG(CASE WHEN uc.response_time IS NOT NULL THEN CAST(uc.response_time AS DECIMAL(8,3)) END) as avg_response_time
            FROM uptime_checks uc
            JOIN projects p ON uc.project_id = p.id
            WHERE p.owner_id = :userId 
                AND uc.checked_at >= :since
        ";

        $conn = $this->getEntityManager()->getConnection();
        $stmt = $conn->prepare($sql);
        $result = $stmt->executeQuery([
            'userId' => $user->getId(),
            'since' => $since->format('Y-m-d H:i:s')
        ]);

        $data = $result->fetchAssociative();
        
        if (!$data || $data['total_checks'] == 0) {
            return [
                'uptime_percent' => null,
                'total_checks' => 0,
                'monitored_projects' => 0,
                'monitored_urls' => 0,
                'avg_response_time' => null
            ];
        }

        return [
            'uptime_percent' => round(($data['up_checks'] / $data['total_checks']) * 100, 2),
            'total_checks' => (int)$data['total_checks'],
            'monitored_projects' => (int)$data['monitored_projects'],
            'monitored_urls' => (int)$data['monitored_urls'],
            'avg_response_time' => $data['avg_response_time'] ? round((float)$data['avg_response_time'], 2) : null
        ];
    }

    /**
     * Nettoie les anciens checks d'uptime (pour maintenance)
     */
    public function cleanupOldChecks(int $daysToKeep = 90): int
    {
        $cutoffDate = new \DateTime("-{$daysToKeep} days");
        
        $qb = $this->createQueryBuilder('uc')
            ->delete()
            ->where('uc.checkedAt < :cutoff')
            ->setParameter('cutoff', $cutoffDate);

        return $qb->getQuery()->execute();
    }
}