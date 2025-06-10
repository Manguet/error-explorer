<?php

namespace App\Service;

use App\Entity\PerformanceMetric;
use App\Entity\Project;
use App\Entity\UptimeCheck;
use App\Repository\PerformanceMetricRepository;
use App\Repository\UptimeCheckRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class PerformanceMonitoringService
{
    private const DEFAULT_TIMEOUT = 30;
    private const DEFAULT_USER_AGENT = 'Error-Explorer-Monitor/1.0';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PerformanceMetricRepository $performanceMetricRepository,
        private readonly UptimeCheckRepository $uptimeCheckRepository,
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly AlertService $alertService
    ) {}

    /**
     * Enregistre une métrique de performance
     */
    public function recordMetric(
        Project $project,
        string $metricType,
        float $value,
        string $unit,
        array $metadata = [],
        string $source = null,
        string $environment = null
    ): PerformanceMetric {
        $metric = new PerformanceMetric();
        $metric
            ->setProject($project)
            ->setMetricType($metricType)
            ->setValueFromFloat($value)
            ->setUnit($unit)
            ->setMetadata($metadata)
            ->setSource($source)
            ->setEnvironment($environment);

        $this->entityManager->persist($metric);
        $this->entityManager->flush();

        // Vérifier si cette métrique nécessite une alerte
        $this->checkForPerformanceAlerts($metric);

        $this->logger->info('Performance metric recorded', [
            'project' => $project->getSlug(),
            'metric_type' => $metricType,
            'value' => $value,
            'unit' => $unit
        ]);

        return $metric;
    }

    /**
     * Enregistre plusieurs métriques en lot
     */
    public function recordMetricsBatch(Project $project, array $metrics): array
    {
        $recordedMetrics = [];
        
        foreach ($metrics as $metricData) {
            $metric = new PerformanceMetric();
            $metric
                ->setProject($project)
                ->setMetricType($metricData['type'])
                ->setValueFromFloat($metricData['value'])
                ->setUnit($metricData['unit'])
                ->setMetadata($metricData['metadata'] ?? [])
                ->setSource($metricData['source'] ?? null)
                ->setEnvironment($metricData['environment'] ?? null);

            if (isset($metricData['timestamp'])) {
                $metric->setRecordedAt(new \DateTime($metricData['timestamp']));
            }

            $this->entityManager->persist($metric);
            $recordedMetrics[] = $metric;
        }

        $this->entityManager->flush();

        // Vérifier les alertes pour toutes les métriques
        foreach ($recordedMetrics as $metric) {
            $this->checkForPerformanceAlerts($metric);
        }

        $this->logger->info('Performance metrics batch recorded', [
            'project' => $project->getSlug(),
            'count' => count($recordedMetrics)
        ]);

        return $recordedMetrics;
    }

    /**
     * Effectue un check d'uptime sur une URL
     */
    public function performUptimeCheck(Project $project, string $url, array $options = []): UptimeCheck
    {
        $timeout = $options['timeout'] ?? self::DEFAULT_TIMEOUT;
        $expectedStatus = $options['expected_status'] ?? [200, 301, 302];
        $followRedirects = $options['follow_redirects'] ?? true;
        $checkLocation = $options['check_location'] ?? gethostname();

        $startTime = microtime(true);
        $check = new UptimeCheck();
        $check
            ->setProject($project)
            ->setUrl($url)
            ->setCheckLocation($checkLocation);

        try {
            $response = $this->httpClient->request('GET', $url, [
                'timeout' => $timeout,
                'max_redirects' => $followRedirects ? 5 : 0,
                'headers' => [
                    'User-Agent' => self::DEFAULT_USER_AGENT,
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8'
                ],
                'verify_peer' => false,
                'verify_host' => false
            ]);

            $responseTime = (microtime(true) - $startTime) * 1000; // En millisecondes
            $statusCode = $response->getStatusCode();
            $content = $response->getContent(false);
            $headers = $response->getHeaders(false);

            $check
                ->setResponseTimeFromFloat($responseTime)
                ->setHttpStatusCode($statusCode)
                ->setContentLength(strlen($content))
                ->setContentChecksum(md5($content))
                ->setResponseHeaders($this->normalizeHeaders($headers));

            // Déterminer le statut
            if (in_array($statusCode, $expectedStatus)) {
                $check->setStatus('up');
                
                // Enregistrer aussi comme métrique de performance
                $this->recordMetric(
                    $project,
                    'response_time',
                    $responseTime,
                    'ms',
                    ['url' => $url, 'http_status' => $statusCode],
                    'uptime_check',
                    $project->getEnvironment()
                );

                $this->recordMetric(
                    $project,
                    'uptime',
                    1.0,
                    'boolean',
                    ['url' => $url],
                    'uptime_check',
                    $project->getEnvironment()
                );
            } else {
                $check
                    ->setStatus('down')
                    ->setErrorMessage("Unexpected HTTP status: {$statusCode}");
                    
                $this->recordMetric(
                    $project,
                    'uptime',
                    0.0,
                    'boolean',
                    ['url' => $url, 'error' => "HTTP {$statusCode}"],
                    'uptime_check',
                    $project->getEnvironment()
                );
            }

        } catch (\Symfony\Contracts\HttpClient\Exception\TimeoutException $e) {
            $responseTime = $timeout * 1000;
            $check
                ->setStatus('timeout')
                ->setResponseTimeFromFloat($responseTime)
                ->setErrorMessage('Request timeout after ' . $timeout . ' seconds');
                
            $this->recordMetric(
                $project,
                'uptime',
                0.0,
                'boolean',
                ['url' => $url, 'error' => 'timeout'],
                'uptime_check',
                $project->getEnvironment()
            );

        } catch (\Exception $e) {
            $check
                ->setStatus('error')
                ->setErrorMessage($e->getMessage());
                
            $this->recordMetric(
                $project,
                'uptime',
                0.0,
                'boolean',
                ['url' => $url, 'error' => $e->getMessage()],
                'uptime_check',
                $project->getEnvironment()
            );
        }

        $this->entityManager->persist($check);
        $this->entityManager->flush();

        // Vérifier si ce check nécessite une alerte
        $this->checkForUptimeAlerts($check);

        $this->logger->info('Uptime check performed', [
            'project' => $project->getSlug(),
            'url' => $url,
            'status' => $check->getStatus(),
            'response_time' => $check->getResponseTimeAsFloat()
        ]);

        return $check;
    }

    /**
     * Effectue des checks d'uptime pour tous les projets actifs
     */
    public function performScheduledUptimeChecks(): array
    {
        $projects = $this->entityManager->getRepository(Project::class)
            ->createQueryBuilder('p')
            ->where('p.isActive = true')
            ->getQuery()
            ->getResult();

        $results = [];

        foreach ($projects as $project) {
            $monitoringUrls = $this->getProjectMonitoringUrls($project);
            
            foreach ($monitoringUrls as $url) {
                try {
                    $check = $this->performUptimeCheck($project, $url);
                    $results[] = $check;
                } catch (\Exception $e) {
                    $this->logger->error('Failed to perform uptime check', [
                        'project' => $project->getSlug(),
                        'url' => $url,
                        'error' => $e->getMessage()
                    ]);
                }
            }
        }

        return $results;
    }

    /**
     * Récupère les statistiques de performance pour un projet
     */
    public function getProjectPerformanceStats(Project $project, \DateTimeInterface $since): array
    {
        $metrics = $this->performanceMetricRepository->getAverageMetrics($project, $since);
        $uptimeStats = $this->uptimeCheckRepository->getUptimeStats($project, $since);
        
        return [
            'metrics' => $metrics,
            'uptime' => $uptimeStats,
            'health_score' => $this->calculateProjectHealthScore($project, $since),
            'issues' => $this->getRecentPerformanceIssues($project, $since)
        ];
    }

    /**
     * Calcule un score de santé global pour un projet
     */
    public function calculateProjectHealthScore(Project $project, \DateTimeInterface $since): int
    {
        $metrics = $this->performanceMetricRepository->getAverageMetrics($project, $since);
        $uptimeStats = $this->uptimeCheckRepository->getUptimeStats($project, $since);
        
        $score = 100;

        // Pénalités basées sur l'uptime
        if (isset($uptimeStats['uptime_percent'])) {
            $score -= (100 - $uptimeStats['uptime_percent']);
        }

        // Pénalités basées sur les métriques
        if (isset($metrics['response_time']['avg']) && $metrics['response_time']['avg'] > 1000) {
            $score -= min(30, ($metrics['response_time']['avg'] - 1000) / 100);
        }

        if (isset($metrics['error_rate']['avg']) && $metrics['error_rate']['avg'] > 1) {
            $score -= min(40, $metrics['error_rate']['avg'] * 5);
        }

        if (isset($metrics['cpu_usage']['avg']) && $metrics['cpu_usage']['avg'] > 70) {
            $score -= min(20, ($metrics['cpu_usage']['avg'] - 70) / 2);
        }

        if (isset($metrics['memory_usage']['avg']) && $metrics['memory_usage']['avg'] > 512) {
            $score -= min(15, ($metrics['memory_usage']['avg'] - 512) / 100);
        }

        return max(0, min(100, (int)round($score)));
    }

    /**
     * Récupère les problèmes de performance récents
     */
    public function getRecentPerformanceIssues(Project $project, \DateTimeInterface $since): array
    {
        $metricIssues = $this->performanceMetricRepository->findPerformanceIssues($project, $since);
        $uptimeIssues = $this->uptimeCheckRepository->findPerformanceIssues($project, $since);
        
        $issues = [];
        
        foreach ($metricIssues as $metric) {
            $issues[] = [
                'type' => 'metric',
                'severity' => $metric->getSeverityLevel(),
                'description' => $metric->getDescription(),
                'timestamp' => $metric->getRecordedAt(),
                'data' => $metric
            ];
        }
        
        foreach ($uptimeIssues as $check) {
            $issues[] = [
                'type' => 'uptime',
                'severity' => $check->getSeverityLevel(),
                'description' => $check->getDescription(),
                'timestamp' => $check->getCheckedAt(),
                'data' => $check
            ];
        }

        // Trier par timestamp décroissant
        usort($issues, function($a, $b) {
            return $b['timestamp'] <=> $a['timestamp'];
        });

        return $issues;
    }

    /**
     * Vérifie si une métrique nécessite une alerte
     */
    private function checkForPerformanceAlerts(PerformanceMetric $metric): void
    {
        if (!$metric->isPerformanceIssue()) {
            return;
        }

        $project = $metric->getProject();
        if (!$project->isAlertsEnabled()) {
            return;
        }

        $severity = $metric->getSeverityLevel();
        $shouldAlert = match($severity) {
            'critical' => true,
            'high' => true,
            'medium' => false, // Optionnel selon configuration
            default => false
        };

        if ($shouldAlert) {
            $this->alertService->sendPerformanceAlert($project, $metric);
        }
    }

    /**
     * Vérifie si un check d'uptime nécessite une alerte
     */
    private function checkForUptimeAlerts(UptimeCheck $check): void
    {
        $project = $check->getProject();
        if (!$project->isAlertsEnabled()) {
            return;
        }

        if ($check->isCritical()) {
            $this->alertService->sendUptimeAlert($project, $check);
        }
    }

    /**
     * Récupère les URLs à monitorer pour un projet
     */
    private function getProjectMonitoringUrls(Project $project): array
    {
        $urls = [];
        
        // URL principale du projet (si configurée)
        $repositoryUrl = $project->getRepositoryUrl();
        if ($repositoryUrl && filter_var($repositoryUrl, FILTER_VALIDATE_URL)) {
            $urls[] = $repositoryUrl;
        }

        // URLs configurées dans les métadonnées du projet
        $monitoringUrls = $project->getSetting('monitoring_urls', []);
        if (is_array($monitoringUrls)) {
            $urls = array_merge($urls, $monitoringUrls);
        }

        // URL par défaut basée sur le slug du projet
        $baseUrl = $project->getSetting('base_url');
        if ($baseUrl) {
            $urls[] = rtrim($baseUrl, '/');
        }

        return array_unique(array_filter($urls));
    }

    /**
     * Normalise les headers HTTP pour le stockage
     */
    private function normalizeHeaders(array $headers): array
    {
        $normalized = [];
        foreach ($headers as $name => $values) {
            $normalized[strtolower($name)] = is_array($values) ? implode(', ', $values) : $values;
        }
        return $normalized;
    }

    /**
     * Nettoie les anciennes données de monitoring
     */
    public function cleanupOldData(int $daysToKeep = 90): array
    {
        $metricsDeleted = $this->performanceMetricRepository->cleanupOldMetrics($daysToKeep);
        $checksDeleted = $this->uptimeCheckRepository->cleanupOldChecks($daysToKeep);

        $this->logger->info('Performance monitoring data cleanup completed', [
            'metrics_deleted' => $metricsDeleted,
            'checks_deleted' => $checksDeleted,
            'days_kept' => $daysToKeep
        ]);

        return [
            'metrics_deleted' => $metricsDeleted,
            'checks_deleted' => $checksDeleted
        ];
    }
}