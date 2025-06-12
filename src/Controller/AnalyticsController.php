<?php

namespace App\Controller;

use App\Repository\ErrorGroupRepository;
use App\Repository\ErrorOccurrenceRepository;
use App\Repository\ProjectRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/analytics', name: 'analytics_')]
#[IsGranted('ROLE_USER')]
class AnalyticsController extends AbstractController
{
    public function __construct(
        private readonly ErrorGroupRepository $errorGroupRepository,
        private readonly ErrorOccurrenceRepository $errorOccurrenceRepository,
        private readonly ProjectRepository $projectRepository,
        private readonly EntityManagerInterface $entityManager
    ) {}

    #[Route('/', name: 'index')]
    public function index(Request $request): Response
    {
        $user = $this->getUser();
        
        // Vérifier si l'utilisateur a accès aux analytics avancés en utilisant une requête directe
        $hasAccess = $this->checkUserHasAdvancedAnalytics($user->getId());
        $planName = $this->getUserPlanName($user->getId());
        
        if (!$hasAccess) {
            return $this->render('analytics/upgrade.html.twig', [
                'user_plan_name' => $planName
            ]);
        }
        
        // Période par défaut : 30 derniers jours
        $period = $request->query->get('period', '30');
        $project = $request->query->get('project', '');
        
        // Calculer les dates
        $endDate = new \DateTime();
        $startDate = new \DateTime("-{$period} days");
        
        // Filtres de base
        $filters = [
            'user' => $user,
            'since' => $startDate,
            'until' => $endDate
        ];
        
        if ($project) {
            $filters['project'] = $project;
        }
        
        // Statistiques globales
        $globalStats = $this->getGlobalAnalytics($filters);
        
        // Tendances
        $trends = $this->getTrendAnalytics($filters, $period);
        
        // Top des projets
        $topProjects = $this->getTopProjects($filters);
        
        // Top des erreurs
        $topErrors = $this->getTopErrors($filters);
        
        // Répartition par type d'erreur
        $errorTypes = $this->getErrorTypeDistribution($filters);
        
        // Liste des projets pour le filtre
        $projects = $this->projectRepository->findByOwner($user, 100);
        
        return $this->render('analytics/index.html.twig', [
            'global_stats' => $globalStats,
            'trends' => $trends,
            'top_projects' => $topProjects,
            'top_errors' => $topErrors,
            'error_types' => $errorTypes,
            'projects' => $projects,
            'current_period' => $period,
            'current_project' => $project,
            'start_date' => $startDate,
            'end_date' => $endDate
        ]);
    }

    #[Route('/api/trends', name: 'api_trends')]
    public function apiTrends(Request $request): JsonResponse
    {
        $user = $this->getUser();
        
        // Vérifier les droits d'accès
        if (!$this->checkUserHasAdvancedAnalytics($user->getId())) {
            return $this->json(['error' => 'Access denied'], 403);
        }
        $period = $request->query->get('period', '30');
        $project = $request->query->get('project', '');
        
        $startDate = new \DateTime("-{$period} days");
        $filters = [
            'user' => $user,
            'since' => $startDate
        ];
        
        if ($project) {
            $filters['project'] = $project;
        }
        
        $trends = $this->errorOccurrenceRepository->getErrorTrend((int)$period, $filters);
        
        return $this->json($trends);
    }

    #[Route('/api/distribution', name: 'api_distribution')]
    public function apiDistribution(Request $request): JsonResponse
    {
        $user = $this->getUser();
        
        // Vérifier les droits d'accès
        if (!$this->checkUserHasAdvancedAnalytics($user->getId())) {
            return $this->json(['error' => 'Access denied'], 403);
        }
        $period = $request->query->get('period', '30');
        $project = $request->query->get('project', '');
        
        $startDate = new \DateTime("-{$period} days");
        $filters = [
            'user' => $user,
            'since' => $startDate
        ];
        
        if ($project) {
            $filters['project'] = $project;
        }
        
        $distribution = $this->getErrorTypeDistribution($filters);
        
        return $this->json($distribution);
    }

    #[Route('/export/csv', name: 'export_csv')]
    public function exportData(Request $request): Response
    {
        $user = $this->getUser();
        
        // Vérifier les droits d'accès
        if (!$this->checkUserHasAdvancedAnalytics($user->getId())) {
            throw $this->createAccessDeniedException('Analytics avancés requis');
        }
        
        $period = $request->query->get('period', '30');
        $project = $request->query->get('project', '');
        $format = $request->query->get('format', 'csv');
        
        $startDate = new \DateTime("-{$period} days");
        $endDate = new \DateTime();
        
        $filters = [
            'user' => $user,
            'since' => $startDate,
            'until' => $endDate
        ];
        
        if ($project) {
            $filters['project'] = $project;
        }
        
        // Router vers le bon format
        switch ($format) {
            case 'json':
                return $this->exportJson($filters);
            case 'summary':
                return $this->exportSummary($filters);
            default:
                return $this->exportCsv($filters);
        }
    }

