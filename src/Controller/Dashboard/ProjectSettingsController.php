<?php

namespace App\Controller\Dashboard;

use App\Entity\ErrorGroup;
use App\Entity\Project;
use App\Form\ProjectAlertsType;
use App\Service\AlertService;
use App\Service\ExternalWebhookService;
use App\Service\SettingsManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/projects/{id}/settings')]
#[IsGranted('ROLE_USER')]
class ProjectSettingsController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly SettingsManager $settingsManager
    ) {}

    #[Route('/alerts', name: 'project_alerts_settings')]
    public function alertsSettings(
        Project $project,
        Request $request
    ): Response {
        // Vérifier que l'utilisateur possède ce projet
        if ($project->getOwner() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        $form = $this->createForm(ProjectAlertsType::class, $project);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();

            $this->addFlash('success', 'Configuration des alertes mise à jour avec succès !');

            return $this->redirectToRoute('project_alerts_settings', [
                'id' => $project->getId()
            ]);
        }

        return $this->render('projects/settings/alerts.html.twig', [
            'project' => $project,
            'form' => $form->createView(),
            'global_alerts_enabled' => $this->settingsManager->getSetting('email.error_alerts', false),
        ]);
    }

    #[Route('/test-alert', name: 'project_test_alert', methods: ['POST'])]
    public function testAlert(
        Project $project,
        Request $request,
        AlertService $alertService
    ): Response {
        // Vérifier que l'utilisateur possède ce projet
        if ($project->getOwner() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        // Vérifier le token CSRF
        if (!$this->isCsrfTokenValid('test_alert_' . $project->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF invalide');
        }

        try {
            // Créer une erreur de test factice
            $testErrorGroup = new ErrorGroup();
            $testErrorGroup->setFingerprint('test-alert-' . time())
                ->setMessage('Ceci est une alerte de test depuis Error Explorer')
                ->setExceptionClass('TestException')
                ->setFile('/app/src/Controller/ProjectSettingsController.php')
                ->setLine(100)
                ->setProject($project->getSlug())
                ->setProjectEntity($project)
                ->setHttpStatusCode(500)
                ->setErrorType('exception')
                ->setEnvironment('test')
                ->setStackTracePreview("TestException: Test alert\n  at ProjectSettingsController::testAlert (line 100)\n  at Dashboard (generated test)")
                ->setStatus(ErrorGroup::STATUS_OPEN)
                ->setOccurrenceCount(1);

            // Envoyer l'alerte de test
            $success = $alertService->sendErrorAlert($testErrorGroup, $project, $this->getUser());

            if ($success) {
                $this->addFlash('success', '🎉 Alerte de test envoyée avec succès ! Vérifiez votre boîte email.');
            } else {
                $this->addFlash('warning', 'L\'alerte de test n\'a pas pu être envoyée. Vérifiez votre configuration.');
            }

        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de l\'envoi de l\'alerte de test : ' . $e->getMessage());
        }

        return $this->redirectToRoute('project_alerts_settings', [
            'id' => $project->getId()
        ]);
    }

    #[Route('/webhook/save', name: 'project_webhook_save', methods: ['POST'])]
    public function saveWebhook(
        Project $project,
        Request $request
    ): JsonResponse {
        // Vérifier que l'utilisateur possède ce projet
        if ($project->getOwner() !== $this->getUser()) {
            return new JsonResponse(['success' => false, 'message' => 'Accès refusé']);
        }

        try {
            $data = json_decode($request->getContent(), true);

            if (!$data || !isset($data['name'], $data['url'], $data['events'])) {
                return new JsonResponse(['success' => false, 'message' => 'Données manquantes']);
            }

            $name = trim($data['name']);
            $url = trim($data['url']);
            $events = $data['events'];
            $headers = $data['headers'] ?? [];

            if (empty($name) || empty($url) || empty($events)) {
                return new JsonResponse(['success' => false, 'message' => 'Nom, URL et événements sont obligatoires']);
            }

            if (!filter_var($url, FILTER_VALIDATE_URL)) {
                return new JsonResponse(['success' => false, 'message' => 'URL invalide']);
            }

            // Modifier un webhook existant ou en créer un nouveau
            if (!empty($data['id'])) {
                // Modification
                $webhooks = $project->getExternalWebhooks();
                $found = false;

                foreach ($webhooks as &$webhook) {
                    if ($webhook['id'] === $data['id']) {
                        $webhook['name'] = $name;
                        $webhook['url'] = $url;
                        $webhook['events'] = $events;
                        $webhook['headers'] = $headers;
                        $found = true;
                        break;
                    }
                }

                if (!$found) {
                    return new JsonResponse(['success' => false, 'message' => 'Webhook non trouvé']);
                }

                $project->setExternalWebhooks($webhooks);
            } else {
                // Création
                $project->addExternalWebhook($name, $url, $events, $headers);
            }

            $this->entityManager->flush();

            return new JsonResponse(['success' => true]);

        } catch (\Exception $e) {
            return new JsonResponse(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
        }
    }

    #[Route('/webhook/remove', name: 'project_webhook_remove', methods: ['POST'])]
    public function removeWebhook(
        Project $project,
        Request $request
    ): JsonResponse {
        // Vérifier que l'utilisateur possède ce projet
        if ($project->getOwner() !== $this->getUser()) {
            return new JsonResponse(['success' => false, 'message' => 'Accès refusé']);
        }

        try {
            $data = json_decode($request->getContent(), true);

            if (!$data || !isset($data['id'])) {
                return new JsonResponse(['success' => false, 'message' => 'ID du webhook manquant']);
            }

            $project->removeExternalWebhook($data['id']);
            $this->entityManager->flush();

            return new JsonResponse(['success' => true]);

        } catch (\Exception $e) {
            return new JsonResponse(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
        }
    }

    #[Route('/webhook/test', name: 'project_webhook_test', methods: ['POST'])]
    public function testWebhook(
        Project $project,
        Request $request,
        ExternalWebhookService $webhookService
    ): JsonResponse {
        // Vérifier que l'utilisateur possède ce projet
        if ($project->getOwner() !== $this->getUser()) {
            return new JsonResponse(['success' => false, 'message' => 'Accès refusé']);
        }

        try {
            $data = json_decode($request->getContent(), true);

            if (!$data || !isset($data['id'])) {
                return new JsonResponse(['success' => false, 'message' => 'ID du webhook manquant']);
            }

            $webhooks = $project->getExternalWebhooks();
            $targetWebhook = null;

            foreach ($webhooks as $webhook) {
                if ($webhook['id'] === $data['id']) {
                    $targetWebhook = $webhook;
                    break;
                }
            }

            if (!$targetWebhook) {
                return new JsonResponse(['success' => false, 'message' => 'Webhook non trouvé']);
            }

            $result = $webhookService->testWebhook($targetWebhook, $project);

            return new JsonResponse($result);

        } catch (\Exception $e) {
            return new JsonResponse(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
        }
    }
}
