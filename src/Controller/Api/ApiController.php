<?php

namespace App\Controller\Api;

use App\Entity\Project;
use App\Repository\ErrorGroupRepository;
use App\Repository\ErrorOccurrenceRepository;
use App\Repository\ProjectRepository;
use App\Service\SettingsManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api', name: 'api_')]
class ApiController extends AbstractController
{
    public function __construct(
        private readonly SettingsManager $settingsManager,
        private readonly ProjectRepository $projectRepository,
        private readonly ErrorGroupRepository $errorGroupRepository,
        private readonly ErrorOccurrenceRepository $errorOccurrenceRepository,
        private readonly EntityManagerInterface $em
    ) {}

    #[Route('/docs', name: 'docs', methods: ['GET'])]
    public function documentation(): Response
    {
        $apiEnabled = $this->settingsManager->getSetting('api.api_enabled', true);
        $docsPublic = $this->settingsManager->getSetting('api.api_docs_public', false);

        if (!$apiEnabled) {
            $this->addFlash('error', 'API is disabled');
            return $this->redirectToRoute('home');
        }

        if (!$docsPublic && !$this->isGranted('ROLE_USER')) {
            $this->addFlash('error', 'API documentation is not publicly accessible');
            return $this->redirectToRoute('home');
        }

        return $this->render('api/documentation.html.twig', [
            'api_enabled' => $apiEnabled,
            'docs_public' => $docsPublic,
            'api_key' => $this->settingsManager->getSetting('api.main_api_key'),
            'rate_limit' => $this->settingsManager->getSetting('api.api_rate_limit', 1000)
        ]);
    }

    #[Route('/projects', name: 'projects', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function getProjects(): JsonResponse
    {
        if (!$this->checkApiAccess()) {
            return new JsonResponse(['error' => 'API access denied'], 403);
        }

        $user = $this->getUser();
        $projects = $this->projectRepository->findBy(['user' => $user, 'active' => true]);

        $data = [];
        foreach ($projects as $project) {
            $data[] = [
                'id' => $project->getId(),
                'name' => $project->getName(),
                'environment' => $project->getEnvironment(),
                'webhook_token' => $project->getWebhookToken(),
                'created_at' => $project->getCreatedAt()?->format('c'),
                'error_count' => $this->errorGroupRepository->countByProject($project),
                'last_error' => $this->getLastErrorForProject($project)
            ];
        }

        return new JsonResponse([
            'projects' => $data,
            'total' => count($data)
        ]);
    }

    #[Route('/projects/{id}', name: 'project_detail', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function getProject(int $id): JsonResponse
    {
        if (!$this->checkApiAccess()) {
            return new JsonResponse(['error' => 'API access denied'], 403);
        }

        $project = $this->projectRepository->find($id);

        if (!$project || $project->getUser() !== $this->getUser()) {
            return new JsonResponse(['error' => 'Project not found'], 404);
        }

        $errorGroups = $this->errorGroupRepository->findBy(['project' => $project], ['lastOccurredAt' => 'DESC'], 10);

        $errors = [];
        foreach ($errorGroups as $errorGroup) {
            $errors[] = [
                'id' => $errorGroup->getId(),
                'message' => $errorGroup->getMessage(),
                'type' => $errorGroup->getType(),
                'status' => $errorGroup->getStatus(),
                'count' => $errorGroup->getCount(),
                'first_occurred' => $errorGroup->getFirstOccurredAt()->format('c'),
                'last_occurred' => $errorGroup->getLastOccurredAt()->format('c')
            ];
        }

        return new JsonResponse([
            'project' => [
                'id' => $project->getId(),
                'name' => $project->getName(),
                'environment' => $project->getEnvironment(),
                'webhook_token' => $project->getWebhookToken(),
                'created_at' => $project->getCreatedAt()?->format('c'),
                'error_count' => $this->errorGroupRepository->countByProject($project),
                'recent_errors' => $errors
            ]
        ]);
    }

    #[Route('/errors', name: 'errors', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function getErrors(Request $request): JsonResponse
    {
        if (!$this->checkApiAccess()) {
            return new JsonResponse(['error' => 'API access denied'], 403);
        }

        $user = $this->getUser();
        $projectId = $request->query->get('project_id');
        $status = $request->query->get('status');
        $limit = min((int)$request->query->get('limit', 50), 100);
        $offset = (int)$request->query->get('offset', 0);

        $criteria = ['user' => $user];
        if ($projectId) {
            $project = $this->projectRepository->find($projectId);
            if (!$project || $project->getUser() !== $user) {
                return new JsonResponse(['error' => 'Project not found'], 404);
            }
            $criteria['project'] = $project;
        }
        if ($status) {
            $criteria['status'] = $status;
        }

        $errorGroups = $this->errorGroupRepository->findBy(
            $criteria,
            ['lastOccurredAt' => 'DESC'],
            $limit,
            $offset
        );

        $data = [];
        foreach ($errorGroups as $errorGroup) {
            $data[] = [
                'id' => $errorGroup->getId(),
                'message' => $errorGroup->getMessage(),
                'type' => $errorGroup->getType(),
                'status' => $errorGroup->getStatus(),
                'count' => $errorGroup->getCount(),
                'project_id' => $errorGroup->getProjectEntity()?->getId(),
                'project_name' => $errorGroup->getProjectEntity()?->getName(),
                'first_occurred' => $errorGroup->getFirstOccurredAt()->format('c'),
                'last_occurred' => $errorGroup->getLastOccurredAt()->format('c')
            ];
        }

        return new JsonResponse([
            'errors' => $data,
            'total' => count($data),
            'limit' => $limit,
            'offset' => $offset
        ]);
    }