    private function exportCsv(array $filters): Response
    {
        // Récupérer les données
        $errors = $this->errorGroupRepository->findWithFilters($filters, 'lastSeen', 'DESC', 1000);
        
        // Récupérer les statistiques avancées
        $advancedStats = $this->getAdvancedExportStatistics($filters);
        
        // Générer le CSV avec BOM UTF-8
        $response = new Response();
        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="analytics-avances-' . date('Y-m-d-H-i') . '.csv"');
        
        // BOM UTF-8 pour assurer la compatibilité Excel
        $csv = "\xEF\xBB\xBF";
        
        // En-têtes détaillés
        $csv .= "ID;Titre;Message;Projet;Type d'erreur;Statut;Occurrences;Première occurrence;Dernière occurrence;";
        $csv .= "Durée non résolue (heures);Jour de la semaine première occurrence;Heure première occurrence;";
        $csv .= "Environnement;Navigateur;Système d'exploitation;Méthode HTTP;Code statut HTTP;";
        $csv .= "URL de l'erreur;Fichier source;Ligne d'erreur;Stack trace (extrait);";
        $csv .= "Fréquence par jour;Pic d'activité;Dernière activité (jours);Impact utilisateur;";
        $csv .= "Tags;Gravité estimée;Complexité résolution;Résolu par;Date résolution\n";
        
        foreach ($errors as $error) {
            // Calculs avancés pour chaque erreur
            $durationUnresolved = $error->getStatus() === 'resolved' && $error->getResolvedAt() 
                ? round((($error->getResolvedAt()->getTimestamp() - $error->getFirstSeen()->getTimestamp()) / 3600), 1)
                : round(((time() - $error->getFirstSeen()->getTimestamp()) / 3600), 1);
            
            $firstSeenDay = $error->getFirstSeen()->format('l'); // Nom du jour en anglais
            $firstSeenHour = $error->getFirstSeen()->format('H');
            $lastActivityDays = round((time() - $error->getLastSeen()->getTimestamp()) / (24 * 3600));
            
            // Extraction d'informations du contexte et de la requête
            $context = $error->getLatestContext();
            $request = $error->getLatestRequest();
            $userAgent = $error->getLatestUserAgent() ?: '';
            
            $environment = $error->getEnvironment() ?: 'Unknown';
            $browser = $this->extractBrowserFromUserAgent($userAgent);
            $os = $this->extractOSFromUserAgent($userAgent);
            $httpMethod = $request['method'] ?? 'Unknown';
            $httpStatus = $error->getHttpStatusCode() ?: 'Unknown';
            $errorUrl = $request['url'] ?? 'Unknown';
            $sourceFile = $error->getFile() ?: 'Unknown';
            $sourceLine = $error->getLine() ?: 'Unknown';
            
            // Stack trace extrait (première ligne)
            $stackTrace = $error->getStackTrace() ? explode("\n", $error->getStackTrace())[0] : 'Non disponible';
            $stackTrace = substr($stackTrace, 0, 100) . (strlen($stackTrace) > 100 ? '...' : '');
            
            // Calculs de fréquence et patterns
            $frequencyPerDay = $error->getOccurrenceCount() > 0 && $durationUnresolved > 24 
                ? round($error->getOccurrenceCount() / ($durationUnresolved / 24), 2) 
                : $error->getOccurrenceCount();
            
            // Estimation de la gravité basée sur occurrences et type
            $severity = $this->estimateSeverity($error);
            $complexity = $this->estimateComplexity($error);
            $userImpact = $this->estimateUserImpact($error);
            $peakActivity = $this->calculatePeakActivity($error->getId());
            
            // Tags basés sur l'analyse automatique
            $tags = $this->generateErrorTags($error);
            
            $csv .= sprintf(
                "%d;\"%s\";\"%s\";\"%s\";\"%s\";\"%s\";%d;\"%s\";\"%s\";",
                $error->getId(),
                $this->escapeCsvField($error->getTitle()),
                $this->escapeCsvField(substr($error->getMessage(), 0, 200)),
                $this->escapeCsvField($error->getProject()),
                $this->escapeCsvField($error->getErrorType() ?: 'Unknown'),
                $this->escapeCsvField($error->getStatus()),
                $error->getOccurrenceCount(),
                $error->getFirstSeen()->format('Y-m-d H:i:s'),
                $error->getLastSeen()->format('Y-m-d H:i:s')
            );
            
            $csv .= sprintf(
                "%.1f;\"%s\";%s;\"%s\";\"%s\";\"%s\";\"%s\";\"%s\";",
                $durationUnresolved,
                $firstSeenDay,
                $firstSeenHour,
                $this->escapeCsvField($environment),
                $this->escapeCsvField($browser),
                $this->escapeCsvField($os),
                $this->escapeCsvField($httpMethod),
                $this->escapeCsvField($httpStatus)
            );
            
            $csv .= sprintf(
                "\"%s\";\"%s\";\"%s\";\"%s\";",
                $this->escapeCsvField($errorUrl),
                $this->escapeCsvField($sourceFile),
                $this->escapeCsvField($sourceLine),
                $this->escapeCsvField($stackTrace)
            );
            
            $csv .= sprintf(
                "%.2f;\"%s\";%d;\"%s\";",
                $frequencyPerDay,
                $this->escapeCsvField($peakActivity),
                $lastActivityDays,
                $this->escapeCsvField($userImpact)
            );
            
            $csv .= sprintf(
                "\"%s\";\"%s\";\"%s\";\"%s\";\"%s\"\n",
                $this->escapeCsvField($tags),
                $this->escapeCsvField($severity),
                $this->escapeCsvField($complexity),
                $this->escapeCsvField($error->getResolvedBy() ?: 'Non résolu'),
                $error->getResolvedAt() ? $error->getResolvedAt()->format('Y-m-d H:i:s') : 'Non résolu'
            );
        }
        
        // Ajouter un résumé statistique à la fin
        $csv .= $this->generateStatsSummary($advancedStats);
        
        $response->setContent($csv);
        return $response;
    }

    #[Route('/api/metrics/{period}', name: 'api_metrics', requirements: ['period' => '\d+'])]
    public function apiMetrics(int $period, Request $request): JsonResponse
    {
        $user = $this->getUser();
        
        // Vérifier les droits d'accès
        if (!$this->checkUserHasAdvancedAnalytics($user->getId())) {
            return $this->json(['error' => 'Access denied'], 403);
        }
        
        $project = $request->query->get('project', '');
        
        $startDate = new \DateTime("-{$period} days");
        $endDate = new \DateTime();
        
        $filters = [
            'user' => $user,
            'since' => $startDate,
            'until' => $endDate
        ];
        
        if ($project) {
            $filters['project'] = $project;
        }
        
        // Métriques avancées
        $metrics = [
            'resolution_metrics' => $this->getResolutionMetrics($filters),
            'error_frequency' => $this->getErrorFrequencyMetrics($filters),
            'project_health' => $this->getProjectHealthMetrics($filters),
            'time_to_resolution' => $this->getTimeToResolutionMetrics($filters)
        ];
        
        return $this->json($metrics);
    }

