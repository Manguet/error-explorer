<?php

namespace App\Controller\Dashboard;

use App\DataTable\Type\ErrorGroupDataTableType;
use App\Entity\ErrorGroup;
use App\Repository\ErrorGroupRepository;
use App\Repository\ErrorOccurrenceRepository;
use App\Service\AiSuggestionService;
use App\Service\Error\ErrorOccurrenceFormatter;
use App\Service\ErrorLimitService;
use App\Service\UpgradeMessageService;
use Doctrine\ORM\EntityManagerInterface;
use Dompdf\Dompdf;
use Dompdf\Options;
use Omines\DataTablesBundle\DataTableFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/dashboard', name: 'dashboard_')]
#[IsGranted('ROLE_USER')]
class DashboardController extends AbstractController
{
    public function __construct(
        private readonly ErrorGroupRepository $errorGroupRepository,
        private readonly ErrorOccurrenceRepository $errorOccurrenceRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly ErrorLimitService $errorLimitService,
        private readonly UpgradeMessageService $upgradeMessageService,
        private readonly AiSuggestionService $aiSuggestionService,
        private readonly ErrorOccurrenceFormatter $errorOccurrenceFormatter
    ) {}

    #[Route('/', name: 'index')]
    public function index(Request $request, DataTableFactory $dataTableFactory): Response
    {
        $user = $this->getUser();

        // Vérifier les limites du plan
        if (!$user->isActive() || $user->isPlanExpired()) {
            $this->addFlash('warning', 'Votre abonnement a expiré. Veuillez mettre à jour votre plan.');
        }

        // Récupérer les filtres depuis la requête + filtre utilisateur
        $filters = $this->getFiltersFromRequest($request);
        $filters['user'] = $user; // Filtrer par utilisateur connecté

        // Créer le DataTable avec l'utilisateur en contexte
        $table = $dataTableFactory->createFromType(ErrorGroupDataTableType::class, [
            'user' => $user,
            'filters' => $filters
        ])->handleRequest($request);

        // Récupérer les statistiques générales pour cet utilisateur
        $stats = $this->getGlobalStats($filters);

        // Récupérer les statistiques d'usage des limites
        $usageStats = $this->errorLimitService->getUsageStats($user);

        // Récupérer le message d'upgrade si nécessaire
        $upgradeMessage = $this->upgradeMessageService->getUpgradeMessage($user);

        // Si c'est une requête AJAX DataTable, renvoyer la réponse JSON
        if ($table->isCallback()) {
            return $table->getResponse();
        }

        // Récupérer la liste des projets de l'utilisateur pour le filtre
        $projects = $this->errorGroupRepository->getDistinctProjectsForUser($user);

        // Nettoyer les filtres pour le template (enlever les objets non-sérialisables)
        $templateFilters = $this->cleanFiltersForTemplate($filters);

        return $this->render('dashboard/index.html.twig', [
            'datatable' => $table,
            'stats' => $stats,
            'usage_stats' => $usageStats,
            'upgrade_message' => $upgradeMessage,
            'filters' => $templateFilters,
            'projects' => $projects,
            'user' => $user
        ]);
    }

