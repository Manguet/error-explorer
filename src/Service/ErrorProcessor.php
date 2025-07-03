<?php

namespace App\Service;

use App\Entity\ErrorGroup;
use App\Entity\ErrorOccurrence;
use App\Repository\ErrorGroupRepository;
use App\Repository\ErrorOccurrenceRepository;
use App\Service\Error\WebhookDataExtractor;
use App\Service\Error\ErrorFingerprintService;
use App\ValueObject\Error\WebhookData;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class ErrorProcessor
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ErrorGroupRepository $errorGroupRepository,
        private ErrorOccurrenceRepository $errorOccurrenceRepository,
        private LoggerInterface $logger,
        private AlertService $alertService,
        private ExternalWebhookService $externalWebhookService,
        private WebhookDataExtractor $webhookDataExtractor,
        private ErrorFingerprintService $fingerprintService
    ) {}

    /**
     * Traite une erreur reçue via webhook
     */
    public function processError(array $payload, string $token, ?\App\Entity\Project $project = null): array
    {
        try {
            // Récupérer le repository du projet
            $projectRepository = $this->entityManager->getRepository(\App\Entity\Project::class);
            
            // Récupérer le projet depuis le token si pas fourni
            if (!$project) {
                $project = $projectRepository->findByWebhookToken($token);

                if (!$project) {
                    throw new \InvalidArgumentException('Projet non trouvé pour ce token');
                }
            }

            // Extraire et transformer les données avec Value Objects
            $webhookData = $this->webhookDataExtractor->extractWebhookData($payload);

            // Valider les données extraites
            $validationErrors = $this->webhookDataExtractor->validateWebhookData($webhookData);
            if (!empty($validationErrors)) {
                throw new \InvalidArgumentException('Données webhook invalides: ' . implode(', ', $validationErrors));
            }

            // Utiliser le nom du projet depuis la BDD (priorité sur le payload)
            $coreData = $webhookData->coreData;
            $coreDataWithProject = new \App\ValueObject\Error\CoreErrorData(
                message: $coreData->message,
                exceptionClass: $coreData->exceptionClass,
                file: $coreData->file,
                line: $coreData->line,
                project: $project->getSlug(), // Override avec le slug du projet
                environment: $coreData->environment,
                httpStatus: $coreData->httpStatus,
                stackTrace: $coreData->stackTrace,
                timestamp: $coreData->timestamp,
                errorType: $coreData->errorType,
                fingerprint: $coreData->fingerprint
            );

            $webhookDataWithProject = new WebhookData(
                requestContext: $webhookData->requestContext,
                serverContext: $webhookData->serverContext,
                errorContext: $webhookData->errorContext,
                coreData: $coreDataWithProject
            );

            // Générer le fingerprint pour grouper les erreurs similaires
            $fingerprint = $this->fingerprintService->generateFingerprint($webhookDataWithProject);

            // Trouver ou créer le groupe d'erreur
            $errorGroup = $this->findOrCreateErrorGroup($fingerprint, $webhookDataWithProject, $project);

            // Créer l'occurrence
            $occurrence = $this->createOccurrence($errorGroup, $webhookDataWithProject);

            // Mettre à jour les statistiques du projet
            $isNewGroup = $errorGroup->getOccurrenceCount() === 1;
            $projectRepository->updateProjectStats($project, $isNewGroup);

            // Sauvegarder en base
            $this->entityManager->flush();

            // Traiter les alertes après la sauvegarde
            $this->handleAlerts($errorGroup, $project, $isNewGroup);

            // Envoyer aux webhooks externes
            $this->handleExternalWebhooks($errorGroup, $project, $isNewGroup);

            // Log pour debug
            $this->logger->info('ErrorProcessor: Erreur traitée', [
                'error_group_id' => $errorGroup->getId(),
                'fingerprint' => $fingerprint,
                'project' => $project->getSlug(),
                'project_id' => $project->getId(),
                'new_group' => $isNewGroup,
                'webhook_summary' => $webhookDataWithProject->getSummary(),
                'has_user_context' => $webhookDataWithProject->hasUserContext(),
                'has_performance_metrics' => $webhookDataWithProject->hasPerformanceMetrics()
            ]);

            return [
                'error_group_id' => $errorGroup->getId(),
                'fingerprint' => $fingerprint,
                'is_new_group' => $isNewGroup,
                'occurrence_id' => $occurrence->getId(),
                'project_id' => $project->getId()
            ];

        } catch (\Exception $e) {
            $this->logger->error('ErrorProcessor: Erreur lors du traitement', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'payload' => $payload,
                'token' => $token
            ]);

            throw $e;
        }
    }



    /**
     * Trouve un groupe d'erreur existant ou en crée un nouveau
     */
    private function findOrCreateErrorGroup(string $fingerprint, WebhookData $webhookData, \App\Entity\Project $project): ErrorGroup
    {
        $errorGroup = $this->errorGroupRepository->findOneBy(['fingerprint' => $fingerprint]);
        $core = $webhookData->coreData;

        if (!$errorGroup) {
            // Créer un nouveau groupe
            $errorGroup = new ErrorGroup();
            $errorGroup->setFingerprint($fingerprint)
                ->setMessage($core->message)
                ->setExceptionClass($core->exceptionClass)
                ->setFile($core->file)
                ->setLine($core->line)
                ->setProject($core->project)
                ->setProjectEntity($project)
                ->setHttpStatusCode($core->httpStatus)
                ->setErrorType($core->errorType)
                ->setEnvironment($core->environment)
                ->setStackTracePreview($core->getStackTracePreview());

            $this->entityManager->persist($errorGroup);

            $this->logger->info('ErrorProcessor: Nouveau groupe d\'erreur créé', [
                'fingerprint' => $fingerprint,
                'project' => $core->project,
                'project_id' => $project->getId(),
                'exception_class' => $core->exceptionClass,
                'is_critical' => $core->isCritical(),
                'tags' => $core->getTags()
            ]);
        } else {
            // Mettre à jour les informations du groupe existant
            $this->updateExistingGroup($errorGroup, $webhookData);
        }

        // Incrémenter le compteur d'occurrences
        $errorGroup->incrementOccurrenceCount();

        return $errorGroup;
    }

    /**
     * Met à jour un groupe d'erreur existant si nécessaire
     */
    private function updateExistingGroup(ErrorGroup $errorGroup, WebhookData $webhookData): void
    {
        $updated = false;
        $core = $webhookData->coreData;

        // Mettre à jour l'environnement si pas défini ou différent
        if (!$errorGroup->getEnvironment() || $errorGroup->getEnvironment() === 'unknown') {
            if ($core->environment && $core->environment !== 'unknown') {
                $errorGroup->setEnvironment($core->environment);
                $updated = true;
            }
        }

        // Mettre à jour le code HTTP si pas défini
        if (!$errorGroup->getHttpStatusCode() && $core->httpStatus) {
            $errorGroup->setHttpStatusCode($core->httpStatus);
            $updated = true;
        }

        // Rouvrir le groupe s'il était résolu ou ignoré
        if ($errorGroup->getStatus() !== ErrorGroup::STATUS_OPEN) {
            $errorGroup->reopen();
            $updated = true;

            $this->logger->info('ErrorProcessor: Groupe d\'erreur rouvert', [
                'error_group_id' => $errorGroup->getId(),
                'previous_status' => $errorGroup->getStatus(),
                'is_critical' => $core->isCritical()
            ]);
        }

        if ($updated) {
            $this->logger->debug('ErrorProcessor: Groupe d\'erreur mis à jour', [
                'error_group_id' => $errorGroup->getId(),
                'new_environment' => $core->environment,
                'new_http_status' => $core->httpStatus
            ]);
        }
    }

    /**
     * Crée une nouvelle occurrence d'erreur
     */
    private function createOccurrence(ErrorGroup $errorGroup, WebhookData $webhookData): ErrorOccurrence
    {
        $core = $webhookData->coreData;
        
        $occurrence = new ErrorOccurrence();
        $occurrence->setErrorGroup($errorGroup)
            ->setStackTrace($core->stackTrace)
            ->setEnvironment($core->environment)
            ->setRequest($webhookData->requestContext?->toArray() ?? [])
            ->setServer($webhookData->serverContext?->toArray() ?? [])
            ->setContext($webhookData->errorContext?->toArray() ?? [])
            ->setCreatedAt($core->timestamp);

        // Définir les champs extraits pour les index (depuis Value Objects)
        if ($webhookData->requestContext) {
            $request = $webhookData->requestContext;
            $occurrence->setUrl($request->url)
                      ->setHttpMethod($request->method)
                      ->setIpAddress($request->ip)
                      ->setUserAgent($request->userAgent);
        }

        if ($webhookData->serverContext) {
            $server = $webhookData->serverContext;
            $occurrence->setMemoryUsage($server->memoryUsage)
                      ->setExecutionTime($server->executionTime);
        }

        if ($webhookData->errorContext && $webhookData->errorContext->hasUserContext()) {
            $user = $webhookData->errorContext->userContext;
            $occurrence->setUserId($user->id);
        }

        $this->entityManager->persist($occurrence);

        // Log détaillé pour debug
        $this->logger->debug('ErrorProcessor: Occurrence créée', [
            'error_group_id' => $errorGroup->getId(),
            'has_request_context' => $webhookData->requestContext !== null,
            'has_server_context' => $webhookData->serverContext !== null,
            'has_error_context' => $webhookData->errorContext !== null,
            'has_user_context' => $webhookData->hasUserContext(),
            'has_breadcrumbs' => $webhookData->hasBreadcrumbs(),
            'performance_metrics' => $webhookData->hasPerformanceMetrics()
        ]);

        return $occurrence;
    }

    /**
     * Normalise le chemin de fichier pour cohérence
     */
    private function normalizePath(string $path): string
    {
        // Remplacer les backslashes par des slashes
        $path = str_replace('\\', '/', $path);

        // Normaliser les chemins relatifs courants
        $path = preg_replace('/^.*\/(src|app|vendor|public)\//', '$1/', $path);

        return $path;
    }

    /**
     * Normalise le nom du projet
     */
    private function normalizeProjectName(string $project): string
    {
        // Nettoyer et limiter la longueur
        $project = trim($project);
        $project = preg_replace('/[^a-zA-Z0-9\-_.]/', '-', $project);

        return substr($project, 0, 100);
    }


    /**
     * Nettoie les anciennes occurrences (à appeler via une commande cron)
     */
    public function cleanupOldOccurrences(int $daysToKeep = 90): int
    {
        $deletedOccurrences = $this->errorOccurrenceRepository->cleanupOldOccurrences($daysToKeep);
        $deletedGroups = $this->errorGroupRepository->cleanupOldResolvedErrors($daysToKeep);

        $this->logger->info('ErrorProcessor: Nettoyage effectué', [
            'deleted_occurrences' => $deletedOccurrences,
            'deleted_groups' => $deletedGroups,
            'days_to_keep' => $daysToKeep
        ]);

        return $deletedOccurrences + $deletedGroups;
    }

    /**
     * Statistiques de traitement
     */
    public function getProcessingStats(): array
    {
        return [
            'total_groups' => $this->errorGroupRepository->count([]),
            'total_occurrences' => $this->errorOccurrenceRepository->count([]),
            'open_groups' => $this->errorGroupRepository->count(['status' => ErrorGroup::STATUS_OPEN]),
            'groups_today' => $this->errorGroupRepository->countWithFilters([
                'since' => new \DateTime('today')
            ]),
            'occurrences_today' => $this->errorOccurrenceRepository->countWithFilters([
                'since' => new \DateTime('today')
            ])
        ];
    }

    /**
     * Gère l'envoi d'alertes pour une erreur
     */
    private function handleAlerts(ErrorGroup $errorGroup, \App\Entity\Project $project, bool $isNewGroup): void
    {
        try {
            $user = $project->getOwner();
            if (!$user) {
                return;
            }

            // Vérifier si c'est une erreur critique qui nécessite une alerte immédiate
            if ($this->alertService->isCriticalError($errorGroup)) {
                $alertSent = $this->alertService->sendCriticalAlert($errorGroup, $project, $user);
                if ($alertSent) {
                    // Sauvegarder le timestamp de dernière alerte mis à jour
                    $this->entityManager->flush();
                    $this->logger->info('ErrorProcessor: Alerte critique envoyée', [
                        'error_group_id' => $errorGroup->getId(),
                        'project_id' => $project->getId(),
                        'user_id' => $user->getId()
                    ]);
                }
                return;
            }

            // Sinon, envoyer une alerte normale si les conditions sont remplies
            $alertSent = $this->alertService->sendErrorAlert($errorGroup, $project, $user);
            
            if ($alertSent) {
                // Sauvegarder le timestamp de dernière alerte mis à jour
                $this->entityManager->flush();
                $this->logger->info('ErrorProcessor: Alerte normale envoyée', [
                    'error_group_id' => $errorGroup->getId(),
                    'project_id' => $project->getId(),
                    'user_id' => $user->getId(),
                    'is_new_group' => $isNewGroup
                ]);
            }

        } catch (\Exception $e) {
            // Ne pas faire échouer le traitement de l'erreur si l'alerte échoue
            $this->logger->error('ErrorProcessor: Échec envoi alerte', [
                'error' => $e->getMessage(),
                'error_group_id' => $errorGroup->getId(),
                'project_id' => $project->getId()
            ]);
        }
    }

    /**
     * Gère l'envoi vers les webhooks externes
     */
    private function handleExternalWebhooks(ErrorGroup $errorGroup, \App\Entity\Project $project, bool $isNewGroup): void
    {
        try {
            // Déterminer le type d'événement
            $event = $isNewGroup ? 'error.created' : 'error.occurred';
            
            // Si c'est une erreur critique, utiliser un événement spécifique
            if ($this->alertService->isCriticalError($errorGroup)) {
                $event = 'error.critical';
            }

            // Envoyer aux webhooks configurés pour cet événement
            $this->externalWebhookService->sendToExternalWebhooks($errorGroup, $project, $event);
            
            $this->logger->info('ErrorProcessor: Webhooks externes traités', [
                'error_group_id' => $errorGroup->getId(),
                'project_id' => $project->getId(),
                'event' => $event,
                'is_new_group' => $isNewGroup
            ]);

        } catch (\Exception $e) {
            // Ne pas faire échouer le traitement de l'erreur si les webhooks externes échouent
            $this->logger->error('ErrorProcessor: Échec webhooks externes', [
                'error' => $e->getMessage(),
                'error_group_id' => $errorGroup->getId(),
                'project_id' => $project->getId()
            ]);
        }
    }
}