    private function getResolutionMetrics(array $filters): array
    {
        $totalErrors = $this->errorGroupRepository->countWithFilters($filters);
        $resolvedErrors = $this->errorGroupRepository->countWithFilters(
            array_merge($filters, ['status' => 'resolved'])
        );
        $ignoredErrors = $this->errorGroupRepository->countWithFilters(
            array_merge($filters, ['status' => 'ignored'])
        );
        
        return [
            'total' => $totalErrors,
            'resolved' => $resolvedErrors,
            'ignored' => $ignoredErrors,
            'open' => $totalErrors - $resolvedErrors - $ignoredErrors,
            'resolution_rate' => $totalErrors > 0 ? round(($resolvedErrors / $totalErrors) * 100, 2) : 0
        ];
    }

    private function getErrorFrequencyMetrics(array $filters): array
    {
        // Erreurs par jour de la semaine
        $sql = "
            SELECT 
                DAYOFWEEK(eo.occurred_at) as day_of_week,
                COUNT(*) as count
            FROM error_occurrences eo
            JOIN error_groups eg ON eo.error_group_id = eg.id
            JOIN projects p ON eg.project_id = p.id
            WHERE p.owner_id = :userId
                AND eo.occurred_at >= :startDate
                AND eo.occurred_at <= :endDate
        ";
        
        $params = [
            'userId' => $filters['user']->getId(),
            'startDate' => $filters['since']->format('Y-m-d H:i:s'),
            'endDate' => $filters['until']->format('Y-m-d H:i:s')
        ];
        
        if (isset($filters['project'])) {
            $sql .= " AND eg.project = :project";
            $params['project'] = $filters['project'];
        }
        
        $sql .= " GROUP BY DAYOFWEEK(eo.occurred_at) ORDER BY day_of_week";
        
        $conn = $this->errorOccurrenceRepository->getEntityManager()->getConnection();
        $stmt = $conn->prepare($sql);
        $result = $stmt->executeQuery($params);
        
        $dayLabels = ['Dimanche', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'];
        $dayData = array_fill(0, 7, 0);
        
        foreach ($result->fetchAllAssociative() as $row) {
            $dayData[$row['day_of_week'] - 1] = (int)$row['count'];
        }
        
        return [
            'by_day_of_week' => array_map(function($count, $index) use ($dayLabels) {
                return ['day' => $dayLabels[$index], 'count' => $count];
            }, $dayData, array_keys($dayData))
        ];
    }

    private function getProjectHealthMetrics(array $filters): array
    {
        $projects = $this->projectRepository->findByOwner($filters['user'], 50);
        $healthData = [];
        
        foreach ($projects as $project) {
            $projectFilters = array_merge($filters, ['project' => $project->getSlug()]);
            
            $totalErrors = $this->errorGroupRepository->countWithFilters($projectFilters);
            $openErrors = $this->errorGroupRepository->countWithFilters(
                array_merge($projectFilters, ['status' => 'open'])
            );
            
            $healthScore = $totalErrors > 0 ? max(0, 100 - (($openErrors / $totalErrors) * 100)) : 100;
            
            $healthData[] = [
                'project' => $project->getName(),
                'slug' => $project->getSlug(),
                'total_errors' => $totalErrors,
                'open_errors' => $openErrors,
                'health_score' => round($healthScore, 1)
            ];
        }
        
        // Trier par score de santé
        usort($healthData, function($a, $b) {
            return $b['health_score'] <=> $a['health_score'];
        });
        
        return array_slice($healthData, 0, 10);
    }

    private function getTimeToResolutionMetrics(array $filters): array
    {
        $sql = "
            SELECT 
                AVG(TIMESTAMPDIFF(HOUR, eg.first_seen, eg.resolved_at)) as avg_hours,
                MIN(TIMESTAMPDIFF(HOUR, eg.first_seen, eg.resolved_at)) as min_hours,
                MAX(TIMESTAMPDIFF(HOUR, eg.first_seen, eg.resolved_at)) as max_hours,
                COUNT(*) as resolved_count
            FROM error_groups eg
            JOIN projects p ON eg.project_id = p.id
            WHERE p.owner_id = :userId
                AND eg.status = 'resolved'
                AND eg.resolved_at IS NOT NULL
                AND eg.last_seen >= :startDate
                AND eg.last_seen <= :endDate
        ";
        
        $params = [
            'userId' => $filters['user']->getId(),
            'startDate' => $filters['since']->format('Y-m-d H:i:s'),
            'endDate' => $filters['until']->format('Y-m-d H:i:s')
        ];
        
        if (isset($filters['project'])) {
            $sql .= " AND eg.project = :project";
            $params['project'] = $filters['project'];
        }
        
        $conn = $this->errorGroupRepository->getEntityManager()->getConnection();
        $stmt = $conn->prepare($sql);
        $result = $stmt->executeQuery($params);
        $data = $result->fetchAssociative();
        
        return [
            'average_hours' => $data['avg_hours'] ? round((float)$data['avg_hours'], 1) : 0,
            'fastest_hours' => $data['min_hours'] ? round((float)$data['min_hours'], 1) : 0,
            'slowest_hours' => $data['max_hours'] ? round((float)$data['max_hours'], 1) : 0,
            'resolved_count' => (int)$data['resolved_count']
        ];
    }

    private function getGlobalAnalytics(array $filters): array
    {
        $totalErrors = $this->errorGroupRepository->countWithFilters($filters);
        $totalOccurrences = $this->errorOccurrenceRepository->countWithFilters($filters);
        
        // Période précédente pour comparaison
        $previousFilters = $filters;
        $period = $filters['until']->diff($filters['since'])->days;
        $previousStartDate = new \DateTime("-" . ($period * 2) . " days");
        $previousEndDate = new \DateTime("-{$period} days");
        $previousFilters['since'] = $previousStartDate;
        $previousFilters['until'] = $previousEndDate;
        
        $previousErrors = $this->errorGroupRepository->countWithFilters($previousFilters);
        $previousOccurrences = $this->errorOccurrenceRepository->countWithFilters($previousFilters);
        
        // Calcul des variations
        $errorGrowth = $previousErrors > 0 ? (($totalErrors - $previousErrors) / $previousErrors) * 100 : 0;
        $occurrenceGrowth = $previousOccurrences > 0 ? (($totalOccurrences - $previousOccurrences) / $previousOccurrences) * 100 : 0;
        
        // Répartition par statut
        $openErrors = $this->errorGroupRepository->countWithFilters(
            array_merge($filters, ['status' => 'open'])
        );
        $resolvedErrors = $this->errorGroupRepository->countWithFilters(
            array_merge($filters, ['status' => 'resolved'])
        );
        $ignoredErrors = $this->errorGroupRepository->countWithFilters(
            array_merge($filters, ['status' => 'ignored'])
        );
        
        return [
            'total_errors' => $totalErrors,
            'total_occurrences' => $totalOccurrences,
            'error_growth' => round($errorGrowth, 1),
            'occurrence_growth' => round($occurrenceGrowth, 1),
            'open_errors' => $openErrors,
            'resolved_errors' => $resolvedErrors,
            'ignored_errors' => $ignoredErrors,
            'resolution_rate' => $totalErrors > 0 ? round(($resolvedErrors / $totalErrors) * 100, 1) : 0,
            'avg_occurrences_per_error' => $totalErrors > 0 ? round($totalOccurrences / $totalErrors, 1) : 0
        ];
    }

    private function getTrendAnalytics(array $filters, string $period): array
    {
        $days = (int)$period;
        
        // Obtenir les tendances des occurrences
        $occurrencesTrend = $this->errorOccurrenceRepository->getErrorTrend($days, $filters);
        
        // Obtenir les tendances des erreurs (groupes d'erreurs uniques par jour)
        $errorsTrend = $this->getErrorsTrend($days, $filters);
        
        // Fusionner les deux datasets
        $result = [];
        $errorsMap = [];
        
        // Créer un map des erreurs par date
        foreach ($errorsTrend as $errorData) {
            $errorsMap[$errorData['date']] = (int)$errorData['count'];
        }
        
        // Combiner avec les occurrences
        foreach ($occurrencesTrend as $occurrenceData) {
            $result[] = [
                'date' => $occurrenceData['date'],
                'occurrences' => (int)$occurrenceData['count'],
                'errors' => $errorsMap[$occurrenceData['date']] ?? 0
            ];
        }
        
        return $result;
    }

    private function getTopProjects(array $filters): array
    {
        $topProjects = $this->errorGroupRepository->getTopProjectsByOccurrences(10, $filters);
        
        // Normaliser les clés pour le template
        $result = [];
        foreach ($topProjects as $project) {
            $result[] = [
                'project' => $project['project'],
                'error_count' => $project['error_count'],
                'occurrence_count' => $project['total_occurrences']
            ];
        }
        
        return $result;
    }

    private function getTopErrors(array $filters): array
    {
        $errors = $this->errorGroupRepository->findWithFilters(
            $filters,
            'occurrenceCount',
            'DESC',
            10
        );
        
        $result = [];
        foreach ($errors as $error) {
            $result[] = [
                'id' => $error->getId(),
                'title' => $error->getTitle(),
                'message' => $error->getMessage(),
                'project' => $error->getProject(),
                'occurrence_count' => $error->getOccurrenceCount(),
                'status' => $error->getStatus(),
                'last_seen' => $error->getLastSeen()
            ];
        }
        
        return $result;
    }

    private function getErrorTypeDistribution(array $filters): array
    {
        // Requête personnalisée pour obtenir la distribution par type d'erreur
        $qb = $this->errorGroupRepository->createQueryBuilder('eg');
        
        // Appliquer les filtres manuellement
        if (isset($filters['user'])) {
            $qb->join('eg.projectEntity', 'p')
               ->andWhere('p.owner = :user')
               ->setParameter('user', $filters['user']);
        }
        
        if (isset($filters['project'])) {
            $qb->andWhere('eg.project = :project')
               ->setParameter('project', $filters['project']);
        }
        
        if (isset($filters['since'])) {
            $qb->andWhere('eg.lastSeen >= :since')
               ->setParameter('since', $filters['since']);
        }
        
        if (isset($filters['until'])) {
            $qb->andWhere('eg.lastSeen <= :until')
               ->setParameter('until', $filters['until']);
        }
        
        $result = $qb->select('eg.errorType, COUNT(eg.id) as count, SUM(eg.occurrenceCount) as occurrences')
            ->groupBy('eg.errorType')
            ->orderBy('count', 'DESC')
            ->getQuery()
            ->getResult();
        
        $distribution = [];
        foreach ($result as $row) {
            $distribution[] = [
                'type' => $row['errorType'] ?: 'Unknown',
                'count' => (int)$row['count'],
                'occurrences' => (int)$row['occurrences']
            ];
        }
        
        return $distribution;
    }

    private function getErrorsTrend(int $days, array $filters): array
    {
        $startDate = new \DateTime("-{$days} days");
        $startDate->setTime(0, 0, 0);

        // Utiliser SQL natif pour gérer la fonction DATE()
        $sql = "
            SELECT DATE(eg.first_seen) as date, COUNT(DISTINCT eg.id) as count
            FROM error_groups eg
        ";

        $params = ['startDate' => $startDate->format('Y-m-d H:i:s')];
        $whereClauses = ['eg.first_seen >= :startDate'];

        // Filtre utilisateur (multi-tenancy) via projects
        if (isset($filters['user'])) {
            $sql .= " JOIN projects p ON eg.project_id = p.id";
            $whereClauses[] = 'p.owner_id = :userId';
            $params['userId'] = $filters['user']->getId();
        }

        // Ajouter les autres filtres
        if (isset($filters['project'])) {
            $whereClauses[] = 'eg.project = :project';
            $params['project'] = $filters['project'];
        }

        if (isset($filters['since'])) {
            $whereClauses[] = 'eg.last_seen >= :since';
            $params['since'] = $filters['since']->format('Y-m-d H:i:s');
        }

        if (isset($filters['until'])) {
            $whereClauses[] = 'eg.last_seen <= :until';
            $params['until'] = $filters['until']->format('Y-m-d H:i:s');
        }

        $sql .= " WHERE " . implode(' AND ', $whereClauses);
        $sql .= " GROUP BY DATE(eg.first_seen) ORDER BY date ASC";

        $conn = $this->errorGroupRepository->getEntityManager()->getConnection();
        $stmt = $conn->prepare($sql);
        $result = $stmt->executeQuery($params);

        $results = $result->fetchAllAssociative();

        // Remplir les jours manquants avec 0
        return $this->fillMissingDays($results, $days);
    }

    private function fillMissingDays(array $results, int $days): array
    {
        $filledResults = [];
        $resultsMap = [];

        // Créer un map des résultats existants
        foreach ($results as $result) {
            $resultsMap[$result['date']] = (int)$result['count'];
        }

        // Générer tous les jours et remplir avec 0 si manquant
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = new \DateTime("-{$i} days");
            $dateStr = $date->format('Y-m-d');

            $filledResults[] = [
                'date' => $dateStr,
                'count' => $resultsMap[$dateStr] ?? 0
            ];
        }

        return $filledResults;
    }