    #[Route('/errors/{id}', name: 'error_detail', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function getError(int $id): JsonResponse
    {
        if (!$this->checkApiAccess()) {
            return new JsonResponse(['error' => 'API access denied'], 403);
        }

        $errorGroup = $this->errorGroupRepository->find($id);

        if (!$errorGroup || $errorGroup->getProjectEntity()?->getOwner() !== $this->getUser()) {
            return new JsonResponse(['error' => 'Error not found'], 404);
        }

        $occurrences = $this->errorOccurrenceRepository->findBy(
            ['errorGroup' => $errorGroup],
            ['createdAt' => 'DESC'],
            5
        );

        $occurrenceData = [];
        foreach ($occurrences as $occurrence) {
            $occurrenceData[] = [
                'id' => $occurrence->getId(),
                'created_at' => $occurrence->getCreatedAt()?->format('c'),
                'stack_trace' => $occurrence->getStackTrace(),
                'context' => $occurrence->getContext(),
                'request_data' => $occurrence->getRequestData(),
                'server_data' => $occurrence->getServerData()
            ];
        }

        return new JsonResponse([
            'error' => [
                'id' => $errorGroup->getId(),
                'message' => $errorGroup->getMessage(),
                'type' => $errorGroup->getType(),
                'status' => $errorGroup->getStatus(),
                'count' => $errorGroup->getCount(),
                'fingerprint' => $errorGroup->getFingerprint(),
                'file' => $errorGroup->getFile(),
                'line' => $errorGroup->getLine(),
                'project_id' => $errorGroup->getProjectEntity()?->getId(),
                'project_name' => $errorGroup->getProjectEntity()?->getName(),
                'first_occurred' => $errorGroup->getFirstOccurredAt()->format('c'),
                'last_occurred' => $errorGroup->getLastOccurredAt()->format('c'),
                'recent_occurrences' => $occurrenceData
            ]
        ]);
    }

    #[Route('/errors/{id}/resolve', name: 'error_resolve', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function resolveError(int $id): JsonResponse
    {
        if (!$this->checkApiAccess()) {
            return new JsonResponse(['error' => 'API access denied'], 403);
        }

        $errorGroup = $this->errorGroupRepository->find($id);

        if (!$errorGroup || $errorGroup->getProjectEntity()?->getOwner() !== $this->getUser()) {
            return new JsonResponse(['error' => 'Error not found'], 404);
        }

        $errorGroup->setStatus('resolved');
        $this->em->flush();

        return new JsonResponse([
            'message' => 'Error resolved successfully',
            'error_id' => $id,
            'new_status' => 'resolved'
        ]);
    }

    #[Route('/stats', name: 'stats', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function getStats(Request $request): JsonResponse
    {
        if (!$this->checkApiAccess()) {
            return new JsonResponse(['error' => 'API access denied'], 403);
        }

        $user = $this->getUser();
        $projectId = $request->query->get('project_id');
        $days = min((int)$request->query->get('days', 7), 30);

        $projects = $projectId
            ? [$this->projectRepository->find($projectId)]
            : $this->projectRepository->findBy(['user' => $user, 'active' => true]);

        $projects = array_filter($projects, fn($p) => $p && $p->getUser() === $user);

        $totalErrors = 0;
        $resolvedErrors = 0;
        $newErrors = 0;

        foreach ($projects as $project) {
            $totalErrors += $this->errorGroupRepository->countByProject($project);
            $resolvedErrors += $this->errorGroupRepository->countByProjectAndStatus($project, 'resolved');
            $newErrors += $this->errorGroupRepository->countRecentByProject($project, $days);
        }

        return new JsonResponse([
            'stats' => [
                'total_projects' => count($projects),
                'total_errors' => $totalErrors,
                'resolved_errors' => $resolvedErrors,
                'new_errors_last_' . $days . '_days' => $newErrors,
                'resolution_rate' => $totalErrors > 0 ? round(($resolvedErrors / $totalErrors) * 100, 2) : 0
            ],
            'period_days' => $days
        ]);
    }

    private function checkApiAccess(): bool
    {
        return $this->settingsManager->getSetting('api.api_enabled', true);
    }

    private function getLastErrorForProject(Project $project): ?array
    {
        $lastError = $this->errorGroupRepository->findOneBy(
            ['project' => $project],
            ['lastOccurredAt' => 'DESC']
        );

        if (!$lastError) {
            return null;
        }

        return [
            'id' => $lastError->getId(),
            'message' => $lastError->getMessage(),
            'occurred_at' => $lastError->getLastOccurredAt()->format('c')
        ];
    }
}