    #[Route('/project/{project}', name: 'project')]
    public function project(string $project, Request $request, DataTableFactory $dataTableFactory): Response
    {
        $user = $this->getUser();
        $projectRepository = $this->entityManager->getRepository(\App\Entity\Project::class);

        // Vérifier que le projet appartient à l'utilisateur
        $projectEntity = $projectRepository->findOneBy([
            'slug' => $project,
            'owner' => $user
        ]);

        if (!$projectEntity) {
            // Vérifier s'il y a des erreurs avec ce nom de projet pour cet utilisateur
            $hasErrors = $this->errorGroupRepository->countWithFilters([
                'project' => $project,
                'user' => $user
            ]);

            if ($hasErrors === 0) {
                $this->addFlash('warning', "Aucun projet '{$project}' trouvé");
                return $this->redirectToRoute('dashboard_index');
            }
        }

        // Forcer le filtre par projet et utilisateur
        $filters = $this->getFiltersFromRequest($request);
        $filters['project'] = $project;
        $filters['user'] = $user;

        // Créer le DataTable pour les erreurs du projet
        $table = $dataTableFactory->createFromType(\App\DataTable\Type\ProjectErrorGroupDataTableType::class, [
            'user' => $user,
            'project' => $project,
            'filters' => $filters
        ])->handleRequest($request);

        // Si c'est une requête AJAX DataTable, renvoyer la réponse JSON
        if ($table->isCallback()) {
            return $table->getResponse();
        }

        // Récupérer les statistiques pour ce projet
        $stats = $this->getGlobalStats($filters);

        // Récupérer les statistiques d'usage des limites
        $usageStats = $this->errorLimitService->getUsageStats($user);

        // Récupérer le message d'upgrade si nécessaire
        $upgradeMessage = $this->upgradeMessageService->getUpgradeMessage($user);

        $metadata = [
            'project_exists' => $projectEntity !== null,
            'project_is_active' => $projectEntity?->isActive() ?? true,
            'has_webhook_configured' => $projectEntity !== null,
            'can_receive_errors' => $projectEntity?->canReceiveError() ?? false,
            'monthly_errors_usage' => $projectEntity?->getCurrentMonthErrors() ?? 0,
            'monthly_errors_limit' => $user->getPlan()?->getMaxMonthlyErrors() ?? 0,
        ];

        // Nettoyer les filtres pour le template
        $templateFilters = $this->cleanFiltersForTemplate($filters);

        return $this->render('dashboard/project.html.twig', [
            'project' => $project,
            'project_entity' => $projectEntity,
            'datatable' => $table,
            'stats' => $stats,
            'usage_stats' => $usageStats,
            'upgrade_message' => $upgradeMessage,
            'filters' => $templateFilters,
            'metadata' => $metadata,
            'user' => $user
        ]);
    }

    #[Route('/project/{projectSlug}/error/{id}', name: 'error_detail', requirements: ['id' => '\d+'])]
    public function errorDetail(string $projectSlug, int $id, Request $request, DataTableFactory $dataTableFactory): Response
    {
        $user = $this->getUser();
        
        // Récupérer l'ErrorGroup avec l'utilisateur assigné et les tags
        $errorGroup = $this->errorGroupRepository->createQueryBuilder('eg')
            ->leftJoin('eg.assignedTo', 'assignedUser')
            ->leftJoin('eg.tags', 'tags')
            ->addSelect('assignedUser', 'tags')
            ->where('eg.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$errorGroup) {
            throw $this->createNotFoundException('Groupe d\'erreur non trouvé');
        }

        // Vérifier que l'erreur appartient à l'utilisateur
        if (!$this->errorGroupRepository->belongsToUser($errorGroup, $user)) {
            throw $this->createAccessDeniedException('Vous n\'avez pas accès à cette erreur');
        }

        // Vérifier que le slug du projet correspond
        if ($errorGroup->getProjectEntity() && $errorGroup->getProjectEntity()->getSlug() !== $projectSlug) {
            throw $this->createNotFoundException('Projet non trouvé');
        }

        // Créer le DataTable pour les occurrences
        $occurrenceTable = $dataTableFactory->createFromType(\App\DataTable\Type\ErrorOccurrenceDataTableType::class, [
            'error_group' => $errorGroup,
            'user' => $user
        ])->handleRequest($request);

        // Si c'est une requête AJAX DataTable, renvoyer la réponse JSON
        if ($occurrenceTable->isCallback()) {
            return $occurrenceTable->getResponse();
        }

        // Récupérer quelques occurrences récentes pour l'affichage initial
        $recentOccurrences = $this->errorOccurrenceRepository->findByErrorGroup($errorGroup, 5, 0);

        // Récupérer les statistiques d'occurrences par jour (30 derniers jours)
        $occurrenceStats = $this->errorOccurrenceRepository->getOccurrenceStatsForGroup(
            $errorGroup,
            30
        );

        // Générer les suggestions d'IA si nécessaire
        $aiSuggestions = null;
        if (!$errorGroup->hasAiSuggestions() || $errorGroup->isAiSuggestionsExpired()) {
            try {
                $aiSuggestions = $this->aiSuggestionService->generateSuggestions($errorGroup, $user);
                $errorGroup->setAiSuggestions($aiSuggestions);
                $this->entityManager->flush();
            } catch (\Exception $e) {
                // Log l'erreur mais continue sans suggestions
                error_log("Erreur génération suggestions IA: " . $e->getMessage());
            }
        } else {
            $aiSuggestions = $errorGroup->getAiSuggestions();
            // Si les suggestions viennent de la DB, ajouter les métadonnées manquantes
            if ($aiSuggestions && !isset($aiSuggestions['generated_at'])) {
                $aiSuggestions['generated_at'] = $errorGroup->getAiSuggestionsGeneratedAt()?->format('d/m/Y H:i') ?? 'Inconnue';
                $aiSuggestions['source'] = $aiSuggestions['source'] ?? 'rules';
            }
        }

        return $this->render('dashboard/error_detail.html.twig', [
            'error_group' => $errorGroup,
            'project' => $errorGroup->getProjectEntity(),
            'occurrences' => $recentOccurrences,
            'occurrence_table' => $occurrenceTable,
            'occurrence_stats' => $occurrenceStats,
            'ai_suggestions' => $aiSuggestions,
            'user' => $user,
            'error_formatter' => $this->errorOccurrenceFormatter
        ]);
    }