    private function checkUserHasAdvancedAnalytics(int $userId): bool
    {
        $sql = "
            SELECT p.has_advanced_analytics 
            FROM users u 
            JOIN plans p ON u.plan_id = p.id 
            WHERE u.id = :userId
        ";
        
        $conn = $this->entityManager->getConnection();
        $stmt = $conn->prepare($sql);
        $result = $stmt->executeQuery(['userId' => $userId]);
        $data = $result->fetchAssociative();
        
        return $data && (bool)$data['has_advanced_analytics'];
    }

    private function getUserPlanName(int $userId): ?string
    {
        $sql = "
            SELECT p.name 
            FROM users u 
            JOIN plans p ON u.plan_id = p.id 
            WHERE u.id = :userId
        ";
        
        $conn = $this->entityManager->getConnection();
        $stmt = $conn->prepare($sql);
        $result = $stmt->executeQuery(['userId' => $userId]);
        $data = $result->fetchAssociative();
        
        return $data ? $data['name'] : null;
    }

    private function escapeCsvField(string $field): string
    {
        // Échapper les guillemets et les points-virgules pour CSV
        return str_replace(['"', ';', "\n", "\r"], ['""', '&#59;', ' ', ' '], $field);
    }

    private function extractBrowserFromUserAgent(string $userAgent): string
    {
        if (strpos($userAgent, 'Chrome') !== false) return 'Chrome';
        if (strpos($userAgent, 'Firefox') !== false) return 'Firefox';
        if (strpos($userAgent, 'Safari') !== false) return 'Safari';
        if (strpos($userAgent, 'Edge') !== false) return 'Edge';
        if (strpos($userAgent, 'Opera') !== false) return 'Opera';
        
        return 'Unknown';
    }

