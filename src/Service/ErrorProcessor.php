<?php

namespace App\Service;

use App\Entity\ErrorGroup;
use App\Entity\ErrorOccurrence;
use App\Repository\ErrorGroupRepository;
use App\Repository\ErrorOccurrenceRepository;
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
        private ExternalWebhookService $externalWebhookService
    ) {}

    /**
     * Traite une erreur reçue via webhook
     */
    public function processError(array $payload, string $token): array
    {
        try {
            // Récupérer le projet depuis le token
            $projectRepository = $this->entityManager->getRepository(\App\Entity\Project::class);
            $project = $projectRepository->findByWebhookToken($token);

            if (!$project) {
                throw new \InvalidArgumentException('Projet non trouvé pour ce token');
            }

            // Nettoyer et normaliser le payload
            $normalizedPayload = $this->normalizePayload($payload);

            // Utiliser le nom du projet depuis la BDD (priorité sur le payload)
            $normalizedPayload['project'] = $project->getSlug();

            // Générer le fingerprint pour grouper les erreurs similaires
            $fingerprint = $this->generateFingerprint($normalizedPayload);

            // Trouver ou créer le groupe d'erreur
            $errorGroup = $this->findOrCreateErrorGroup($fingerprint, $normalizedPayload, $project);

            // Créer l'occurrence
            $occurrence = $this->createOccurrence($errorGroup, $normalizedPayload);

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
                'new_group' => $isNewGroup
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
     * Nettoie et normalise le payload reçu
     */
    private function normalizePayload(array $payload): array
    {
        $normalized = [
            // Champs obligatoires
            'message' => trim($payload['message']),
            'exception_class' => trim($payload['exception_class']),
            'file' => $this->normalizePath($payload['file']),
            'line' => (int) $payload['line'],
            'project' => $this->normalizeProjectName($payload['project']),

            // Champs optionnels avec valeurs par défaut
            'environment' => $payload['environment'] ?? 'unknown',
            'http_status' => isset($payload['http_status']) ? (int) $payload['http_status'] : null,
            'stack_trace' => $payload['stack_trace'] ?? '',
            'error_type' => $this->detectErrorType($payload['exception_class']),
            'timestamp' => $this->parseTimestamp($payload['timestamp'] ?? null),

            // Données contextuelles
            'request' => $payload['request'] ?? [],
            'server' => $payload['server'] ?? [],
            'context' => $payload['context'] ?? []
        ];

        // Extraire les données de requête si présentes
        if (isset($payload['request'])) {
            $normalized['url'] = $payload['request']['url'] ?? null;
            $normalized['http_method'] = $payload['request']['method'] ?? null;
            $normalized['ip_address'] = $payload['request']['ip'] ?? null;
            $normalized['user_agent'] = $payload['request']['user_agent'] ?? null;
            $normalized['user_id'] = $payload['request']['user_id'] ?? $payload['context']['user']['id'] ?? null;
        }

        // Extraire les métriques serveur si présentes
        if (isset($payload['server'])) {
            $normalized['memory_usage'] = isset($payload['server']['memory_usage']) ? (int) $payload['server']['memory_usage'] : null;
            $normalized['execution_time'] = isset($payload['server']['execution_time']) ? (float) $payload['server']['execution_time'] : null;
        }

        return $normalized;
    }

    /**
     * Génère un fingerprint unique pour grouper les erreurs similaires
     */
    private function generateFingerprint(array $payload): string
    {
        // Utiliser la méthode de l'entité ErrorGroup pour cohérence
        return ErrorGroup::generateFingerprint(
            $payload['exception_class'],
            $payload['file'],
            $payload['line'],
            $payload['message']
        );
    }

    /**
     * Trouve un groupe d'erreur existant ou en crée un nouveau
     */
    private function findOrCreateErrorGroup(string $fingerprint, array $payload, \App\Entity\Project $project): ErrorGroup
    {
        $errorGroup = $this->errorGroupRepository->findOneBy(['fingerprint' => $fingerprint]);

        if (!$errorGroup) {
            // Créer un nouveau groupe
            $errorGroup = new ErrorGroup();
            $errorGroup->setFingerprint($fingerprint)
                ->setMessage($payload['message'])
                ->setExceptionClass($payload['exception_class'])
                ->setFile($payload['file'])
                ->setLine($payload['line'])
                ->setProject($payload['project'])
                ->setProjectEntity($project)  // Nouvelle relation
                ->setHttpStatusCode($payload['http_status'])
                ->setErrorType($payload['error_type'])
                ->setEnvironment($payload['environment'])
                ->setStackTracePreview($this->extractStackTracePreview($payload['stack_trace']));

            $this->entityManager->persist($errorGroup);

            $this->logger->info('ErrorProcessor: Nouveau groupe d\'erreur créé', [
                'fingerprint' => $fingerprint,
                'project' => $payload['project'],
                'project_id' => $project->getId(),
                'exception_class' => $payload['exception_class']
            ]);
        } else {
            // Mettre à jour les informations du groupe existant
            $this->updateExistingGroup($errorGroup, $payload);
        }

        // Incrémenter le compteur d'occurrences
        $errorGroup->incrementOccurrenceCount();

        return $errorGroup;
    }

    /**
     * Met à jour un groupe d'erreur existant si nécessaire
     */
    private function updateExistingGroup(ErrorGroup $errorGroup, array $payload): void
    {
        $updated = false;

        // Mettre à jour l'environnement si pas défini ou différent
        if (!$errorGroup->getEnvironment() || $errorGroup->getEnvironment() === 'unknown') {
            if (isset($payload['environment']) && $payload['environment'] && $payload['environment'] !== 'unknown') {
                $errorGroup->setEnvironment($payload['environment']);
                $updated = true;
            }
        }

        // Mettre à jour le code HTTP si pas défini
        if (!$errorGroup->getHttpStatusCode() && isset($payload['http_status']) && $payload['http_status']) {
            $errorGroup->setHttpStatusCode($payload['http_status']);
            $updated = true;
        }

        // Rouvrir le groupe s'il était résolu ou ignoré
        if ($errorGroup->getStatus() !== ErrorGroup::STATUS_OPEN) {
            $errorGroup->reopen();
            $updated = true;

            $this->logger->info('ErrorProcessor: Groupe d\'erreur rouvert', [
                'error_group_id' => $errorGroup->getId(),
                'previous_status' => $errorGroup->getStatus()
            ]);
        }

        if ($updated) {
            $this->logger->debug('ErrorProcessor: Groupe d\'erreur mis à jour', [
                'error_group_id' => $errorGroup->getId()
            ]);
        }
    }

    /**
     * Crée une nouvelle occurrence d'erreur
     */
    private function createOccurrence(ErrorGroup $errorGroup, array $payload): ErrorOccurrence
    {
        $occurrence = new ErrorOccurrence();
        $occurrence->setErrorGroup($errorGroup)
            ->setStackTrace($payload['stack_trace'])
            ->setEnvironment($payload['environment'])
            ->setRequest($payload['request'])
            ->setServer($payload['server'])
            ->setContext($payload['context'])
            ->setCreatedAt($payload['timestamp']);

        // Définir les champs extraits pour les index
        if (isset($payload['url'])) {
            $occurrence->setUrl($payload['url']);
        }
        if (isset($payload['http_method'])) {
            $occurrence->setHttpMethod($payload['http_method']);
        }
        if (isset($payload['ip_address'])) {
            $occurrence->setIpAddress($payload['ip_address']);
        }
        if (isset($payload['user_agent'])) {
            $occurrence->setUserAgent($payload['user_agent']);
        }
        if (isset($payload['user_id'])) {
            $occurrence->setUserId($payload['user_id']);
        }
        if (isset($payload['memory_usage'])) {
            $occurrence->setMemoryUsage($payload['memory_usage']);
        }
        if (isset($payload['execution_time'])) {
            $occurrence->setExecutionTime($payload['execution_time']);
        }

        $this->entityManager->persist($occurrence);

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
     * Détecte le type d'erreur basé sur la classe d'exception
     */
    private function detectErrorType(string $exceptionClass): string
    {
        $class = strtolower($exceptionClass);

        if (str_contains($class, 'error')) {
            return ErrorGroup::ERROR_TYPE_ERROR;
        }

        if (str_contains($class, 'warning')) {
            return ErrorGroup::ERROR_TYPE_WARNING;
        }

        if (str_contains($class, 'notice')) {
            return ErrorGroup::ERROR_TYPE_NOTICE;
        }

        // Par défaut, considérer comme une exception
        return ErrorGroup::ERROR_TYPE_EXCEPTION;
    }

    /**
     * Parse un timestamp reçu
     */
    private function parseTimestamp(?string $timestamp): \DateTime
    {
        if (!$timestamp) {
            return new \DateTime();
        }

        try {
            return new \DateTime($timestamp);
        } catch (\Exception $e) {
            $this->logger->warning('ErrorProcessor: Timestamp invalide', [
                'timestamp' => $timestamp,
                'error' => $e->getMessage()
            ]);

            return new \DateTime();
        }
    }

    /**
     * Extrait un aperçu de la stack trace pour l'affichage
     */
    private function extractStackTracePreview(string $stackTrace): string
    {
        if (!$stackTrace) {
            return '';
        }

        // Prendre les 3 premières lignes de la stack trace
        $lines = explode("\n", $stackTrace);
        $preview = array_slice($lines, 0, 3);

        return implode("\n", $preview);
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
