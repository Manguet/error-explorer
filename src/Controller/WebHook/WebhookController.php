<?php

namespace App\Controller\WebHook;

use App\Entity\Project;
use App\Repository\ProjectRepository;
use App\Service\ErrorLimitService;
use App\Service\ErrorProcessor;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/webhook', name: 'webhook_')]
class WebhookController extends AbstractController
{
    public function __construct(
        private ErrorProcessor $errorProcessor,
        private ErrorLimitService $errorLimitService,
        private ProjectRepository $projectRepository,
        private LoggerInterface $logger
    ) {}

    /**
     * Endpoint principal pour recevoir les erreurs des applications clientes
     */
    #[Route('/error/{token}', name: 'receive_error', methods: ['POST'])]
    public function receiveError(string $token, Request $request): JsonResponse
    {
        // Démarrer le chrono pour mesurer les performances
        $startTime = microtime(true);

        try {
            // Validation du token et récupération du projet
            $project = $this->getProjectByToken($token);
            if (!$project) {
                $this->logger->warning('Webhook: Token invalide', [
                    'token' => $token,
                    'ip' => $request->getClientIp()
                ]);

                return $this->json([
                    'success' => false,
                    'error' => 'Token invalide'
                ], 401);
            }

            // Vérification des limites de plan
            if (!$this->errorLimitService->canReceiveError($project)) {
                $owner = $project->getOwner();
                $plan = $owner?->getPlan();

                if ($owner && $owner->isPlanExpired()) {
                    return $this->json([
                        'success' => false,
                        'error' => 'Plan expiré',
                        'error_code' => 'PLAN_EXPIRED',
                        'message' => 'Votre plan a expiré. Veuillez renouveler votre abonnement pour continuer à recevoir des erreurs.',
                        'plan_expires_at' => $owner->getPlanExpiresAt()?->format('c'),
                        'upgrade_url' => '/pricing'
                    ], 402);
                }

                if (!$plan || !$plan->isActive()) {
                    return $this->json([
                        'success' => false,
                        'error' => 'Aucun plan actif',
                        'error_code' => 'NO_ACTIVE_PLAN',
                        'message' => 'Aucun plan actif trouvé. Veuillez souscrire à un plan pour utiliser ce service.',
                        'upgrade_url' => '/pricing'
                    ], 402);
                }

                $stats = $this->errorLimitService->getUsageStats($owner);
                if ($stats['monthly_errors']['percentage'] >= 100) {
                    return $this->json([
                        'success' => false,
                        'error' => 'Limite mensuelle atteinte',
                        'error_code' => 'MONTHLY_LIMIT_REACHED',
                        'message' => sprintf(
                            'Vous avez atteint votre limite mensuelle de %s erreurs. Veuillez upgrader votre plan pour continuer.',
                            $stats['monthly_errors']['max_label']
                        ),
                        'current_usage' => $stats['monthly_errors']['current'],
                        'limit' => $stats['monthly_errors']['max'],
                        'percentage' => $stats['monthly_errors']['percentage'],
                        'upgrade_url' => '/pricing'
                    ], 429);
                }

                return $this->json([
                    'success' => false,
                    'error' => 'Limite de plan atteinte',
                    'error_code' => 'PLAN_LIMIT_REACHED',
                    'message' => 'Vous avez atteint les limites de votre plan actuel.',
                    'upgrade_url' => '/pricing'
                ], 429);
            }

            // Validation du Content-Type
            if (!$request->headers->contains('Content-Type', 'application/json')) {
                return $this->json([
                    'success' => false,
                    'error' => 'Content-Type doit être application/json'
                ], 400);
            }

            // Récupération et validation du payload
            $payload = json_decode($request->getContent(), true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return $this->json([
                    'success' => false,
                    'error' => 'JSON invalide: ' . json_last_error_msg()
                ], 400);
            }

            // Validation des champs requis
            $validationError = $this->validatePayload($payload);
            if ($validationError) {
                return $this->json([
                    'success' => false,
                    'error' => $validationError
                ], 400);
            }

            // Traitement de l'erreur avec le projet
            $result = $this->errorProcessor->processError($payload, $token, $project);

            // Log du succès
            $processingTime = round((microtime(true) - $startTime) * 1000, 2);
            $this->logger->info('Webhook: Erreur traitée avec succès', [
                'project' => $payload['project'] ?? 'unknown',
                'error_group_id' => $result['error_group_id'] ?? null,
                'processing_time_ms' => $processingTime,
                'ip' => $request->getClientIp()
            ]);

            return $this->json([
                'success' => true,
                'message' => 'Erreur enregistrée',
                'error_group_id' => $result['error_group_id'] ?? null,
                'fingerprint' => $result['fingerprint'] ?? null,
                'processing_time_ms' => $processingTime
            ]);

        } catch (\Exception $e) {
            // Log de l'erreur mais ne pas exposer les détails
            $this->logger->error('Webhook: Erreur lors du traitement', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'payload' => $payload ?? null,
                'ip' => $request->getClientIp()
            ]);

            return $this->json([
                'success' => false,
                'error' => 'Erreur interne du serveur'
            ], 500);
        }
    }

    /**
     * Endpoint de test pour vérifier la connectivité
     */
    #[Route('/ping/{token}', name: 'ping', methods: ['GET', 'POST'])]
    public function ping(string $token): JsonResponse
    {
        $project = $this->getProjectByToken($token);
        if (!$project) {
            return $this->json([
                'success' => false,
                'error' => 'Token invalide'
            ], 401);
        }

        // Vérification des limites de plan pour le ping aussi
        if (!$this->errorLimitService->canReceiveError($project)) {
            return $this->json([
                'success' => false,
                'error' => 'Plan non valide ou limites atteintes',
                'warning' => 'Le projet ne peut pas recevoir de nouvelles erreurs'
            ], 402);
        }

        return $this->json([
            'success' => true,
            'message' => 'Webhook opérationnel',
            'timestamp' => date('c'),
            'version' => '1.0.0',
            'project' => $project->getName(),
            'plan_status' => 'active'
        ]);
    }

    /**
     * Endpoint pour tester la réception d'erreur (dev uniquement)
     */
    #[Route('/test-error', name: 'test_error', methods: ['POST'])]
    public function testError(Request $request): JsonResponse
    {
        // Uniquement en mode dev
        if ($this->getParameter('kernel.environment') !== 'dev') {
            throw $this->createNotFoundException();
        }

        $payload = [
            'message' => 'Test error from webhook endpoint',
            'exception_class' => 'RuntimeException',
            'stack_trace' => $this->generateTestStackTrace(),
            'file' => __FILE__,
            'line' => __LINE__,
            'project' => 'webhook-test',
            'environment' => 'dev',
            'http_status' => 500,
            'timestamp' => date('c'),
            'request' => [
                'url' => $request->getUri(),
                'method' => $request->getMethod(),
                'ip' => $request->getClientIp(),
                'user_agent' => $request->headers->get('User-Agent')
            ],
            'server' => [
                'php_version' => PHP_VERSION,
                'memory_usage' => memory_get_usage(),
                'execution_time' => 0.150
            ]
        ];

        $result = $this->errorProcessor->processError($payload, 'test-token');

        return $this->json([
            'success' => true,
            'message' => 'Erreur de test créée',
            'result' => $result
        ]);
    }

    /**
     * Récupère le projet par son token d'authentification
     */
    private function getProjectByToken(string $token): ?Project
    {
        try {
            // Chercher le projet par son webhookToken dans la base de données
            return $this->projectRepository->findOneBy([
                'webhookToken' => $token,
                'isActive' => true
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Erreur lors de la validation du token', [
                'token' => $token,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Validation du payload reçu
     */
    private function validatePayload(?array $payload): ?string
    {
        if (!$payload) {
            return 'Payload vide';
        }

        // Champs obligatoires
        $required = ['message', 'exception_class', 'file', 'line', 'project'];

        foreach ($required as $field) {
            if (!isset($payload[$field]) || $payload[$field] === '') {
                return "Champ requis manquant: {$field}";
            }
        }

        // Validation des types
        if (!is_string($payload['message']) || strlen($payload['message']) > 2000) {
            return 'Le champ "message" doit être une chaîne de moins de 2000 caractères';
        }

        if (!is_string($payload['project']) || strlen($payload['project']) > 100) {
            return 'Le champ "project" doit être une chaîne de moins de 100 caractères';
        }

        if (!is_int($payload['line']) || $payload['line'] < 0) {
            return 'Le champ "line" doit être un entier positif';
        }

        // Validation optionnelle du code HTTP
        if (isset($payload['http_status']) && (!is_int($payload['http_status']) || $payload['http_status'] < 100 || $payload['http_status'] > 599)) {
            return 'Le champ "http_status" doit être un code HTTP valide (100-599)';
        }

        return null;
    }

    /**
     * Génère une stack trace de test
     */
    private function generateTestStackTrace(): string
    {
        return "#0 " . __FILE__ . "(" . __LINE__ . "): " . __CLASS__ . "->testError()\n" .
            "#1 /var/www/vendor/symfony/http-kernel/HttpKernel.php(163): Symfony\\Component\\HttpKernel\\HttpKernel->handleRaw()\n" .
            "#2 /var/www/vendor/symfony/http-kernel/HttpKernel.php(74): Symfony\\Component\\HttpKernel\\HttpKernel->handle()\n" .
            "#3 /var/www/vendor/symfony/http-kernel/Kernel.php(202): Symfony\\Component\\HttpKernel\\Kernel->handle()\n" .
            "#4 /var/www/public/index.php(25): App\\Kernel->handle()\n" .
            "#5 {main}";
    }
}