    private function extractOSFromUserAgent(string $userAgent): string
    {
        if (strpos($userAgent, 'Windows') !== false) return 'Windows';
        if (strpos($userAgent, 'Mac OS') !== false) return 'macOS';
        if (strpos($userAgent, 'Linux') !== false) return 'Linux';
        if (strpos($userAgent, 'Android') !== false) return 'Android';
        if (strpos($userAgent, 'iOS') !== false) return 'iOS';
        
        return 'Unknown';
    }

    // Méthodes de compatibilité pour les anciens appels
    private function extractBrowser(array $context): string
    {
        $userAgent = $context['request']['headers']['User-Agent'] ?? $context['user_agent'] ?? '';
        return $this->extractBrowserFromUserAgent($userAgent);
    }

    private function extractOS(array $context): string
    {
        $userAgent = $context['request']['headers']['User-Agent'] ?? $context['user_agent'] ?? '';
        return $this->extractOSFromUserAgent($userAgent);
    }

    private function estimateSeverity($error): string
    {
        $occurrences = $error->getOccurrenceCount();
        $errorType = strtolower($error->getErrorType() ?: '');
        
        // Erreurs critiques
        if (strpos($errorType, 'fatal') !== false || 
            strpos($errorType, 'critical') !== false ||
            strpos($errorType, 'security') !== false) {
            return 'Critique';
        }
        
        // Basé sur la fréquence
        if ($occurrences > 100) return 'Haute';
        if ($occurrences > 20) return 'Moyenne';
        if ($occurrences > 5) return 'Faible';
        
        return 'Très faible';
    }

    private function estimateComplexity($error): string
    {
        $stackTrace = $error->getStackTrace() ?: '';
        $message = $error->getMessage() ?: '';
        
        // Analyse de la complexité basée sur la stack trace et le message
        $stackLines = substr_count($stackTrace, "\n");
        $hasDatabase = strpos($message, 'SQL') !== false || strpos($message, 'database') !== false;
        $hasNetwork = strpos($message, 'curl') !== false || strpos($message, 'network') !== false;
        $hasMemory = strpos($message, 'memory') !== false || strpos($message, 'allocation') !== false;
        
        if ($stackLines > 20 || $hasDatabase || $hasNetwork) return 'Complexe';
        if ($stackLines > 10 || $hasMemory) return 'Moyenne';
        if ($stackLines > 5) return 'Simple';
        
        return 'Très simple';
    }

