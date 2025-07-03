<?php

namespace App\Controller\Dashboard;

use App\DataTable\Type\ProjectDataTableType;
use App\DataTable\Type\ProjectErrorGroupDataTableType;
use App\Entity\ErrorGroup;
use App\Entity\Project;
use App\Repository\ErrorGroupRepository;
use App\Repository\ErrorOccurrenceRepository;
use App\Repository\ProjectRepository;
use App\Service\Email\EmailService;
use App\Service\ErrorLimitService;
use Doctrine\ORM\EntityManagerInterface;
use Omines\DataTablesBundle\DataTableFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/projects', name: 'projects_')]
class ProjectController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ProjectRepository $projectRepository,
        private readonly ErrorGroupRepository $errorGroupRepository,
        private readonly ErrorOccurrenceRepository $errorOccurrenceRepository,
        private readonly ErrorLimitService $errorLimitService
    ) {}

    /**
     * Liste des projets
     */
    #[Route('/', name: 'index')]
    public function index(Request $request, DataTableFactory $dataTableFactory): Response
    {
        $table = $dataTableFactory->createFromType(ProjectDataTableType::class, [
            'user' => $this->getUser()
        ])->handleRequest($request);

        if ($table->isCallback()) {
            return $table->getResponse();
        }

        // Statistiques pour les cartes de résumé pour l'utilisateur connecté
        $stats = $this->projectRepository->getStatsForUser($this->getUser());

        // Ajouter les statistiques d'usage des limites
        $usageStats = $this->errorLimitService->getUsageStats($this->getUser());

        return $this->render('projects/index.html.twig', [
            'datatable' => $table,
            'stats' => $stats,
            'usage_stats' => $usageStats
        ]);
    }

    /**
     * Créer un nouveau projet
     */
    #[Route('/create', name: 'create', methods: ['GET', 'POST'])]
    public function create(Request $request): Response
    {
        $user = $this->getUser();

        // Vérifier si l'utilisateur peut créer un nouveau projet
        if (!$this->errorLimitService->canCreateProject($user)) {
            $stats = $this->errorLimitService->getUsageStats($user);

            if ($stats['plan_expired']) {
                $this->addFlash('error', 'Votre plan a expiré. Veuillez renouveler votre abonnement pour créer de nouveaux projets.');
                return $this->redirectToRoute('home_pricing');
            }

            if (!$user->getPlan()) {
                $this->addFlash('error', 'Vous devez souscrire à un plan pour créer des projets.');
                return $this->redirectToRoute('home_pricing');
            }

            $plan = $user->getPlan();
            $this->addFlash('error', sprintf(
                'Vous avez atteint votre limite de %s projets. Veuillez upgrader votre plan pour créer plus de projets.',
                $plan->getMaxProjectsLabel()
            ));
            return $this->redirectToRoute('projects_index');
        }

        if ($request->isMethod('POST')) {
            $name = trim($request->request->get('name', ''));
            $description = trim($request->request->get('description', ''));
            $environment = trim($request->request->get('environment', ''));
            $notificationEmail = trim($request->request->get('notification_email', ''));
            $repositoryUrl = trim($request->request->get('repository_url', ''));

            // Validation
            $errors = [];

            if (empty($name)) {
                $errors[] = 'Le nom du projet est requis';
            } elseif (strlen($name) > 100) {
                $errors[] = 'Le nom du projet ne peut pas dépasser 100 caractères';
            } elseif ($this->projectRepository->isNameExistsForUser($name, $user)) {
                $errors[] = 'Un projet avec ce nom existe déjà dans vos projets';
            }

            if ($description && strlen($description) > 1000) {
                $errors[] = 'La description ne peut pas dépasser 1000 caractères';
            }

            if ($notificationEmail && !filter_var($notificationEmail, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'L\'email de notification n\'est pas valide';
            }

            if ($repositoryUrl && !filter_var($repositoryUrl, FILTER_VALIDATE_URL)) {
                $errors[] = 'L\'URL du repository n\'est pas valide';
            }

            if (empty($errors)) {
                // Créer le projet
                $project = new Project();
                $project->setName($name)
                    ->setDescription($description ?: null)
                    ->setEnvironment($environment ?: null)
                    ->setNotificationEmail($notificationEmail ?: null)
                    ->setRepositoryUrl($repositoryUrl ?: null)
                    ->setOwner($user)
                ;

                // Générer un slug globalement unique
                $slug = $this->projectRepository->generateUniqueSlug($name);
                $project->setSlug($slug);

                $this->entityManager->persist($project);
                $this->entityManager->flush();

                // Mettre à jour le compteur de projets de l'utilisateur
                $this->errorLimitService->updateProjectCount($user);

                $this->addFlash('success', 'Projet créé avec succès ! Token webhook généré.');

                return $this->redirectToRoute('projects_show', ['slug' => $project->getSlug()]);
            }

            // Afficher les erreurs
            foreach ($errors as $error) {
                $this->addFlash('error', $error);
            }
        }

        return $this->render('projects/create.html.twig');
    }

    /**
     * Exporter la liste des projets
     */
    #[Route('/export', name: 'export', methods: ['GET'])]
    public function export(Request $request): Response
    {
        $user = $this->getUser();
        $format = strtolower($request->query->get('format', 'csv'));

        // Récupérer tous les projets de l'utilisateur
        $projects = $this->projectRepository->findByOwner($user, 1000); // Limite pour éviter les exports trop volumineux

        switch ($format) {
            case 'csv':
                return $this->exportToCsv($projects);
            case 'json':
                return $this->exportToJson($projects);
            case 'xlsx':
                return $this->exportToXlsx($projects);
            default:
                throw $this->createNotFoundException('Format d\'export non supporté');
        }
    }

    /**
     * Éditer un projet
     */
    #[Route('/{slug}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(string $slug, Request $request): Response
    {
        $project = $this->projectRepository->findOneBy(['slug' => $slug, 'owner' => $this->getUser()]);

        if (!$project) {
            throw $this->createNotFoundException('Projet non trouvé');
        }

        if ($request->isMethod('POST')) {
            $name = trim($request->request->get('name', ''));
            $description = trim($request->request->get('description', ''));
            $environment = trim($request->request->get('environment', ''));
            $notificationEmail = trim($request->request->get('notification_email', ''));
            $repositoryUrl = trim($request->request->get('repository_url', ''));

            // Validation
            $errors = [];

            if (empty($name)) {
                $errors[] = 'Le nom du projet est requis';
            } elseif (strlen($name) > 100) {
                $errors[] = 'Le nom du projet ne peut pas dépasser 100 caractères';
            } elseif ($this->projectRepository->isNameExistsForUser($name, $user, $project->getId())) {
                $errors[] = 'Un autre projet avec ce nom existe déjà dans vos projets';
            }

            if ($description && strlen($description) > 1000) {
                $errors[] = 'La description ne peut pas dépasser 1000 caractères';
            }

            if ($notificationEmail && !filter_var($notificationEmail, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'L\'email de notification n\'est pas valide';
            }

            if ($repositoryUrl && !filter_var($repositoryUrl, FILTER_VALIDATE_URL)) {
                $errors[] = 'L\'URL du repository n\'est pas valide';
            }

            if (empty($errors)) {
                $project->setName($name)
                    ->setDescription($description ?: null)
                    ->setEnvironment($environment ?: null)
                    ->setNotificationEmail($notificationEmail ?: null)
                    ->setRepositoryUrl($repositoryUrl ?: null);

                $this->entityManager->flush();

                $this->addFlash('success', 'Projet mis à jour avec succès');

                return $this->redirectToRoute('projects_show', ['slug' => $project->getSlug()]);
            }

            // Afficher les erreurs
            foreach ($errors as $error) {
                $this->addFlash('error', $error);
            }
        }

        return $this->render('projects/edit.html.twig', [
            'project' => $project
        ]);
    }

    /**
     * Régénérer le token webhook
     */
    #[Route('/{slug}/regenerate-token', name: 'regenerate_token', methods: ['POST'])]
    public function regenerateToken(string $slug): JsonResponse
    {
        $project = $this->projectRepository->findOneBy(['slug' => $slug, 'owner' => $this->getUser()]);

        if (!$project) {
            return $this->json(['success' => false, 'error' => 'Projet non trouvé'], 404);
        }

        $oldToken = $project->getWebhookToken();
        $project->regenerateWebhookToken();
        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'message' => 'Token webhook régénéré avec succès',
            'new_token' => $project->getWebhookToken(),
            'old_token' => $oldToken,
            'new_webhook_url' => $project->getWebhookUrl($this->generateBaseUrl())
        ]);
    }

    /**
     * Activer/désactiver un projet
     */
    #[Route('/{slug}/toggle-status', name: 'toggle_status', methods: ['POST'])]
    public function toggleStatus(string $slug): JsonResponse
    {
        $project = $this->projectRepository->findOneBy(['slug' => $slug, 'owner' => $this->getUser()]);

        if (!$project) {
            return $this->json(['error' => 'Projet non trouvé'], 404);
        }

        $project->setIsActive(!$project->isActive());
        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'message' => $project->isActive() ? 'Projet activé' : 'Projet désactivé',
            'is_active' => $project->isActive()
        ]);
    }

    /**
     * Supprimer un projet
     */
    #[Route('/{slug}/delete', name: 'delete', methods: ['POST'])]
    public function delete(string $slug): JsonResponse
    {
        $project = $this->projectRepository->findOneBy(['slug' => $slug, 'owner' => $this->getUser()]);

        if (!$project) {
            return $this->json(['error' => 'Projet non trouvé'], 404);
        }

        // Vérifier s'il y a des erreurs associées
        if ($project->getTotalErrors() > 0) {
            return $this->json([
                'error' => 'Impossible de supprimer un projet qui contient des erreurs. Désactivez-le plutôt.'
            ], 400);
        }

        $projectName = $project->getName();
        $this->entityManager->remove($project);
        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'message' => "Projet '{$projectName}' supprimé avec succès"
        ]);
    }

    /**
     * API: Liste des projets pour autocomplete
     */
    #[Route('/api/search', name: 'api_search', methods: ['GET'])]
    public function apiSearch(Request $request): JsonResponse
    {
        $query = $request->query->get('q', '');

        if (strlen($query) < 2) {
            return $this->json([]);
        }

        $projects = $this->projectRepository->search($query, 10);

        $results = array_map(function (Project $project) {
            return [
                'id' => $project->getId(),
                'name' => $project->getName(),
                'slug' => $project->getSlug(),
                'is_active' => $project->isActive(),
                'total_errors' => $project->getTotalErrors(),
                'last_error_at' => $project->getLastErrorAt()?->format('c')
            ];
        }, $projects);

        return $this->json($results);
    }

    /**
     * API: Statistiques d'un projet
     */
    #[Route('/{slug}/stats', name: 'api_stats', methods: ['GET'])]
    public function apiStats(string $slug): JsonResponse
    {
        $project = $this->projectRepository->findOneBy(['slug' => $slug, 'owner' => $this->getUser()]);

        if (!$project) {
            return $this->json(['error' => 'Projet non trouvé'], 404);
        }

        return $this->json($project->getStatsSummary());
    }

    /**
     * Teste la connectivité du webhook d'un projet
     */
    #[Route('/{slug}/test-webhook', name: 'test_webhook', methods: ['POST'])]
    public function testWebhook(string $slug, Request $request): JsonResponse
    {
        $project = $this->projectRepository->findOneBy(['slug' => $slug, 'owner' => $this->getUser()]);

        if (!$project) {
            return $this->json(['error' => 'Projet non trouvé'], 404);
        }

        try {
            $baseUrl = $request->getSchemeAndHttpHost();
            $pingUrl = $baseUrl . '/webhook/ping/' . $project->getWebhookToken();

            // Test simple de connectivité
            $context = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'timeout' => 5,
                    'ignore_errors' => true
                ]
            ]);

            $response = file_get_contents($pingUrl, false, $context);
            $httpCode = $http_response_header[0] ?? '';

            if (str_contains($httpCode, '200')) {
                return $this->json([
                    'success' => true,
                    'message' => 'Webhook opérationnel',
                    'response' => json_decode($response, true, 512, JSON_THROW_ON_ERROR)
                ]);
            }

            return $this->json([
                'success' => false,
                'message' => 'Webhook non accessible',
                'http_code' => $httpCode
            ], 400);

        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Erreur lors du test du webhook',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    private function exportToCsv(array $projects): StreamedResponse
    {
        $response = new StreamedResponse();
        $response->setCallback(function() use ($projects) {
            $handle = fopen('php://output', 'w+');

            // Ajouter le BOM UTF-8 pour Excel
            fwrite($handle, "\xEF\xBB\xBF");

            // En-têtes CSV avec point-virgule comme séparateur
            fputcsv($handle, [
                'ID',
                'Nom',
                'Slug',
                'Description',
                'Environnement',
                'Statut',
                'Total Erreurs',
                'Total Occurrences',
                'Email Notification',
                'Repository URL',
                'Créé le',
                'Dernière Erreur',
                'Token Webhook'
            ], ';');

            // Données avec point-virgule comme séparateur
            foreach ($projects as $project) {
                fputcsv($handle, [
                    $project->getId(),
                    $project->getName(),
                    $project->getSlug(),
                    $project->getDescription() ?: '',
                    $project->getEnvironment() ?: '',
                    $project->isActive() ? 'Actif' : 'Inactif',
                    $project->getTotalErrors(),
                    $project->getTotalOccurrences(),
                    $project->getNotificationEmail() ?: '',
                    $project->getRepositoryUrl() ?: '',
                    $project->getCreatedAt()->format('Y-m-d H:i:s'),
                    $project->getLastErrorAt()?->format('Y-m-d H:i:s') ?: 'Jamais',
                    $project->getWebhookToken()
                ], ';');
            }

            fclose($handle);
        });

        $filename = 'projets_' . date('Y-m-d_H-i-s') . '.csv';
        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');

        return $response;
    }

    private function exportToJson(array $projects): JsonResponse
    {
        $data = [];

        foreach ($projects as $project) {
            $data[] = [
                'id' => $project->getId(),
                'name' => $project->getName(),
                'slug' => $project->getSlug(),
                'description' => $project->getDescription(),
                'environment' => $project->getEnvironment(),
                'is_active' => $project->isActive(),
                'total_errors' => $project->getTotalErrors(),
                'total_occurrences' => $project->getTotalOccurrences(),
                'notification_email' => $project->getNotificationEmail(),
                'repository_url' => $project->getRepositoryUrl(),
                'created_at' => $project->getCreatedAt()->format('c'),
                'last_error_at' => $project->getLastErrorAt()?->format('c'),
                'webhook_token' => $project->getWebhookToken(),
                'stats' => $project->getStatsSummary()
            ];
        }

        $response = new JsonResponse([
            'exported_at' => date('c'),
            'user_id' => $this->getUser()->getId(),
            'total_projects' => count($projects),
            'projects' => $data
        ]);
        
        $response->setEncodingOptions(JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        $filename = 'projets_' . date('Y-m-d_H-i-s') . '.json';
        $response->headers->set('Content-Type', 'application/json; charset=utf-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');

        return $response;
    }

    private function exportToXlsx(array $projects): Response
    {
        // Pour XLSX, on va créer un CSV avec séparateur tab qui peut être ouvert dans Excel
        $response = new StreamedResponse();
        $response->setCallback(function() use ($projects) {
            $handle = fopen('php://output', 'w+');

            // Ajouter le BOM UTF-8 pour Excel
            fwrite($handle, "\xEF\xBB\xBF");

            // En-têtes avec tabulation comme séparateur
            fputcsv($handle, [
                'ID',
                'Nom',
                'Slug',
                'Description',
                'Environnement',
                'Statut',
                'Total Erreurs',
                'Total Occurrences',
                'Email Notification',
                'Repository URL',
                'Créé le',
                'Dernière Erreur',
                'Token Webhook'
            ], "\t");

            // Données avec tabulation comme séparateur
            foreach ($projects as $project) {
                fputcsv($handle, [
                    $project->getId(),
                    $project->getName(),
                    $project->getSlug(),
                    $project->getDescription() ?: '',
                    $project->getEnvironment() ?: '',
                    $project->isActive() ? 'Actif' : 'Inactif',
                    $project->getTotalErrors(),
                    $project->getTotalOccurrences(),
                    $project->getNotificationEmail() ?: '',
                    $project->getRepositoryUrl() ?: '',
                    $project->getCreatedAt()->format('Y-m-d H:i:s'),
                    $project->getLastErrorAt()?->format('Y-m-d H:i:s') ?: 'Jamais',
                    $project->getWebhookToken()
                ], "\t");
            }

            fclose($handle);
        });

        $filename = 'projets_' . date('Y-m-d_H-i-s') . '.xlsx';
        $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet; charset=utf-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');

        return $response;
    }

    /**
     * Génère l'URL de base pour les instructions
     */
    private function generateBaseUrl(): string
    {
        // Utiliser l'URL actuelle de la requête comme base
        $request = $this->container->get('request_stack')->getCurrentRequest();
        if ($request) {
            return $request->getSchemeAndHttpHost();
        }

        // Fallback si pas de requête disponible
        return 'https://your-error-monitoring.com';
    }

    /**
     * Afficher un projet avec ses instructions d'installation
     */
    #[Route('/details/{slug}', name: 'show')]
    public function show(string $slug, Request $request, DataTableFactory $dataTableFactory): Response
    {
        $project = $this->projectRepository->findOneBy(['slug' => $slug, 'owner' => $this->getUser()]);

        if (!$project) {
            throw $this->createNotFoundException('Projet non trouvé');
        }

        // URL de base pour les instructions
        $baseUrl = $request->getSchemeAndHttpHost();
        $instructions = $project->getInstallationInstructions($baseUrl);

        // Calculer les statistiques réelles pour ce projet
        $filters = ['user' => $this->getUser(), 'project' => $project->getSlug()];
        $stats = [
            'total_errors' => $this->errorGroupRepository->countWithFilters($filters),
            'total_occurrences' => $this->errorOccurrenceRepository->countWithFilters($filters),
            'resolved_errors' => $this->errorGroupRepository->countWithFilters(
                array_merge($filters, ['status' => ErrorGroup::STATUS_RESOLVED])
            ),
            'occurrences_this_week' => $this->errorOccurrenceRepository->countWithFilters(
                array_merge($filters, ['since' => new \DateTime('-7 days')])
            )
        ];

        // Appliquer les filtres de la requête
        $requestFilters = $this->getFiltersFromRequest($request);
        $allFilters = array_merge($filters, $requestFilters);

        // Créer le DataTable pour les erreurs du projet
        $table = $dataTableFactory->createFromType(ProjectErrorGroupDataTableType::class, [
            'user' => $this->getUser(),
            'project' => $project->getSlug(),
            'filters' => $allFilters
        ])->handleRequest($request);

        // Si c'est une requête AJAX DataTable, renvoyer la réponse JSON
        if ($table->isCallback()) {
            return $table->getResponse();
        }

        return $this->render('projects/show.html.twig', [
            'project' => $project,
            'instructions' => $instructions,
            'stats' => $stats,
            'webhook_url' => $project->getWebhookUrl($baseUrl),
            'datatable' => $table,
            'filters' => $this->cleanFiltersForTemplate($allFilters)
        ]);
    }

    /**
     * Extrait les filtres depuis la requête
     */
    private function getFiltersFromRequest(Request $request): array
    {
        $filters = [];

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
     * Actions en lot sur les erreurs d'un projet
     */
    #[Route('/details/{slug}/bulk-errors', name: 'bulk_errors', methods: ['POST'])]
    public function bulkErrorActions(string $slug, Request $request): JsonResponse
    {
        $user = $this->getUser();

        // Vérifier que le projet appartient à l'utilisateur
        $projectEntity = $this->projectRepository->findOneBy(['slug' => $slug, 'owner' => $user]);
        if (!$projectEntity) {
            return $this->json(['success' => false, 'error' => 'Projet non trouvé'], 404);
        }

        $data = json_decode($request->getContent(), true);
        $action = $data['action'] ?? null;
        $errorIds = $data['error_ids'] ?? [];

        if (!$action || empty($errorIds)) {
            return $this->json(['success' => false, 'error' => 'Paramètres manquants'], 400);
        }

        if (!in_array($action, ['resolve', 'ignore', 'reopen'])) {
            return $this->json(['success' => false, 'error' => 'Action non valide'], 400);
        }

        try {
            // Récupérer les erreurs concernées (appartenant au projet et à l'utilisateur)
            $errors = $this->errorGroupRepository->createQueryBuilder('eg')
                ->where('eg.id IN (:ids)')
                ->andWhere('eg.projectEntity = :projectEntity')
                ->setParameter('ids', $errorIds)
                ->setParameter('projectEntity', $projectEntity)
                ->getQuery()
                ->getResult();

            if (empty($errors)) {
                return $this->json(['success' => false, 'error' => 'Aucune erreur trouvée'], 404);
            }

            $count = 0;
            foreach ($errors as $error) {
                // Vérifier que l'erreur appartient bien à un projet de l'utilisateur
                if ($error->getProjectEntity() && $error->getProjectEntity()->getOwner()->getId() !== $user->getId()) {
                    continue;
                }

                switch ($action) {
                    case 'resolve':
                        if ($error->getStatus() !== ErrorGroup::STATUS_RESOLVED) {
                            $error->setStatus(ErrorGroup::STATUS_RESOLVED);
                            $count++;
                        }
                        break;
                    case 'ignore':
                        if ($error->getStatus() !== ErrorGroup::STATUS_IGNORED) {
                            $error->setStatus(ErrorGroup::STATUS_IGNORED);
                            $count++;
                        }
                        break;
                    case 'reopen':
                        if ($error->getStatus() !== ErrorGroup::STATUS_OPEN) {
                            $error->setStatus(ErrorGroup::STATUS_OPEN);
                            $count++;
                        }
                        break;
                }
            }

            $this->entityManager->flush();

            $actionText = match($action) {
                'resolve' => 'résolues',
                'ignore' => 'ignorées',
                'reopen' => 'rouvertes'
            };

            return $this->json([
                'success' => true,
                'message' => sprintf('%d erreur(s) %s avec succès', $count, $actionText)
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => 'Erreur lors de l\'action groupée: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * API: Récupère la liste des utilisateurs assignables pour un projet
     */
    #[Route('/details/{slug}/assignable-users', name: 'assignable_users', methods: ['GET'])]
    public function getAssignableUsers(string $slug): JsonResponse
    {
        $user = $this->getUser();
        $project = $this->projectRepository->findOneBy(['slug' => $slug, 'owner' => $user]);

        if (!$project || !$user) {
            return $this->json(['success' => false, 'error' => 'Projet non trouvé'], 404);
        }

        $assignableUsers = [];

        // Ajouter l'utilisateur principal (propriétaire du projet)
        $assignableUsers[] = [
            'id' => $user->getId(),
            'full_name' => $user->getFullName(),
            'initials' => $user->getInitials(),
            'role' => 'Propriétaire',
            'email' => $user->getEmail()
        ];

        // Ajouter les membres de l'équipe si le projet en a une
        if ($project->getTeam()) {
            $team = $project->getTeam();

            foreach ($team->getMembers() as $member) {
                if ($member->isActive() && $member->getUser()) {
                    $memberUser = $member->getUser();
                    if ($memberUser) {
                        $assignableUsers[] = [
                            'id' => $memberUser->getId(),
                            'full_name' => $memberUser->getFullName(),
                            'initials' => $memberUser->getInitials(),
                            'role' => ucfirst($member->getRole()),
                            'email' => $memberUser->getEmail()
                        ];
                    }
                }
            }
        }

        return $this->json([
            'success' => true,
            'users' => $assignableUsers
        ]);
    }

    /**
     * Assigner une erreur à un utilisateur
     */
    #[Route('/details/{slug}/assign-error', name: 'assign_error', methods: ['POST'])]
    public function assignError(string $slug, Request $request, EmailService $emailService): JsonResponse
    {
        $user = $this->getUser();
        $project = $this->projectRepository->findOneBy(['slug' => $slug, 'owner' => $user]);

        if (!$project) {
            return $this->json(['success' => false, 'error' => 'Projet non trouvé'], 404);
        }

        $data = json_decode($request->getContent(), true);
        $errorId = $data['error_id'] ?? null;
        $assigneeId = $data['user_id'] ?? null;

        if (!$errorId) {
            return $this->json(['success' => false, 'error' => 'ID de l\'erreur manquant'], 400);
        }

        try {
            // Récupérer l'erreur et vérifier qu'elle appartient au projet
            $errorGroup = $this->errorGroupRepository->createQueryBuilder('eg')
                ->where('eg.id = :errorId')
                ->andWhere('eg.projectEntity = :project')
                ->setParameter('errorId', $errorId)
                ->setParameter('project', $project)
                ->getQuery()
                ->getOneOrNullResult();

            if (!$errorGroup) {
                return $this->json(['success' => false, 'error' => 'Erreur non trouvée'], 404);
            }

            $assignee = null;
            $assigneeName = 'Non assigné';

            // Si un utilisateur est spécifié, vérifier qu'il peut être assigné
            if ($assigneeId) {
                // Vérifier que c'est le propriétaire ou un membre de l'équipe
                if ($assigneeId == $user->getId()) {
                    $assignee = $user;
                    $assigneeName = $user->getFullName();
                } elseif ($project->getTeam()) {
                    $team = $project->getTeam();
                    foreach ($team->getMembers() as $member) {
                        if ($member->isActive() && $member->getUser() && $member->getUser()->getId() == $assigneeId) {
                            $assignee = $member->getUser();
                            $assigneeName = $assignee->getFullName();
                            break;
                        }
                    }
                }

                if (!$assignee) {
                    return $this->json(['success' => false, 'error' => 'Utilisateur non autorisé pour ce projet'], 403);
                }
            }

            // Assigner l'erreur
            $errorGroup->setAssignedTo($assignee);
            $this->entityManager->flush();

            // Envoyer une notification email si l'utilisateur a activé les notifications
            if ($assignee) {
                try {
                    $emailService->sendErrorAssignmentNotification($assignee, $errorGroup, $project, $user);
                } catch (\Exception $e) {
                    error_log('Erreur envoi email assignation: ' . $e->getMessage());
                }
            }

            $message = $assignee
                ? sprintf('Erreur assignée à %s avec succès', $assigneeName)
                : 'Assignation retirée avec succès';

            return $this->json([
                'success' => true,
                'message' => $message,
                'assigned_to' => $assignee ? [
                    'id' => $assignee->getId(),
                    'name' => $assignee->getFullName(),
                    'initials' => $assignee->getInitials()
                ] : null
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => 'Erreur lors de l\'assignation: ' . $e->getMessage()
            ], 500);
        }
    }
}
