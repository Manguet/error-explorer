<?php

namespace App\Repository;

use App\Entity\PerformanceMetric;
use App\Entity\Project;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PerformanceMetric>
 */
class PerformanceMetricRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PerformanceMetric::class);
    }

    /**
     * Récupère les métriques de performance pour un projet donné
     */
    public function findByProject(Project $project, \DateTimeInterface $since = null, int $limit = 1000): array
    {
        $qb = $this->createQueryBuilder('pm')
            ->where('pm.project = :project')
            ->setParameter('project', $project)
            ->orderBy('pm.recordedAt', 'DESC')
            ->setMaxResults($limit);

        if ($since) {
            $qb->andWhere('pm.recordedAt >= :since')
               ->setParameter('since', $since);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Récupère les métriques par type pour un projet
     */
    public function findByProjectAndType(Project $project, string $metricType, \DateTimeInterface $since = null, int $limit = 1000): array
    {
        $qb = $this->createQueryBuilder('pm')
            ->where('pm.project = :project')
            ->andWhere('pm.metricType = :metricType')
            ->setParameter('project', $project)
            ->setParameter('metricType', $metricType)
            ->orderBy('pm.recordedAt', 'DESC')
            ->setMaxResults($limit);

        if ($since) {
            $qb->andWhere('pm.recordedAt >= :since')
               ->setParameter('since', $since);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Récupère les métriques pour tous les projets d'un utilisateur
     */
    public function findByUser(User $user, \DateTimeInterface $since = null, int $limit = 1000): array
    {
        $qb = $this->createQueryBuilder('pm')
            ->join('pm.project', 'p')
            ->where('p.owner = :user')
            ->setParameter('user', $user)
            ->orderBy('pm.recordedAt', 'DESC')
            ->setMaxResults($limit);

        if ($since) {
            $qb->andWhere('pm.recordedAt >= :since')
               ->setParameter('since', $since);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Calcule les statistiques moyennes pour un projet sur une période
     */
    public function getAverageMetrics(Project $project, \DateTimeInterface $since, \DateTimeInterface $until = null): array
    {
        $until = $until ?: new \DateTime();
        
        $sql = "
            SELECT 
                metric_type,
                AVG(CAST(value AS DECIMAL(10,3))) as avg_value,
                MIN(CAST(value AS DECIMAL(10,3))) as min_value,
                MAX(CAST(value AS DECIMAL(10,3))) as max_value,
                COUNT(*) as measurement_count,
                unit
            FROM performance_metrics 
            WHERE project_id = :projectId 
                AND recorded_at >= :since 
                AND recorded_at <= :until
            GROUP BY metric_type, unit
            ORDER BY metric_type
        ";

        $conn = $this->getEntityManager()->getConnection();
        $stmt = $conn->prepare($sql);
        $result = $stmt->executeQuery([
            'projectId' => $project->getId(),
            'since' => $since->format('Y-m-d H:i:s'),
            'until' => $until->format('Y-m-d H:i:s')
        ]);

        $metrics = [];
        foreach ($result->fetchAllAssociative() as $row) {
            $metrics[$row['metric_type']] = [
                'avg' => round((float)$row['avg_value'], 3),
                'min' => round((float)$row['min_value'], 3),
                'max' => round((float)$row['max_value'], 3),
                'count' => (int)$row['measurement_count'],
                'unit' => $row['unit']
            ];
        }

        return $metrics;
    }

    /**
     * Récupère les tendances des métriques sur une période avec regroupement temporel
     */
    public function getMetricTrend(Project $project, string $metricType, int $days = 7, string $interval = 'hour'): array
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
                DATE_FORMAT(recorded_at, '{$dateFormat}') as time_bucket,
                AVG(CAST(value AS DECIMAL(10,3))) as avg_value,
                MIN(CAST(value AS DECIMAL(10,3))) as min_value,
                MAX(CAST(value AS DECIMAL(10,3))) as max_value,
                COUNT(*) as measurement_count
            FROM performance_metrics 
            WHERE project_id = :projectId 
                AND metric_type = :metricType
                AND recorded_at >= :since
            GROUP BY time_bucket
            ORDER BY time_bucket ASC
        ";

        $conn = $this->getEntityManager()->getConnection();
        $stmt = $conn->prepare($sql);
        $result = $stmt->executeQuery([
            'projectId' => $project->getId(),
            'metricType' => $metricType,
            'since' => $since->format('Y-m-d H:i:s')
        ]);

        return $result->fetchAllAssociative();
    }

    /**
     * Récupère les métriques avec des problèmes de performance
     */
    public function findPerformanceIssues(Project $project = null, \DateTimeInterface $since = null, int $limit = 100): array
    {
        $since = $since ?: new \DateTime('-24 hours');
        
        $sql = "
            SELECT pm.* 
            FROM performance_metrics pm
            JOIN projects p ON pm.project_id = p.id
            WHERE pm.recorded_at >= :since
                AND (
                    (pm.metric_type = 'response_time' AND CAST(pm.value AS DECIMAL(10,3)) > 1000)
                    OR (pm.metric_type = 'error_rate' AND CAST(pm.value AS DECIMAL(10,3)) > 5)
                    OR (pm.metric_type = 'cpu_usage' AND CAST(pm.value AS DECIMAL(10,3)) > 80)
                    OR (pm.metric_type = 'memory_usage' AND CAST(pm.value AS DECIMAL(10,3)) > 1024)
                    OR (pm.metric_type = 'db_query_time' AND CAST(pm.value AS DECIMAL(10,3)) > 500)
                    OR (pm.metric_type = 'uptime' AND CAST(pm.value AS DECIMAL(10,3)) < 1)
                )
        ";

        $params = ['since' => $since->format('Y-m-d H:i:s')];

        if ($project) {
            $sql .= " AND pm.project_id = :projectId";
            $params['projectId'] = $project->getId();
        }

        $sql .= " ORDER BY pm.recorded_at DESC LIMIT :limit";
        $params['limit'] = $limit;

        $conn = $this->getEntityManager()->getConnection();
        $stmt = $conn->prepare($sql);
        $result = $stmt->executeQuery($params);

        $metrics = [];
        foreach ($result->fetchAllAssociative() as $row) {
            $metric = $this->find($row['id']);
            if ($metric) {
                $metrics[] = $metric;
            }
        }

        return $metrics;
    }

    /**
     * Récupère le statut de santé global des projets
     */
    public function getProjectsHealthStatus(User $user): array
    {
        $since = new \DateTime('-1 hour'); // Dernière heure
        
        $sql = "
            SELECT 
                p.id as project_id,
                p.name as project_name,
                p.slug as project_slug,
                COUNT(CASE WHEN pm.metric_type = 'uptime' AND CAST(pm.value AS DECIMAL(10,3)) >= 1 THEN 1 END) as uptime_count,
                COUNT(CASE WHEN pm.metric_type = 'uptime' THEN 1 END) as total_uptime_checks,
                AVG(CASE WHEN pm.metric_type = 'response_time' THEN CAST(pm.value AS DECIMAL(10,3)) END) as avg_response_time,
                AVG(CASE WHEN pm.metric_type = 'error_rate' THEN CAST(pm.value AS DECIMAL(10,3)) END) as avg_error_rate,
                MAX(CASE WHEN pm.metric_type = 'cpu_usage' THEN CAST(pm.value AS DECIMAL(10,3)) END) as max_cpu_usage,
                MAX(CASE WHEN pm.metric_type = 'memory_usage' THEN CAST(pm.value AS DECIMAL(10,3)) END) as max_memory_usage
            FROM projects p
            LEFT JOIN performance_metrics pm ON p.id = pm.project_id AND pm.recorded_at >= :since
            WHERE p.owner_id = :userId AND p.is_active = 1
            GROUP BY p.id, p.name, p.slug
            ORDER BY p.name
        ";

        $conn = $this->getEntityManager()->getConnection();
        $stmt = $conn->prepare($sql);
        $result = $stmt->executeQuery([
            'userId' => $user->getId(),
            'since' => $since->format('Y-m-d H:i:s')
        ]);

        $projects = [];
        foreach ($result->fetchAllAssociative() as $row) {
            $uptimePercent = $row['total_uptime_checks'] > 0 
                ? ($row['uptime_count'] / $row['total_uptime_checks']) * 100 
                : null;

            $healthScore = $this->calculateHealthScore(
                $uptimePercent,
                $row['avg_response_time'],
                $row['avg_error_rate'],
                $row['max_cpu_usage'],
                $row['max_memory_usage']
            );

            $projects[] = [
                'project_id' => (int)$row['project_id'],
                'project_name' => $row['project_name'],
                'project_slug' => $row['project_slug'],
                'uptime_percent' => $uptimePercent,
                'avg_response_time' => $row['avg_response_time'] ? round((float)$row['avg_response_time'], 2) : null,
                'avg_error_rate' => $row['avg_error_rate'] ? round((float)$row['avg_error_rate'], 2) : null,
                'max_cpu_usage' => $row['max_cpu_usage'] ? round((float)$row['max_cpu_usage'], 2) : null,
                'max_memory_usage' => $row['max_memory_usage'] ? round((float)$row['max_memory_usage'], 2) : null,
                'health_score' => $healthScore,
                'status' => $this->getStatusFromHealthScore($healthScore)
            ];
        }

        return $projects;
    }

    /**
     * Calcule un score de santé basé sur les métriques
     */
    private function calculateHealthScore(?float $uptime, ?float $responseTime, ?float $errorRate, ?float $cpuUsage, ?float $memoryUsage): int
    {
        $score = 100;

        // Pénalités basées sur les métriques
        if ($uptime !== null && $uptime < 100) {
            $score -= (100 - $uptime); // Pénalité directe pour downtime
        }

        if ($responseTime !== null && $responseTime > 500) {
            $score -= min(30, ($responseTime - 500) / 100); // Pénalité pour temps de réponse lent
        }

        if ($errorRate !== null && $errorRate > 1) {
            $score -= min(40, $errorRate * 5); // Pénalité pour taux d'erreur élevé
        }

        if ($cpuUsage !== null && $cpuUsage > 70) {
            $score -= min(20, ($cpuUsage - 70) / 2); // Pénalité pour CPU élevé
        }

        if ($memoryUsage !== null && $memoryUsage > 512) {
            $score -= min(15, ($memoryUsage - 512) / 100); // Pénalité pour mémoire élevée
        }

        return max(0, min(100, (int)round($score)));
    }

    /**
     * Détermine le statut basé sur le score de santé
     */
    private function getStatusFromHealthScore(int $healthScore): string
    {
        return match (true) {
            $healthScore >= 90 => 'excellent',
            $healthScore >= 75 => 'good',
            $healthScore >= 50 => 'warning',
            $healthScore >= 25 => 'critical',
            default => 'down'
        };
    }

    /**
     * Nettoie les anciennes métriques (pour maintenance)
     */
    public function cleanupOldMetrics(int $daysToKeep = 90): int
    {
        $cutoffDate = new \DateTime("-{$daysToKeep} days");
        
        $qb = $this->createQueryBuilder('pm')
            ->delete()
            ->where('pm.recordedAt < :cutoff')
            ->setParameter('cutoff', $cutoffDate);

        return $qb->getQuery()->execute();
    }

    /**
     * Compte les métriques avec des filtres
     */
    public function countWithFilters(array $filters = []): int
    {
        $qb = $this->createQueryBuilder('pm')
            ->select('COUNT(pm.id)');

        if (isset($filters['project'])) {
            $qb->andWhere('pm.project = :project')
               ->setParameter('project', $filters['project']);
        }

        if (isset($filters['metricType'])) {
            $qb->andWhere('pm.metricType = :metricType')
               ->setParameter('metricType', $filters['metricType']);
        }

        if (isset($filters['since'])) {
            $qb->andWhere('pm.recordedAt >= :since')
               ->setParameter('since', $filters['since']);
        }

        if (isset($filters['until'])) {
            $qb->andWhere('pm.recordedAt <= :until')
               ->setParameter('until', $filters['until']);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }
}