    private function estimateUserImpact($error): string
    {
        $occurrences = $error->getOccurrenceCount();
        $errorType = strtolower($error->getErrorType() ?: '');
        $message = strtolower($error->getMessage() ?: '');
        
        // Erreurs bloquantes
        if (strpos($errorType, 'fatal') !== false || 
            strpos($message, '500') !== false ||
            strpos($message, 'unavailable') !== false) {
            return 'Bloquant';
        }
        
        // Erreurs dégradantes
        if (strpos($message, '404') !== false || 
            strpos($message, 'timeout') !== false ||
            $occurrences > 50) {
            return 'Dégradant';
        }
        
        // Basé sur la fréquence
        if ($occurrences > 20) return 'Modéré';
        if ($occurrences > 5) return 'Faible';
        
        return 'Minimal';
    }

    private function calculatePeakActivity(int $errorId): string
    {
        $sql = "
            SELECT 
                HOUR(occurred_at) as hour,
                COUNT(*) as count
            FROM error_occurrences 
            WHERE error_group_id = :errorId
            GROUP BY HOUR(occurred_at)
            ORDER BY count DESC
            LIMIT 1
        ";
        
        $conn = $this->entityManager->getConnection();
        $stmt = $conn->prepare($sql);
        $result = $stmt->executeQuery(['errorId' => $errorId]);
        $data = $result->fetchAssociative();
        
        if ($data) {
            $hour = (int)$data['hour'];
            $count = (int)$data['count'];
            return sprintf("%02d:00 (%d occurrences)", $hour, $count);
        }
        
        return 'Non déterminé';
    }

    private function generateErrorTags($error): string
    {
        $tags = [];
        $message = strtolower($error->getMessage() ?: '');
        $errorType = strtolower($error->getErrorType() ?: '');
        $file = strtolower($error->getFile() ?: '');
        
        // Tags basés sur le type d'erreur
        if (strpos($errorType, 'syntax') !== false) $tags[] = 'syntaxe';
        if (strpos($errorType, 'fatal') !== false) $tags[] = 'fatal';
        if (strpos($errorType, 'warning') !== false) $tags[] = 'avertissement';
        if (strpos($errorType, 'notice') !== false) $tags[] = 'notice';
        
        // Tags basés sur le message
        if (strpos($message, 'sql') !== false || strpos($message, 'database') !== false) $tags[] = 'base-de-données';
        if (strpos($message, 'curl') !== false || strpos($message, 'network') !== false) $tags[] = 'réseau';
        if (strpos($message, 'memory') !== false) $tags[] = 'mémoire';
        if (strpos($message, 'permission') !== false) $tags[] = 'permissions';
        if (strpos($message, 'timeout') !== false) $tags[] = 'timeout';
        if (strpos($message, '404') !== false) $tags[] = 'page-introuvable';
        if (strpos($message, '500') !== false) $tags[] = 'erreur-serveur';
        
        // Tags basés sur le fichier
        if (strpos($file, 'vendor') !== false) $tags[] = 'dépendance';
        if (strpos($file, 'config') !== false) $tags[] = 'configuration';
        if (strpos($file, 'controller') !== false) $tags[] = 'contrôleur';
        if (strpos($file, 'model') !== false) $tags[] = 'modèle';
        if (strpos($file, 'view') !== false || strpos($file, 'template') !== false) $tags[] = 'vue';
        
        // Tags basés sur la fréquence
        if ($error->getOccurrenceCount() > 100) $tags[] = 'fréquent';
        if ($error->getOccurrenceCount() < 5) $tags[] = 'rare';
        
        // Tags basés sur l'âge
        $ageInDays = (time() - $error->getFirstSeen()->getTimestamp()) / (24 * 3600);
        if ($ageInDays > 30) $tags[] = 'ancien';
        if ($ageInDays < 1) $tags[] = 'nouveau';
        
        return implode(', ', $tags);
    }

    private function getAdvancedExportStatistics(array $filters): array
    {
        $sql = "
            SELECT 
                COUNT(DISTINCT eg.id) as total_errors,
                COUNT(DISTINCT eg.project) as affected_projects,
                SUM(eg.occurrence_count) as total_occurrences,
                AVG(eg.occurrence_count) as avg_occurrences_per_error,
                COUNT(CASE WHEN eg.status = 'open' THEN 1 END) as open_errors,
                COUNT(CASE WHEN eg.status = 'resolved' THEN 1 END) as resolved_errors,
                COUNT(CASE WHEN eg.status = 'ignored' THEN 1 END) as ignored_errors,
                MIN(eg.first_seen) as earliest_error,
                MAX(eg.last_seen) as latest_error,
                COUNT(CASE WHEN eg.first_seen >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 END) as new_errors_24h,
                COUNT(CASE WHEN eg.last_seen >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 END) as active_errors_24h
            FROM error_groups eg
            JOIN projects p ON eg.project_id = p.id
            WHERE p.owner_id = :userId
        ";
        
        $params = ['userId' => $filters['user']->getId()];
        
        if (isset($filters['since'])) {
            $sql .= " AND eg.last_seen >= :since";
            $params['since'] = $filters['since']->format('Y-m-d H:i:s');
        }
        
        if (isset($filters['until'])) {
            $sql .= " AND eg.last_seen <= :until";
            $params['until'] = $filters['until']->format('Y-m-d H:i:s');
        }
        
        if (isset($filters['project'])) {
            $sql .= " AND eg.project = :project";
            $params['project'] = $filters['project'];
        }
        
        $conn = $this->entityManager->getConnection();
        $stmt = $conn->prepare($sql);
        $result = $stmt->executeQuery($params);
        
        return $result->fetchAssociative() ?: [];
    }