    #[Route('/error/{id}/resolve', name: 'error_resolve', methods: ['POST'])]
    public function resolveError(int $id): JsonResponse
    {
        $user = $this->getUser();
        $errorGroup = $this->errorGroupRepository->find($id);

        if (!$errorGroup) {
            return $this->json(['error' => 'Groupe d\'erreur non trouvé'], 404);
        }

        // Vérifier que l'erreur appartient à l'utilisateur
        if (!$this->errorGroupRepository->belongsToUser($errorGroup, $user)) {
            return $this->json(['error' => 'Accès refusé'], 403);
        }

        $errorGroup->resolve();
        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'message' => 'Erreur marquée comme résolue',
            'status' => $errorGroup->getStatus()
        ]);
    }

    #[Route('/error/{id}/ignore', name: 'error_ignore', methods: ['POST'])]
    public function ignoreError(int $id): JsonResponse
    {
        $user = $this->getUser();
        $errorGroup = $this->errorGroupRepository->find($id);

        if (!$errorGroup) {
            return $this->json(['error' => 'Groupe d\'erreur non trouvé'], 404);
        }

        // Vérifier que l'erreur appartient à l'utilisateur
        if (!$this->errorGroupRepository->belongsToUser($errorGroup, $user)) {
            return $this->json(['error' => 'Accès refusé'], 403);
        }

        $errorGroup->ignore();
        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'message' => 'Erreur ignorée',
            'status' => $errorGroup->getStatus()
        ]);
    }

    #[Route('/error/{id}/reopen', name: 'error_reopen', methods: ['POST'])]
    public function reopenError(int $id): JsonResponse
    {
        $user = $this->getUser();
        $errorGroup = $this->errorGroupRepository->find($id);

        if (!$errorGroup) {
            return $this->json(['error' => 'Groupe d\'erreur non trouvé'], 404);
        }

        // Vérifier que l'erreur appartient à l'utilisateur
        if (!$this->errorGroupRepository->belongsToUser($errorGroup, $user)) {
            return $this->json(['error' => 'Accès refusé'], 403);
        }

        $errorGroup->reopen();
        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'message' => 'Erreur rouverte',
            'status' => $errorGroup->getStatus()
        ]);
    }

    #[Route('/error/{id}/generate-suggestions', name: 'error_generate_suggestions', methods: ['POST'])]
    public function generateAiSuggestions(int $id): JsonResponse
    {
        $user = $this->getUser();
        $errorGroup = $this->errorGroupRepository->find($id);

        if (!$errorGroup) {
            return $this->json(['error' => 'Groupe d\'erreur non trouvé'], 404);
        }

        // Vérifier que l'erreur appartient à l'utilisateur
        if (!$this->errorGroupRepository->belongsToUser($errorGroup, $user)) {
            return $this->json(['error' => 'Accès refusé'], 403);
        }

        try {
            $suggestions = $this->aiSuggestionService->generateSuggestions($errorGroup, $user);
            $errorGroup->setAiSuggestions($suggestions);
            $this->entityManager->flush();

            return $this->json([
                'success' => true,
                'message' => 'Suggestions générées avec succès',
                'suggestions' => $suggestions
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => 'Erreur lors de la génération des suggestions: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/api/stats', name: 'api_stats')]
    public function apiStats(Request $request): JsonResponse
    {
        $user = $this->getUser();
        $filters = $this->getFiltersFromRequest($request);
        $filters['user'] = $user; // Toujours filtrer par utilisateur

        $stats = $this->getGlobalStats($filters);

        return $this->json($stats);
    }

    #[Route('/api/occurrence-chart/{id}', name: 'api_occurrence_chart', requirements: ['id' => '\d+'])]
    public function occurrenceChart(int $id, Request $request): JsonResponse
    {
        $user = $this->getUser();
        $errorGroup = $this->errorGroupRepository->find($id);

        if (!$errorGroup) {
            return $this->json(['error' => 'Groupe d\'erreur non trouvé'], 404);
        }

        // Vérifier que l'erreur appartient à l'utilisateur
        if (!$this->errorGroupRepository->belongsToUser($errorGroup, $user)) {
            return $this->json(['error' => 'Accès refusé'], 403);
        }

        $days = $request->query->getInt('days', 30);
        $stats = $this->errorOccurrenceRepository->getOccurrenceStatsForGroup($errorGroup, $days);

        return $this->json($stats);
    }

    #[Route('/api/occurrence/{id}/details', name: 'api_occurrence_details')]
    public function occurrenceDetails(int $id): JsonResponse
    {
        $user = $this->getUser();
        $occurrence = $this->errorOccurrenceRepository->find($id);

        if (!$occurrence) {
            return $this->json(['error' => 'Occurrence non trouvée'], 404);
        }

        // Vérifier que l'occurrence appartient à l'utilisateur
        if (!$this->errorGroupRepository->belongsToUser($occurrence->getErrorGroup(), $user)) {
            return $this->json(['error' => 'Accès refusé'], 403);
        }

        // Extraire les données des propriétés JSON
        $requestData = $occurrence->getRequest();
        $serverData = $occurrence->getServer();
        $contextData = $occurrence->getContext();

        // Formatter les données pour l'affichage
        $details = [
            'id' => $occurrence->getId(),
            'created_at' => $occurrence->getCreatedAt()->format('d/m/Y H:i:s'),
            'user_agent' => $occurrence->getUserAgent(),
            'ip_address' => $occurrence->getIpAddress(),
            'url' => $occurrence->getUrl(),
            'http_method' => $occurrence->getHttpMethod(),
            'environment' => $occurrence->getEnvironment(),
            'user_id' => $occurrence->getUserId(),
            'session_id' => $requestData['session_id'] ?? $serverData['session_id'] ?? null,
            'memory_usage' => $occurrence->getMemoryUsage(),
            'execution_time' => $occurrence->getExecutionTime(),
            'commit_hash' => $occurrence->getCommitHash(),
            'request_data' => $requestData,
            'server_data' => $serverData,
            'context' => $contextData,
            'headers' => $requestData['headers'] ?? $serverData['headers'] ?? null,
            'cookies' => $requestData['cookies'] ?? $serverData['cookies'] ?? null,
            'breadcrumbs' => $contextData['breadcrumbs'] ?? null,
            'stack_trace' => $occurrence->getStackTrace(),
            'error_group' => [
                'id' => $occurrence->getErrorGroup()->getId(),
                'title' => $occurrence->getErrorGroup()->getTitle(),
                'message' => $occurrence->getErrorGroup()->getMessage(),
                'file' => $occurrence->getErrorGroup()->getFile(),
                'line' => $occurrence->getErrorGroup()->getLine(),
            ]
        ];

        return $this->json(['success' => true, 'occurrence' => $details]);
    }

    #[Route('/error/{id}/export-occurrences', name: 'error_export_occurrences', requirements: ['id' => '\d+'])]
    public function exportOccurrences(int $id, Request $request): Response
    {
        try {
            $user = $this->getUser();
            $errorGroup = $this->errorGroupRepository->find($id);

            if (!$errorGroup) {
                throw $this->createNotFoundException('Groupe d\'erreur non trouvé');
            }

            // Vérifier que l'erreur appartient à l'utilisateur
            if (!$this->errorGroupRepository->belongsToUser($errorGroup, $user)) {
                throw $this->createAccessDeniedException('Vous n\'avez pas accès à cette erreur');
            }

            $format = $request->query->get('format', 'csv'); // csv, json, pdf
            $limit = $request->query->getInt('limit', 100); // Limiter le nombre d'occurrences

            // Récupérer les occurrences
            $occurrences = $this->errorOccurrenceRepository->findByErrorGroup($errorGroup, $limit, 0);

            switch ($format) {
                case 'json':
                    return $this->exportAsJson($errorGroup, $occurrences);
                case 'pdf':
                    return $this->exportAsPdf($errorGroup, $occurrences);
                default:
                    return $this->exportAsCsv($errorGroup, $occurrences);
            }
        } catch (\Exception $e) {
            // En cas d'erreur, retourner une réponse d'erreur
            $response = new Response();
            $response->headers->set('Content-Type', 'application/json');
            $response->setStatusCode(500);
            $response->setContent(json_encode([
                'error' => 'Erreur serveur lors de l\'export',
                'message' => $e->getMessage(),
                'type' => get_class($e)
            ]));
            return $response;
        }
    }

    private function exportAsCsv($errorGroup, $occurrences): Response
    {
        $filename = sprintf('occurrences_%s_%s.csv', 
            $errorGroup->getProject(), 
            date('Y-m-d_H-i-s')
        );

        $response = new Response();
        $response->headers->set('Content-Type', 'text/csv');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');

        $csv = [];
        $csv[] = [
            'ID',
            'Date',
            'URL',
            'Méthode HTTP',
            'Adresse IP',
            'User Agent',
            'Environnement',
            'Utilisateur ID',
            'Usage mémoire',
            'Temps exécution',
            'Hash commit',
            'Tags',
            'Assigné à'
        ];

        // Récupérer les tags et l'assignation une seule fois
        $tagsString = implode(', ', $errorGroup->getTagNames());
        $assignedTo = $errorGroup->getAssignedTo() ? $errorGroup->getAssignedTo()->getFullName() : '';

        foreach ($occurrences as $occurrence) {
            $csv[] = [
                $occurrence->getId(),
                $occurrence->getCreatedAt()->format('Y-m-d H:i:s'),
                $occurrence->getUrl(),
                $occurrence->getHttpMethod(),
                $occurrence->getIpAddress(),
                $occurrence->getUserAgent(),
                $occurrence->getEnvironment(),
                $occurrence->getUserId(),
                $occurrence->getMemoryUsage(),
                $occurrence->getExecutionTime(),
                $occurrence->getCommitHash(),
                $tagsString,
                $assignedTo
            ];
        }

        $output = fopen('php://temp', 'r+');
        foreach ($csv as $row) {
            fputcsv($output, $row, ';'); // Utiliser ; comme séparateur pour Excel
        }
        rewind($output);
        $content = stream_get_contents($output);
        fclose($output);

        $response->setContent($content);
        return $response;
    }

    private function exportAsJson($errorGroup, $occurrences): Response
    {
        try {
            $filename = sprintf('occurrences_%s_%s.json', 
                preg_replace('/[^a-zA-Z0-9_-]/', '_', $errorGroup->getProject()), 
                date('Y-m-d_H-i-s')
            );

            $data = [
                'error_group' => [
                    'id' => $errorGroup->getId(),
                    'title' => $this->sanitizeString($errorGroup->getExceptionClass()),
                    'message' => $this->sanitizeString($errorGroup->getMessage()),
                    'file' => $this->sanitizeString($errorGroup->getFile()),
                    'line' => $errorGroup->getLine(),
                    'project' => $this->sanitizeString($errorGroup->getProject()),
                    'status' => $errorGroup->getStatus(),
                    'first_seen' => $errorGroup->getFirstSeen() ? $errorGroup->getFirstSeen()->format('c') : null,
                    'last_seen' => $errorGroup->getLastSeen() ? $errorGroup->getLastSeen()->format('c') : null,
                    'occurrence_count' => count($occurrences),
                    'tags' => $errorGroup->getTagsAsArray(),
                    'assigned_to' => $errorGroup->getAssignedTo() ? [
                        'id' => $errorGroup->getAssignedTo()->getId(),
                        'name' => $errorGroup->getAssignedTo()->getFullName(),
                        'email' => $errorGroup->getAssignedTo()->getEmail()
                    ] : null
                ],
                'occurrences' => []
            ];

            foreach ($occurrences as $occurrence) {
                try {
                    $occurrenceData = [
                        'id' => $occurrence->getId(),
                        'created_at' => $occurrence->getCreatedAt() ? $occurrence->getCreatedAt()->format('c') : null,
                        'url' => $this->sanitizeString($occurrence->getUrl()),
                        'http_method' => $this->sanitizeString($occurrence->getHttpMethod()),
                        'ip_address' => $this->sanitizeString($occurrence->getIpAddress()),
                        'user_agent' => $this->sanitizeString($occurrence->getUserAgent()),
                        'environment' => $this->sanitizeString($occurrence->getEnvironment()),
                        'user_id' => $occurrence->getUserId(),
                        'memory_usage' => $occurrence->getMemoryUsage(),
                        'execution_time' => $occurrence->getExecutionTime(),
                        'commit_hash' => $this->sanitizeString($occurrence->getCommitHash()),
                    ];

                    // Ajouter les données complexes avec protection
                    try {
                        // Stack trace
                        $stackTrace = $occurrence->getStackTrace();
                        $occurrenceData['stack_trace'] = $stackTrace ? substr($this->sanitizeString($stackTrace), 0, 5000) : null;
                        
                        // Request data
                        try {
                            $requestData = $occurrence->getRequest();
                            $occurrenceData['request_data'] = is_array($requestData) ? $this->sanitizeJsonData($requestData) : [];
                        } catch (\Exception $e) {
                            $occurrenceData['request_data'] = [];
                        }
                        
                        // Server data
                        try {
                            $serverData = $occurrence->getServer();
                            $occurrenceData['server_data'] = is_array($serverData) ? $this->sanitizeJsonData($serverData) : [];
                        } catch (\Exception $e) {
                            $occurrenceData['server_data'] = [];
                        }
                        
                        // Context data
                        try {
                            $contextData = $occurrence->getContext();
                            $occurrenceData['context'] = is_array($contextData) ? $this->sanitizeJsonData($contextData) : [];
                        } catch (\Exception $e) {
                            $occurrenceData['context'] = [];
                        }
                    } catch (\Exception $e) {
                        // En cas d'erreur, on met des valeurs par défaut
                        $occurrenceData['stack_trace'] = 'Erreur lors de la récupération: ' . $e->getMessage();
                        $occurrenceData['request_data'] = [];
                        $occurrenceData['server_data'] = [];
                        $occurrenceData['context'] = [];
                    }

                    $data['occurrences'][] = $occurrenceData;
                } catch (\Exception $e) {
                    // Si une occurrence pose problème, on continue avec les autres
                    $data['occurrences'][] = [
                        'id' => isset($occurrence) ? $occurrence->getId() : 'unknown',
                        'error' => 'Erreur lors de la récupération de cette occurrence: ' . $e->getMessage()
                    ];
                }
            }

            // Utiliser les options JSON disponibles
            $jsonOptions = JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR;
            // Ajouter JSON_INVALID_UTF8_SUBSTITUTE si disponible (PHP 7.2+)
            if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
                $jsonOptions |= JSON_INVALID_UTF8_SUBSTITUTE;
            }
            $jsonContent = json_encode($data, $jsonOptions);
            
            if ($jsonContent === false) {
                throw new \Exception('Erreur lors de l\'encodage JSON: ' . json_last_error_msg());
            }

            $response = new Response();
            $response->headers->set('Content-Type', 'application/json; charset=utf-8');
            $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');
            $response->setContent($jsonContent);

            return $response;
            
        } catch (\Exception $e) {
            // Log l'erreur pour debug
            error_log('Export JSON Error: ' . $e->getMessage() . ' - Trace: ' . $e->getTraceAsString());
            
            // En cas d'erreur, retourner un JSON d'erreur simple
            $errorData = [
                'error' => 'Erreur lors de l\'export JSON',
                'message' => $e->getMessage(),
                'error_group_id' => $errorGroup->getId(),
                'debug_trace' => $e->getTraceAsString()
            ];
            
            $response = new Response();
            $response->headers->set('Content-Type', 'application/json; charset=utf-8');
            $response->headers->set('Content-Disposition', 'attachment; filename="error_export.json"');
            $response->setContent(json_encode($errorData, JSON_PRETTY_PRINT | JSON_PARTIAL_OUTPUT_ON_ERROR));
            
            return $response;
        }
    }

    private function sanitizeString(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        
        // Supprimer les caractères de contrôle qui peuvent causer des problèmes JSON
        return preg_replace('/[\x00-\x1F\x7F]/', '', $value);
    }

    private function sanitizeJsonData($data)
    {
        if ($data === null) {
            return null;
        }
        
        if (is_array($data)) {
            $sanitized = [];
            foreach ($data as $key => $value) {
                try {
                    $sanitizedKey = $this->sanitizeString((string)$key);
                    if (is_string($value)) {
                        $sanitized[$sanitizedKey] = $this->sanitizeString($value);
                    } elseif (is_array($value)) {
                        // Récursion pour les tableaux imbriqués
                        $sanitized[$sanitizedKey] = $this->sanitizeJsonData($value);
                    } elseif (is_object($value)) {
                        // Convertir les objets en tableaux
                        $sanitized[$sanitizedKey] = $this->sanitizeJsonData((array)$value);
                    } else {
                        $sanitized[$sanitizedKey] = $value;
                    }
                } catch (\Exception $e) {
                    // En cas d'erreur, ignorer cette clé
                    continue;
                }
            }
            return $sanitized;
        }
        
        return $data;
    }

    private function exportAsPdf($errorGroup, $occurrences): Response
    {
        $filename = sprintf('error_report_%s_%s.pdf', 
            preg_replace('/[^a-zA-Z0-9_-]/', '_', $errorGroup->getProject()), 
            date('Y-m-d_H-i-s')
        );

        // Préparer les données pour le template
        $data = [
            'error_group' => $errorGroup,
            'occurrences' => $occurrences,
            'generated_at' => new \DateTime(),
            'total_occurrences' => count($occurrences)
        ];

        // Générer le HTML à partir du template Twig
        $html = $this->renderView('exports/error_report.html.twig', $data);

        // Configurer DomPDF
        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', true);
        $options->set('isHtml5ParserEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        // Créer la réponse
        $response = new Response($dompdf->output());
        $response->headers->set('Content-Type', 'application/pdf');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');

        return $response;
    }

    /**
     * Extrait les filtres depuis la requête
     */
    private function getFiltersFromRequest(Request $request): array
    {
        $filters = [];

        // Filtre par projet
        if ($project = $request->query->get('project')) {
            $filters['project'] = $project;
        }

        // Filtre par statut
        if ($status = $request->query->get('status')) {
            $filters['status'] = $status;
        }

        // Filtre par code HTTP
        if ($httpStatus = $request->query->get('http_status')) {
            $filters['http_status'] = (int) $httpStatus;
        }

        // Filtre par type d'erreur
        if ($errorType = $request->query->get('error_type')) {
            $filters['error_type'] = $errorType;
        }

        // Filtre par environnement
        if ($environment = $request->query->get('environment')) {
            $filters['environment'] = $environment;
        }

        // Filtre par récence (en jours)
        $days = $request->query->getInt('days', 7);
        if ($days > 0) {
            $filters['since'] = new \DateTime("-{$days} days");
        }

        // Filtre par recherche textuelle
        if ($search = $request->query->get('search')) {
            $filters['search'] = trim($search);
        }

        // Filtre par tags
        if ($tags = $request->query->get('tags')) {
            if (is_string($tags)) {
                // Support pour tags séparés par des virgules
                $filters['tags'] = array_filter(array_map('trim', explode(',', $tags)));
            } elseif (is_array($tags)) {
                $filters['tags'] = array_filter($tags);
            }
            
            // Mode de filtre pour les tags (any/all)
            $filters['tag_mode'] = $request->query->get('tag_mode', 'any');
        }

        // Filtre pour les erreurs sans tags
        if ($request->query->getBoolean('untagged_only')) {
            $filters['untagged_only'] = true;
        }

        return $filters;
    }

    /**
     * Nettoie les filtres pour le template (enlève les objets DateTime)
     */
    private function cleanFiltersForTemplate(array $filters): array
    {
        $cleanFilters = [];

        foreach ($filters as $key => $value) {
            // Ignorer les objets non-sérialisables comme DateTime et User
            if (!is_object($value)) {
                $cleanFilters[$key] = $value;
            } elseif ($value instanceof \DateTime) {
                // Convertir DateTime en string pour l'affichage
                $cleanFilters[$key . '_display'] = $value->format('Y-m-d H:i:s');
            }
            // Ignorer les autres objets (comme User)
        }

        return $cleanFilters;
    }

    /**
     * Récupère les statistiques globales pour un utilisateur
     */
    private function getGlobalStats(array $filters): array
    {
        return [
            'total_errors' => $this->errorGroupRepository->countWithFilters($filters),
            'open_errors' => $this->errorGroupRepository->countWithFilters(
                array_merge($filters, ['status' => ErrorGroup::STATUS_OPEN])
            ),
            'resolved_errors' => $this->errorGroupRepository->countWithFilters(
                array_merge($filters, ['status' => ErrorGroup::STATUS_RESOLVED])
            ),
            'ignored_errors' => $this->errorGroupRepository->countWithFilters(
                array_merge($filters, ['status' => ErrorGroup::STATUS_IGNORED])
            ),
            'total_occurrences' => $this->errorOccurrenceRepository->countWithFilters($filters),
            'occurrences_today' => $this->errorOccurrenceRepository->countWithFilters(
                array_merge($filters, ['since' => new \DateTime('today')])
            ),
            'occurrences_this_week' => $this->errorOccurrenceRepository->countWithFilters(
                array_merge($filters, ['since' => new \DateTime('-7 days')])
            ),
            'top_projects' => $this->errorGroupRepository->getTopProjectsByOccurrences(5, $filters),
            'error_trend' => $this->errorOccurrenceRepository->getErrorTrend(7, $filters)
        ];
    }

}
