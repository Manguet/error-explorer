<?php

namespace App\Service\Email;

use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Service de monitoring et de métriques pour les emails
 */
class EmailMonitoringService
{
    private const METRICS_CACHE_TTL = 300; // 5 minutes
    private const STATS_CACHE_TTL = 1800;  // 30 minutes

    public function __construct(
        private readonly CacheInterface $cache,
        private readonly Connection $connection,
        private readonly LoggerInterface $logger,
        private readonly bool $enableMetrics = true
    ) {}

    /**
     * Enregistre une métrique d'envoi d'email
     */
    public function recordEmailSent(
        string $type,
        string $recipientEmail,
        bool $success,
        int $attempts,
        float $executionTimeMs,
        EmailPriority $priority,
        array $metadata = []
    ): void {
        if (!$this->enableMetrics) {
            return;
        }

        try {
            $this->connection->insert('email_metrics', [
                'type' => $type,
                'recipient_email' => $recipientEmail,
                'success' => $success ? 1 : 0,
                'attempts' => $attempts,
                'execution_time_ms' => $executionTimeMs,
                'priority' => $priority->value,
                'metadata' => json_encode($metadata),
                'sent_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s')
            ]);

            $this->cache->delete('email_stats_summary');

        } catch (\Exception $e) {
            $this->logger->error('Erreur enregistrement métrique email', [
                'type' => $type,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Obtient les statistiques globales d'envoi
     */
    public function getGlobalStats(\DateTimeInterface $since = null): array
    {
        $since = $since ?? new \DateTimeImmutable('-30 days');
        $cacheKey = 'email_stats_global_' . $since->format('Y-m-d');

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($since) {
            $item->expiresAfter(self::STATS_CACHE_TTL);

            $sql = "
                SELECT 
                    COUNT(*) as total_sent,
                    SUM(success) as total_success,
                    AVG(attempts) as avg_attempts,
                    AVG(execution_time_ms) as avg_execution_time,
                    MAX(execution_time_ms) as max_execution_time,
                    MIN(execution_time_ms) as min_execution_time
                FROM email_metrics 
                WHERE sent_at >= :since
            ";

            $result = $this->connection->fetchAssociative($sql, [
                'since' => $since->format('Y-m-d H:i:s')
            ]);

            if (!$result || (int)$result['total_sent'] === 0) {
                return [
                    'total_sent' => 0,
                    'success_rate' => 0.0,
                    'avg_attempts' => 0.0,
                    'avg_execution_time_ms' => 0.0,
                    'max_execution_time_ms' => 0.0,
                    'min_execution_time_ms' => 0.0
                ];
            }

            return [
                'total_sent' => (int) $result['total_sent'],
                'success_rate' => round(($result['total_success'] / $result['total_sent']) * 100, 2),
                'avg_attempts' => round($result['avg_attempts'], 2),
                'avg_execution_time_ms' => round($result['avg_execution_time'], 2),
                'max_execution_time_ms' => round($result['max_execution_time'], 2),
                'min_execution_time_ms' => round($result['min_execution_time'], 2)
            ];
        });
    }

    /**
     * Obtient les statistiques par type d'email
     */
    public function getStatsByType(\DateTimeInterface $since = null): array
    {
        $since = $since ?? new \DateTimeImmutable('-7 days');
        $cacheKey = 'email_stats_by_type_' . $since->format('Y-m-d');

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($since) {
            $item->expiresAfter(self::STATS_CACHE_TTL);

            $sql = "
                SELECT 
                    type,
                    COUNT(*) as total_sent,
                    SUM(success) as total_success,
                    AVG(attempts) as avg_attempts,
                    AVG(execution_time_ms) as avg_execution_time
                FROM email_metrics 
                WHERE sent_at >= :since
                GROUP BY type
                ORDER BY total_sent DESC
            ";

            $results = $this->connection->fetchAllAssociative($sql, [
                'since' => $since->format('Y-m-d H:i:s')
            ]);

            $stats = [];
            foreach ($results as $result) {
                $stats[$result['type']] = [
                    'total_sent' => (int) $result['total_sent'],
                    'success_rate' => round(($result['total_success'] / $result['total_sent']) * 100, 2),
                    'avg_attempts' => round($result['avg_attempts'], 2),
                    'avg_execution_time_ms' => round($result['avg_execution_time'], 2)
                ];
            }

            return $stats;
        });
    }

    /**
     * Obtient les statistiques par priorité
     */
    public function getStatsByPriority(\DateTimeInterface $since = null): array
    {
        $since = $since ?? new \DateTimeImmutable('-7 days');
        $cacheKey = 'email_stats_by_priority_' . $since->format('Y-m-d');

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($since) {
            $item->expiresAfter(self::STATS_CACHE_TTL);

            $sql = "
                SELECT 
                    priority,
                    COUNT(*) as total_sent,
                    SUM(success) as total_success,
                    AVG(attempts) as avg_attempts,
                    AVG(execution_time_ms) as avg_execution_time
                FROM email_metrics 
                WHERE sent_at >= :since
                GROUP BY priority
                ORDER BY 
                    CASE priority 
                        WHEN 'high' THEN 1 
                        WHEN 'normal' THEN 2 
                        WHEN 'low' THEN 3 
                    END
            ";

            $results = $this->connection->fetchAllAssociative($sql, [
                'since' => $since->format('Y-m-d H:i:s')
            ]);

            $stats = [];
            foreach ($results as $result) {
                $stats[$result['priority']] = [
                    'total_sent' => (int) $result['total_sent'],
                    'success_rate' => round(($result['total_success'] / $result['total_sent']) * 100, 2),
                    'avg_attempts' => round($result['avg_attempts'], 2),
                    'avg_execution_time_ms' => round($result['avg_execution_time'], 2)
                ];
            }

            return $stats;
        });
    }