    private function generateStatsSummary(array $stats): string
    {
        if (empty($stats)) {
            return "\n\n;Résumé Statistique;Aucune donnée disponible\n";
        }
        
        $summary = "\n\n;=== RÉSUMÉ STATISTIQUE ===;\n";
        $summary .= ";Métrique;Valeur\n";
        $summary .= sprintf(";Total des erreurs;%d\n", $stats['total_errors'] ?? 0);
        $summary .= sprintf(";Projets affectés;%d\n", $stats['affected_projects'] ?? 0);
        $summary .= sprintf(";Total des occurrences;%d\n", $stats['total_occurrences'] ?? 0);
        $summary .= sprintf(";Moyenne occurrences par erreur;%.2f\n", $stats['avg_occurrences_per_error'] ?? 0);
        $summary .= sprintf(";Erreurs ouvertes;%d\n", $stats['open_errors'] ?? 0);
        $summary .= sprintf(";Erreurs résolues;%d\n", $stats['resolved_errors'] ?? 0);
        $summary .= sprintf(";Erreurs ignorées;%d\n", $stats['ignored_errors'] ?? 0);
        
        if ($stats['total_errors'] > 0) {
            $resolutionRate = ($stats['resolved_errors'] / $stats['total_errors']) * 100;
            $summary .= sprintf(";Taux de résolution;%.1f%%\n", $resolutionRate);
        }
        
        $summary .= sprintf(";Nouvelles erreurs (24h);%d\n", $stats['new_errors_24h'] ?? 0);
        $summary .= sprintf(";Erreurs actives (24h);%d\n", $stats['active_errors_24h'] ?? 0);
        
        if ($stats['earliest_error']) {
            $summary .= sprintf(";Première erreur;%s\n", $stats['earliest_error']);
        }
        
        if ($stats['latest_error']) {
            $summary .= sprintf(";Dernière erreur;%s\n", $stats['latest_error']);
        }
        
        $summary .= sprintf(";Rapport généré le;%s\n", date('Y-m-d H:i:s'));
        
        return $summary;
    }

