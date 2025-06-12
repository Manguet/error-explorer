<?php

namespace App\Controller;

use App\Entity\ErrorGroup;
use App\Repository\ErrorGroupRepository;
use App\Repository\ErrorOccurrenceRepository;
use App\Service\ErrorLimitService;
use App\Service\UpgradeMessageService;
use App\Service\AiSuggestionService;
use Doctrine\ORM\EntityManagerInterface;
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
        private readonly AiSuggestionService $aiSuggestionService
    ) {}

    #[Route('/', name: 'index')]
    public function index(Request $request): Response
    {
        $user = $this->getUser();

        // Vérifier les limites du plan
        if (!$user->isActive() || $user->isPlanExpired()) {
            $this->addFlash('warning', 'Votre abonnement a expiré. Veuillez mettre à jour votre plan.');
        }

        // Récupérer les filtres depuis la requête + filtre utilisateur
        $filters = $this->getFiltersFromRequest($request);
        $filters['user'] = $user; // Filtrer par utilisateur connecté

        // Récupérer les statistiques générales pour cet utilisateur
        $stats = $this->getGlobalStats($filters);
        
        // Récupérer les statistiques d'usage des limites
        $usageStats = $this->errorLimitService->getUsageStats($user);
        
        // Récupérer le message d'upgrade si nécessaire
        $upgradeMessage = $this->upgradeMessageService->getUpgradeMessage($user);

        // Récupérer les groupes d'erreurs avec pagination
        $page = max(1, $request->query->getInt('page', 1));
        $limit = 20;
        $offset = ($page - 1) * $limit;

        $errorGroups = $this->errorGroupRepository->findWithFilters(
            $filters,
            $request->query->get('sort', 'lastSeen'),
            $request->query->get('direction', 'DESC'),
            $limit,
            $offset
        );

        $totalGroups = $this->errorGroupRepository->countWithFilters($filters);
        $totalPages = ceil($totalGroups / $limit);

        // Récupérer la liste des projets de l'utilisateur pour le filtre
        $projects = $this->errorGroupRepository->getDistinctProjectsForUser($user);

        // Nettoyer les filtres pour le template (enlever les objets non-sérialisables)
        $templateFilters = $this->cleanFiltersForTemplate($filters);

        return $this->render('dashboard/index.html.twig', [
            'error_groups' => $errorGroups,
            'stats' => $stats,
            'usage_stats' => $usageStats,
            'upgrade_message' => $upgradeMessage,
            'filters' => $templateFilters, // Utiliser les filtres nettoyés
            'projects' => $projects,
            'user' => $user,
            'pagination' => [
                'current_page' => $page,
                'total_pages' => $totalPages,
                'total_items' => $totalGroups,
                'per_page' => $limit
            ],
            'sort' => $request->query->get('sort', 'lastSeen'),
            'direction' => $request->query->get('direction', 'DESC')
        ]);
    }

    #[Route('/project/{project}', name: 'project')]
    public function project(string $project, Request $request): Response
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

        // Récupérer les statistiques pour ce projet
        $stats = $this->getGlobalStats($filters);
        
        // Récupérer les statistiques d'usage des limites
        $usageStats = $this->errorLimitService->getUsageStats($user);
        
        // Récupérer le message d'upgrade si nécessaire
        $upgradeMessage = $this->upgradeMessageService->getUpgradeMessage($user);

        $page = max(1, $request->query->getInt('page', 1));
        $limit = 20;
        $offset = ($page - 1) * $limit;

        $errorGroups = $this->errorGroupRepository->findWithFilters(
            $filters,
            $request->query->get('sort', 'lastSeen'),
            $request->query->get('direction', 'DESC'),
            $limit,
            $offset
        );

        $totalGroups = $this->errorGroupRepository->countWithFilters($filters);
        $totalPages = ceil($totalGroups / $limit);

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
            'error_groups' => $errorGroups,
            'stats' => $stats,
            'usage_stats' => $usageStats,
            'upgrade_message' => $upgradeMessage,
            'filters' => $templateFilters, // Utiliser les filtres nettoyés
            'metadata' => $metadata,
            'user' => $user,
            'pagination' => [
                'current_page' => $page,
                'total_pages' => $totalPages,
                'total_items' => $totalGroups,
                'per_page' => $limit
            ],
            'sort' => $request->query->get('sort', 'lastSeen'),
            'direction' => $request->query->get('direction', 'DESC')
        ]);
    }

    #[Route('/error/{id}', name: 'error_detail', requirements: ['id' => '\d+'])]
    public function errorDetail(int $id, Request $request): Response
    {
        $user = $this->getUser();
        $errorGroup = $this->errorGroupRepository->find($id);

        if (!$errorGroup) {
            throw $this->createNotFoundException('Groupe d\'erreur non trouvé');
        }

        // Vérifier que l'erreur appartient à l'utilisateur
        if (!$this->errorGroupRepository->belongsToUser($errorGroup, $user)) {
            throw $this->createAccessDeniedException('Vous n\'avez pas accès à cette erreur');
        }

        // Récupérer les occurrences récentes avec pagination
        $page = max(1, $request->query->getInt('page', 1));
        $limit = 10;
        $offset = ($page - 1) * $limit;

        $occurrences = $this->errorOccurrenceRepository->findByErrorGroup(
            $errorGroup,
            $limit,
            $offset
        );

        $totalOccurrences = $this->errorOccurrenceRepository->countByErrorGroup($errorGroup);
        $totalPages = ceil($totalOccurrences / $limit);

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
            'occurrences' => $occurrences,
            'occurrence_stats' => $occurrenceStats,
            'ai_suggestions' => $aiSuggestions,
            'user' => $user,
            'pagination' => [
                'current_page' => $page,
                'total_pages' => $totalPages,
                'total_items' => $totalOccurrences,
                'per_page' => $limit
            ]
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