    /**
     * Obtient les emails en échec
     */
    public function getFailedEmails(int $limit = 50): array
    {
        $sql = "
            SELECT 
                type,
                recipient_email,
                attempts,
                execution_time_ms,
                metadata,
                sent_at
            FROM email_metrics 
            WHERE success = 0
            ORDER BY sent_at DESC
            LIMIT :limit
        ";

        $results = $this->connection->fetchAllAssociative($sql, ['limit' => $limit]);

        return array_map(function ($result) {
            $result['metadata'] = json_decode($result['metadata'], true) ?? [];
            return $result;
        }, $results);
    }

    /**
     * Obtient les tendances d'envoi par heure
     */
    public function getHourlyTrends(\DateTimeInterface $date = null): array
    {
        $date = $date ?? new \DateTimeImmutable();
        $cacheKey = 'email_hourly_trends_' . $date->format('Y-m-d');

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($date) {
            $item->expiresAfter(self::METRICS_CACHE_TTL);

            $sql = "
                SELECT 
                    HOUR(sent_at) as hour,
                    COUNT(*) as total_sent,
                    SUM(success) as total_success,
                    AVG(execution_time_ms) as avg_execution_time
                FROM email_metrics 
                WHERE DATE(sent_at) = :date
                GROUP BY HOUR(sent_at)
                ORDER BY hour
            ";

            $results = $this->connection->fetchAllAssociative($sql, [
                'date' => $date->format('Y-m-d')
            ]);

            // Créer un tableau avec toutes les heures (0-23)
            $trends = [];
            for ($hour = 0; $hour < 24; $hour++) {
                $trends[$hour] = [
                    'hour' => $hour,
                    'total_sent' => 0,
                    'success_rate' => 0.0,
                    'avg_execution_time_ms' => 0.0
                ];
            }

            // Remplir avec les données réelles
            foreach ($results as $result) {
                $hour = (int) $result['hour'];
                $trends[$hour] = [
                    'hour' => $hour,
                    'total_sent' => (int) $result['total_sent'],
                    'success_rate' => $result['total_sent'] > 0
                        ? round(($result['total_success'] / $result['total_sent']) * 100, 2)
                        : 0.0,
                    'avg_execution_time_ms' => round($result['avg_execution_time'], 2)
                ];
            }

            return array_values($trends);
        });
    }

    /**
     * Détecte les problèmes potentiels
     */
    public function detectIssues(): array
    {
        $issues = [];
        $stats = $this->getGlobalStats(new \DateTimeImmutable('-1 hour'));

        // Taux de succès faible
        if ($stats['success_rate'] < 95.0 && $stats['total_sent'] > 10) {
            $issues[] = [
                'type' => 'low_success_rate',
                'severity' => 'high',
                'message' => "Taux de succès faible: {$stats['success_rate']}%",
                'value' => $stats['success_rate']
            ];
        }

        // Nombre d'essais élevé
        if ($stats['avg_attempts'] > 2.0) {
            $issues[] = [
                'type' => 'high_retry_rate',
                'severity' => 'medium',
                'message' => "Nombre moyen d'essais élevé: {$stats['avg_attempts']}",
                'value' => $stats['avg_attempts']
            ];
        }

        // Temps d'exécution élevé
        if ($stats['avg_execution_time_ms'] > 5000) {
            $issues[] = [
                'type' => 'slow_execution',
                'severity' => 'medium',
                'message' => "Temps d'exécution lent: {$stats['avg_execution_time_ms']}ms",
                'value' => $stats['avg_execution_time_ms']
            ];
        }

        // Vérifier les échecs récents par type
        $typeStats = $this->getStatsByType(new \DateTimeImmutable('-1 hour'));
        foreach ($typeStats as $type => $typeStat) {
            if ($typeStat['success_rate'] < 90.0 && $typeStat['total_sent'] > 5) {
                $issues[] = [
                    'type' => 'type_specific_failure',
                    'severity' => 'medium',
                    'message' => "Échecs pour le type '$type': {$typeStat['success_rate']}%",
                    'value' => $typeStat['success_rate'],
                    'email_type' => $type
                ];
            }
        }

        return $issues;
    }

    /**
     * Nettoie les anciennes métriques
     */
    public function cleanOldMetrics(int $daysToKeep = 90): int
    {
        $cutoffDate = (new \DateTimeImmutable())->modify("-{$daysToKeep} days");

        $sql = "DELETE FROM email_metrics WHERE sent_at < :cutoff_date";

        $result = $this->connection->executeStatement($sql, [
            'cutoff_date' => $cutoffDate->format('Y-m-d H:i:s')
        ]);

        $this->logger->info("Nettoyage des métriques email", [
            'deleted_records' => $result,
            'cutoff_date' => $cutoffDate->format('Y-m-d H:i:s')
        ]);

        // Invalider le cache
        $this->cache->invalidateTags(['email_stats']);

        return $result;
    }

    /**
     * Génère un rapport de santé des emails
     */
    public function generateHealthReport(): array
    {
        $globalStats = $this->getGlobalStats(new \DateTimeImmutable('-24 hours'));
        $typeStats = $this->getStatsByType(new \DateTimeImmutable('-24 hours'));
        $priorityStats = $this->getStatsByPriority(new \DateTimeImmutable('-24 hours'));
        $issues = $this->detectIssues();

        $health = 'healthy';
        if (count($issues) > 0) {
            $health = in_array('high', array_column($issues, 'severity')) ? 'critical' : 'warning';
        }

        return [
            'health' => $health,
            'timestamp' => (new \DateTimeImmutable())->format('c'),
            'global_stats' => $globalStats,
            'type_stats' => $typeStats,
            'priority_stats' => $priorityStats,
            'issues' => $issues,
            'recommendations' => $this->getRecommendations($issues)
        ];
    }

    /**
     * Génère des recommandations basées sur les problèmes détectés
     */
    private function getRecommendations(array $issues): array
    {
        $recommendations = [];

        foreach ($issues as $issue) {
            switch ($issue['type']) {
                case 'low_success_rate':
                    $recommendations[] = "Vérifier la configuration SMTP et les paramètres d'authentification";
                    $recommendations[] = "Contrôler la réputation IP et le domaine expéditeur";
                    break;

                case 'high_retry_rate':
                    $recommendations[] = "Examiner les logs SMTP pour identifier les erreurs temporaires";
                    $recommendations[] = "Ajuster les délais entre les tentatives";
                    break;

                case 'slow_execution':
                    $recommendations[] = "Optimiser les templates d'email";
                    $recommendations[] = "Vérifier les performances de la connexion SMTP";
                    break;

                case 'type_specific_failure':
                    $recommendations[] = "Vérifier le template pour le type '{$issue['email_type']}'";
                    $recommendations[] = "Examiner les données contextuelles pour ce type d'email";
                    break;
            }
        }

        return array_unique($recommendations);
    }
}
