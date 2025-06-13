<?php

namespace App\Controller\WebHook;

use App\Repository\PerformanceMetricRepository;
use App\Repository\ProjectRepository;
use App\Repository\UptimeCheckRepository;
use App\Service\PerformanceMonitoringService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/performance', name: 'performance_')]
class PerformanceController extends AbstractController
{
    public function __construct(
        private readonly PerformanceMonitoringService $performanceService,
        private readonly PerformanceMetricRepository $metricRepository,
        private readonly UptimeCheckRepository $uptimeRepository,
        private readonly ProjectRepository $projectRepository,
        private readonly EntityManagerInterface $entityManager
    ) {}

    /**
     * API endpoint pour recevoir des métriques de performance
     */
    #[Route('/metrics/{token}', name: 'submit_metrics', methods: ['POST'])]
    public function submitMetrics(string $token, Request $request): JsonResponse
    {
        try {
            // Trouver le projet par token webhook
            $project = $this->projectRepository->findOneBy(['webhookToken' => $token]);
            if (!$project) {
                return $this->json(['error' => 'Invalid token'], Response::HTTP_UNAUTHORIZED);
            }

            if (!$project->isActive()) {
                return $this->json(['error' => 'Project is not active'], Response::HTTP_FORBIDDEN);
            }

            // Vérifier les limites du plan
            if (!$project->canReceiveError()) {
                return $this->json([
                    'error' => 'Monthly limit reached',
                    'current_count' => $project->getCurrentMonthErrors(),
                    'max_allowed' => $project->getOwner()?->getPlan()?->getMaxMonthlyErrors()
                ], Response::HTTP_TOO_MANY_REQUESTS);
            }

            $data = json_decode($request->getContent(), true);
            if (!$data) {
                return $this->json(['error' => 'Invalid JSON payload'], Response::HTTP_BAD_REQUEST);
            }

            // Valider les données reçues
            $validationResult = $this->validateMetricsPayload($data);
            if ($validationResult !== true) {
                return $this->json(['error' => $validationResult], Response::HTTP_BAD_REQUEST);
            }

            $recordedMetrics = [];

            // Traiter les métriques individuelles
            if (isset($data['metrics']) && is_array($data['metrics'])) {
                $recordedMetrics = $this->performanceService->recordMetricsBatch($project, $data['metrics']);
            }

            // Traiter une métrique unique
            if (isset($data['type']) && isset($data['value'])) {
                $metric = $this->performanceService->recordMetric(
                    $project,
                    $data['type'],
                    (float) $data['value'],
                    $data['unit'] ?? 'count',
                    $data['metadata'] ?? [],
                    $data['source'] ?? 'api',
                    $data['environment'] ?? $project->getEnvironment()
                );
                $recordedMetrics[] = $metric;
            }

            return $this->json([
                'status' => 'success',
                'metrics_recorded' => count($recordedMetrics),
                'project' => $project->getSlug()
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Internal server error',
                'message' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * API endpoint pour les checks d'uptime
     */
    #[Route('/uptime/{token}', name: 'submit_uptime', methods: ['POST'])]
    public function submitUptimeCheck(string $token, Request $request): JsonResponse
    {
        try {
            $project = $this->projectRepository->findOneBy(['webhookToken' => $token]);
            if (!$project) {
                return $this->json(['error' => 'Invalid token'], Response::HTTP_UNAUTHORIZED);
            }

            if (!$project->isActive()) {
                return $this->json(['error' => 'Project is not active'], Response::HTTP_FORBIDDEN);
            }

            $data = json_decode($request->getContent(), true);
            if (!$data || !isset($data['url'])) {
                return $this->json(['error' => 'URL is required'], Response::HTTP_BAD_REQUEST);
            }

            $options = [
                'timeout' => $data['timeout'] ?? 30,
                'expected_status' => $data['expected_status'] ?? [200, 301, 302],
                'follow_redirects' => $data['follow_redirects'] ?? true,
                'check_location' => $data['check_location'] ?? 'api'
            ];

            $check = $this->performanceService->performUptimeCheck($project, $data['url'], $options);

            return $this->json([
                'status' => 'success',
                'check_id' => $check->getId(),
                'url' => $check->getUrl(),
                'status_result' => $check->getStatus(),
                'response_time' => $check->getResponseTimeAsFloat(),
                'http_status' => $check->getHttpStatusCode(),
                'is_up' => $check->isUp()
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Internal server error',
                'message' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Dashboard de monitoring de performance (nécessite authentification)
     */
    #[Route('/dashboard', name: 'dashboard')]
    #[IsGranted('ROLE_USER')]
    public function dashboard(Request $request): Response
    {
        $user = $this->getUser();
        $projects = $this->projectRepository->findByOwner($user);

        $selectedProject = null;
        $projectSlug = $request->query->get('project');

        if ($projectSlug) {
            $selectedProject = $this->projectRepository->findOneBy([
                'slug' => $projectSlug,
                'owner' => $user
            ]);
        } elseif (!empty($projects)) {
            $selectedProject = $projects[0];
        }

        $performanceData = [];
        if ($selectedProject) {
            $since = new \DateTime('-24 hours');
            $performanceData = $this->performanceService->getProjectPerformanceStats($selectedProject, $since);
        }

        return $this->render('dashboard/performance.html.twig', [
            'projects' => $projects,
            'selectedProject' => $selectedProject,
            'performanceData' => $performanceData,
            'currentPeriod' => '24h'
        ]);
    }

    /**
     * API pour récupérer les métriques de performance
     */
    #[Route('/api/metrics', name: 'api_metrics', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function getMetrics(Request $request): JsonResponse
    {
        $user = $this->getUser();
        $projectSlug = $request->query->get('project');
        $metricType = $request->query->get('type');
        $period = (int) $request->query->get('period', 24); // heures

        if (!$projectSlug) {
            return $this->json(['error' => 'Project parameter is required'], Response::HTTP_BAD_REQUEST);
        }

        $project = $this->projectRepository->findOneBy([
            'slug' => $projectSlug,
            'owner' => $user
        ]);

        if (!$project) {
            return $this->json(['error' => 'Project not found'], Response::HTTP_NOT_FOUND);
        }

        $since = new \DateTime("-{$period} hours");

        if ($metricType) {
            $metrics = $this->metricRepository->findByProjectAndType($project, $metricType, $since);
            $trend = $this->metricRepository->getMetricTrend($project, $metricType, 7, 'hour');
        } else {
            $metrics = $this->metricRepository->findByProject($project, $since);
            $trend = [];
        }

        $averages = $this->metricRepository->getAverageMetrics($project, $since);

        return $this->json([
            'metrics' => array_map(function($metric) {
                return [
                    'id' => $metric->getId(),
                    'type' => $metric->getMetricType(),
                    'value' => $metric->getValueAsFloat(),
                    'unit' => $metric->getUnit(),
                    'recorded_at' => $metric->getRecordedAt()->format('c'),
                    'description' => $metric->getDescription(),
                    'severity' => $metric->getSeverityLevel(),
                    'is_issue' => $metric->isPerformanceIssue()
                ];
            }, $metrics),
            'averages' => $averages,
            'trend' => $trend
        ]);
    }

    /**
     * API pour récupérer les données d'uptime
     */
    #[Route('/api/uptime', name: 'api_uptime', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function getUptime(Request $request): JsonResponse
    {
        $user = $this->getUser();
        $projectSlug = $request->query->get('project');
        $period = (int) $request->query->get('period', 24); // heures

        if (!$projectSlug) {
            return $this->json(['error' => 'Project parameter is required'], Response::HTTP_BAD_REQUEST);
        }

        $project = $this->projectRepository->findOneBy([
            'slug' => $projectSlug,
            'owner' => $user
        ]);

        if (!$project) {
            return $this->json(['error' => 'Project not found'], Response::HTTP_NOT_FOUND);
        }

        $since = new \DateTime("-{$period} hours");

        $checks = $this->uptimeRepository->findLatestByProject($project, 100);
        $stats = $this->uptimeRepository->getUptimeStats($project, $since);
        $trend = $this->uptimeRepository->getUptimeTrend($project, 7, 'hour');
        $incidents = $this->uptimeRepository->findDowntimeIncidents($project, $since);

        return $this->json([
            'latest_checks' => array_map(function($check) {
                return [
                    'id' => $check->getId(),
                    'url' => $check->getUrl(),
                    'status' => $check->getStatus(),
                    'response_time' => $check->getResponseTimeAsFloat(),
                    'http_status' => $check->getHttpStatusCode(),
                    'checked_at' => $check->getCheckedAt()->format('c'),
                    'is_up' => $check->isUp(),
                    'is_critical' => $check->isCritical(),
                    'description' => $check->getDescription()
                ];
            }, $checks),
            'stats' => $stats,
            'trend' => $trend,
            'incidents' => array_map(function($incident) {
                return [
                    'check' => [
                        'url' => $incident['check']->getUrl(),
                        'status' => $incident['check']->getStatus(),
                        'checked_at' => $incident['check']->getCheckedAt()->format('c'),
                        'error_message' => $incident['check']->getErrorMessage()
                    ],
                    'is_start_of_incident' => $incident['is_start_of_incident'],
                    'is_end_of_incident' => $incident['is_end_of_incident']
                ];
            }, $incidents)
        ]);
    }

    /**
     * API pour récupérer le statut global de santé
     */
    #[Route('/api/health', name: 'api_health', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function getHealthStatus(Request $request): JsonResponse
    {
        $user = $this->getUser();
        $projectSlug = $request->query->get('project');

        if ($projectSlug) {
            $project = $this->projectRepository->findOneBy([
                'slug' => $projectSlug,
                'owner' => $user
            ]);

            if (!$project) {
                return $this->json(['error' => 'Project not found'], Response::HTTP_NOT_FOUND);
            }

            $since = new \DateTime('-1 hour');
            $healthScore = $this->performanceService->calculateProjectHealthScore($project, $since);
            $issues = $this->performanceService->getRecentPerformanceIssues($project, $since);

            return $this->json([
                'project' => $project->getSlug(),
                'health_score' => $healthScore,
                'status' => $this->getStatusFromHealthScore($healthScore),
                'issues_count' => count($issues),
                'recent_issues' => array_slice($issues, 0, 10)
            ]);
        } else {
            // Statut global pour tous les projets
            $projectsHealth = $this->metricRepository->getProjectsHealthStatus($user);
            $globalUptime = $this->uptimeRepository->getGlobalUptimeStats($user, new \DateTime('-24 hours'));

            return $this->json([
                'projects' => $projectsHealth,
                'global_uptime' => $globalUptime,
                'overall_status' => $this->calculateOverallStatus($projectsHealth)
            ]);
        }
    }

    /**
     * Valide le payload des métriques
     */
    private function validateMetricsPayload(array $data): string|true
    {
        // Validation pour métriques multiples
        if (isset($data['metrics'])) {
            if (!is_array($data['metrics'])) {
                return 'Metrics must be an array';
            }

            foreach ($data['metrics'] as $metric) {
                if (!isset($metric['type']) || !isset($metric['value'])) {
                    return 'Each metric must have type and value';
                }

                if (!is_numeric($metric['value'])) {
                    return 'Metric value must be numeric';
                }
            }
        }

        // Validation pour métrique unique
        if (isset($data['type'])) {
            if (!isset($data['value'])) {
                return 'Value is required when type is specified';
            }

            if (!is_numeric($data['value'])) {
                return 'Value must be numeric';
            }
        }

        // Au moins une métrique doit être présente
        if (!isset($data['metrics']) && !isset($data['type'])) {
            return 'Either metrics array or single metric (type/value) is required';
        }

        return true;
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
     * Calcule le statut global basé sur la santé de tous les projets
     */
    private function calculateOverallStatus(array $projectsHealth): string
    {
        if (empty($projectsHealth)) {
            return 'unknown';
        }

        $statuses = array_column($projectsHealth, 'status');

        if (in_array('down', $statuses)) {
            return 'critical';
        }

        if (in_array('critical', $statuses)) {
            return 'critical';
        }

        if (in_array('warning', $statuses)) {
            return 'warning';
        }

        if (in_array('good', $statuses)) {
            return 'good';
        }

        return 'excellent';
    }
}