    private function exportJson(array $filters): Response
    {
        $errors = $this->errorGroupRepository->findWithFilters($filters, 'lastSeen', 'DESC', 1000);
        $advancedStats = $this->getAdvancedExportStatistics($filters);
        
        $data = [
            'export_info' => [
                'generated_at' => date('Y-m-d H:i:s'),
                'period' => [
                    'from' => $filters['since']->format('Y-m-d H:i:s'),
                    'to' => $filters['until']->format('Y-m-d H:i:s')
                ],
                'project' => $filters['project'] ?? 'all',
                'total_errors' => count($errors)
            ],
            'statistics' => $advancedStats,
            'errors' => []
        ];
        
        foreach ($errors as $error) {
            $context = $error->getLatestContext();
            $request = $error->getLatestRequest();
            $userAgent = $error->getLatestUserAgent() ?: '';
            
            $data['errors'][] = [
                'id' => $error->getId(),
                'title' => $error->getTitle(),
                'message' => $error->getMessage(),
                'project' => $error->getProject(),
                'error_type' => $error->getErrorType(),
                'status' => $error->getStatus(),
                'occurrence_count' => $error->getOccurrenceCount(),
                'first_seen' => $error->getFirstSeen()->format('c'),
                'last_seen' => $error->getLastSeen()->format('c'),
                'resolved_at' => $error->getResolvedAt()?->format('c'),
                'resolved_by' => $error->getResolvedBy(),
                'file' => $error->getFile(),
                'line' => $error->getLine(),
                'stack_trace' => $error->getStackTrace(),
                'environment' => $error->getEnvironment(),
                'http_status_code' => $error->getHttpStatusCode(),
                'context' => $context,
                'request' => $request,
                'user_agent' => $userAgent,
                'analytics' => [
                    'severity' => $this->estimateSeverity($error),
                    'complexity' => $this->estimateComplexity($error),
                    'user_impact' => $this->estimateUserImpact($error),
                    'tags' => explode(', ', $this->generateErrorTags($error)),
                    'peak_activity' => $this->calculatePeakActivity($error->getId()),
                    'browser' => $this->extractBrowserFromUserAgent($userAgent),
                    'os' => $this->extractOSFromUserAgent($userAgent)
                ]
            ];
        }
        
        $response = new Response();
        $response->headers->set('Content-Type', 'application/json; charset=utf-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="analytics-data-' . date('Y-m-d-H-i') . '.json"');
        $response->setContent(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        
        return $response;
    }

    private function exportSummary(array $filters): Response
    {
        $advancedStats = $this->getAdvancedExportStatistics($filters);
        $errors = $this->errorGroupRepository->findWithFilters($filters, 'lastSeen', 'DESC', 10);
        
        // Générer un rapport HTML
        $html = $this->generateExecutiveSummaryHtml($advancedStats, $errors, $filters);
        
        $response = new Response();
        $response->headers->set('Content-Type', 'text/html; charset=utf-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="resume-executif-' . date('Y-m-d-H-i') . '.html"');
        $response->setContent($html);
        
        return $response;
    }

    private function generateExecutiveSummaryHtml(array $stats, array $topErrors, array $filters): string
    {
        $projectFilter = $filters['project'] ?? 'Tous les projets';
        $period = $filters['until']->diff($filters['since'])->days;
        
        $html = '<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Résumé Exécutif - Analytics Error Explorer</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; color: #333; line-height: 1.6; }
        .header { background: #3b82f6; color: white; padding: 30px; margin: -40px -40px 40px -40px; }
        .header h1 { margin: 0; font-size: 28px; }
        .header .subtitle { margin: 10px 0 0 0; opacity: 0.9; }
        .section { margin: 30px 0; }
        .section h2 { color: #1f2937; border-bottom: 2px solid #e5e7eb; padding-bottom: 10px; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin: 20px 0; }
        .stat-card { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px; padding: 20px; text-align: center; }
        .stat-value { font-size: 32px; font-weight: bold; color: #3b82f6; }
        .stat-label { color: #6b7280; margin-top: 5px; }
        .error-table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        .error-table th, .error-table td { border: 1px solid #e5e7eb; padding: 12px; text-align: left; }
        .error-table th { background: #f3f4f6; font-weight: bold; }
        .status-open { color: #dc2626; font-weight: bold; }
        .status-resolved { color: #16a34a; font-weight: bold; }
        .status-ignored { color: #6b7280; font-weight: bold; }
        .insight { background: #eff6ff; border-left: 4px solid #3b82f6; padding: 15px; margin: 20px 0; }
        .footer { margin-top: 50px; padding-top: 20px; border-top: 1px solid #e5e7eb; color: #6b7280; font-size: 14px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Résumé Exécutif - Error Analytics</h1>
        <div class="subtitle">Période: ' . $filters['since']->format('d/m/Y') . ' - ' . $filters['until']->format('d/m/Y') . ' | Projet: ' . htmlspecialchars($projectFilter) . '</div>
    </div>

    <div class="section">
        <h2>🎯 Vue d\'ensemble</h2>
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value">' . number_format($stats['total_errors'] ?? 0) . '</div>
                <div class="stat-label">Erreurs Total</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">' . number_format($stats['total_occurrences'] ?? 0) . '</div>
                <div class="stat-label">Occurrences</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">' . ($stats['total_errors'] > 0 ? round(($stats['resolved_errors'] / $stats['total_errors']) * 100, 1) : 0) . '%</div>
                <div class="stat-label">Taux de Résolution</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">' . ($stats['affected_projects'] ?? 0) . '</div>
                <div class="stat-label">Projets Affectés</div>
            </div>
        </div>
    </div>';

        if ($stats['total_errors'] > 0) {
            $resolutionRate = ($stats['resolved_errors'] / $stats['total_errors']) * 100;
            
            $html .= '
    <div class="section">
        <h2>💡 Insights Clés</h2>';
            
            if ($resolutionRate >= 80) {
                $html .= '<div class="insight">✅ <strong>Excellent taux de résolution (' . round($resolutionRate, 1) . '%)</strong> - Votre équipe gère efficacement les erreurs.</div>';
            } elseif ($resolutionRate >= 60) {
                $html .= '<div class="insight">⚠️ <strong>Taux de résolution modéré (' . round($resolutionRate, 1) . '%)</strong> - Il y a des opportunités d\'amélioration.</div>';
            } else {
                $html .= '<div class="insight">🚨 <strong>Taux de résolution faible (' . round($resolutionRate, 1) . '%)</strong> - Action prioritaire requise.</div>';
            }
            
            if (($stats['new_errors_24h'] ?? 0) > 0) {
                $html .= '<div class="insight">🆕 <strong>' . $stats['new_errors_24h'] . ' nouvelles erreurs</strong> détectées dans les dernières 24h.</div>';
            }
            
            if (($stats['active_errors_24h'] ?? 0) > ($stats['total_errors'] * 0.5)) {
                $html .= '<div class="insight">⚡ <strong>Activité élevée</strong> - Plus de 50% des erreurs ont été actives récemment.</div>';
            }
            
            $html .= '</div>';
        }

        $html .= '
    <div class="section">
        <h2>🔥 Top 10 des Erreurs</h2>
        <table class="error-table">
            <thead>
                <tr>
                    <th>Erreur</th>
                    <th>Projet</th>
                    <th>Occurrences</th>
                    <th>Statut</th>
                    <th>Dernière Activité</th>
                </tr>
            </thead>
            <tbody>';

        foreach ($topErrors as $error) {
            $daysSinceLastSeen = round((time() - $error->getLastSeen()->getTimestamp()) / (24 * 3600));
            $statusClass = 'status-' . $error->getStatus();
            
            $html .= '
                <tr>
                    <td>' . htmlspecialchars(substr($error->getTitle(), 0, 60)) . '...</td>
                    <td>' . htmlspecialchars($error->getProject()) . '</td>
                    <td>' . number_format($error->getOccurrenceCount()) . '</td>
                    <td class="' . $statusClass . '">' . ucfirst($error->getStatus()) . '</td>
                    <td>' . ($daysSinceLastSeen == 0 ? 'Aujourd\'hui' : "Il y a {$daysSinceLastSeen} jour(s)") . '</td>
                </tr>';
        }

        $html .= '
            </tbody>
        </table>
    </div>

    <div class="section">
        <h2>📊 Recommandations</h2>';
        
        $recommendations = [];
        
        if (($stats['open_errors'] ?? 0) > ($stats['total_errors'] * 0.3)) {
            $recommendations[] = 'Prioriser la résolution des erreurs ouvertes (' . $stats['open_errors'] . ' en attente)';
        }
        
        if (($stats['new_errors_24h'] ?? 0) > 5) {
            $recommendations[] = 'Investiguer la cause de l\'augmentation récente des nouvelles erreurs';
        }
        
        if (($stats['avg_occurrences_per_error'] ?? 0) > 10) {
            $recommendations[] = 'Analyser les erreurs récurrentes pour identifier des patterns systémiques';
        }
        
        if (empty($recommendations)) {
            $recommendations[] = 'Maintenir les bonnes pratiques actuelles de gestion des erreurs';
            $recommendations[] = 'Continuer le monitoring proactif pour détecter les tendances';
        }
        
        foreach ($recommendations as $recommendation) {
            $html .= '<div class="insight">• ' . $recommendation . '</div>';
        }

        $html .= '
    </div>

    <div class="footer">
        <strong>Error Explorer</strong> - Rapport généré le ' . date('d/m/Y à H:i:s') . '<br>
        Ce rapport couvre la période du ' . $filters['since']->format('d/m/Y') . ' au ' . $filters['until']->format('d/m/Y') . ' (' . $period . ' jours)
    </div>
</body>
</html>';

        return $html;
    }
}