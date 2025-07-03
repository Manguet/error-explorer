<?php

namespace App\Service;

use App\Entity\ErrorGroup;
use App\Entity\Project;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class AiSuggestionService
{
    private const OPENAI_API_ENDPOINT = 'https://api.openai.com/v1/chat/completions';
    private const GROQ_API_ENDPOINT = 'https://api.groq.com/openai/v1/chat/completions';
    private const MAX_CONTEXT_LENGTH = 3000;
    private const MAX_STACK_TRACE_LENGTH = 2000;

    private string $aiProvider;
    private string $openaiApiKey;
    private string $groqApiKey;
    private EntityManagerInterface $entityManager;

    public function __construct(
        EntityManagerInterface $entityManager,
        string $aiProvider = null,
        string $openaiApiKey = null,
        string $groqApiKey = null
    ) {
        $this->entityManager = $entityManager;
        $this->aiProvider = $aiProvider ?: 'rules';
        $this->openaiApiKey = $openaiApiKey ?: '';
        $this->groqApiKey = $groqApiKey ?: '';
    }

    /**
     * Génère des suggestions d'IA pour une erreur donnée
     */
    public function generateSuggestions(ErrorGroup $errorGroup, User $user): array
    {
        // Vérifier si l'utilisateur peut utiliser les suggestions IA
        if ($user->canUseAiSuggestions()) {
            // Obtenir les providers disponibles pour cet utilisateur
            $providers = $this->getAvailableProvidersForUser($user);

            foreach ($providers as $provider) {
                try {
                    $prompt = $this->buildPrompt($errorGroup);
                    $response = $this->callAiProvider($provider, $prompt);
                    
                    // Incrémenter le compteur d'utilisation IA
                    $user->incrementAiSuggestions();
                    $this->entityManager->persist($user);
                    $this->entityManager->flush();

                    return $this->parseAiResponse($response, $errorGroup, $provider);
                } catch (\Exception $e) {
                    error_log("AI Provider '{$provider}' failed: " . $e->getMessage());
                }
            }
        }

        // Si l'utilisateur n'a pas de plan IA ou si tous les providers échouent, utiliser les règles
        return $this->getFallbackSuggestions($errorGroup);
    }

    /**
     * Retourne la liste des providers disponibles pour un utilisateur donné
     */
    private function getAvailableProvidersForUser(User $user): array
    {
        $providers = [];
        $userAiProvider = $user->getAvailableAiProvider();

        if (!$userAiProvider) {
            return $providers;
        }

        // Selon le plan de l'utilisateur
        switch ($userAiProvider) {
            case 'groq':
                if (!empty($this->groqApiKey)) {
                    $providers[] = 'groq';
                }
                break;
            case 'openai':
                if (!empty($this->openaiApiKey)) {
                    $providers[] = 'openai';
                }
                break;
            case 'all':
                // Plan premium : Groq d'abord (plus rapide), puis OpenAI
                if (!empty($this->groqApiKey)) {
                    $providers[] = 'groq';
                }
                if (!empty($this->openaiApiKey)) {
                    $providers[] = 'openai';
                }
                break;
        }

        return $providers;
    }

    /**
     * Retourne la liste des providers disponibles dans l'ordre de préférence (méthode legacy)
     */
    private function getAvailableProviders(): array
    {
        $providers = [];

        // Ajouter les providers selon la configuration et les clés disponibles
        if ($this->aiProvider === 'groq' && !empty($this->groqApiKey)) {
            $providers[] = 'groq';
        }

        if ($this->aiProvider === 'openai' && !empty($this->openaiApiKey)) {
            $providers[] = 'openai';
        }

        // Fallback: si provider configuré non disponible, essayer les autres
        if (empty($providers)) {
            if (!empty($this->groqApiKey)) {
                $providers[] = 'groq';
            }
            if (!empty($this->openaiApiKey)) {
                $providers[] = 'openai';
            }
        }

        return $providers;
    }

    /**
     * Construit le prompt pour l'IA basé sur l'erreur
     */
    private function buildPrompt(ErrorGroup $errorGroup): string
    {
        $project = $errorGroup->getProjectEntity();
        $context = $this->prepareContext($errorGroup);

        $prompt = "Tu es un expert en débogage de code. Analyse cette erreur et fournis des suggestions de résolution.\n\n";

        // Informations sur l'erreur
        $prompt .= "## Détails de l'erreur:\n";
        $prompt .= "- Type: {$errorGroup->getExceptionClass()}\n";
        $prompt .= "- Message: {$errorGroup->getMessage()}\n";
        $prompt .= "- Fichier: {$errorGroup->getFile()}:{$errorGroup->getLine()}\n";
        $prompt .= "- Environnement: {$errorGroup->getEnvironment()}\n";
        $prompt .= "- Occurrences: {$errorGroup->getOccurrenceCount()}\n\n";

        // Stack trace tronquée
        if ($stackTrace = $errorGroup->getStackTracePreview()) {
            $shortStackTrace = substr($stackTrace, 0, self::MAX_STACK_TRACE_LENGTH);
            $prompt .= "## Stack Trace:\n```\n{$shortStackTrace}\n```\n\n";
        }

        // Contexte additionnel
        if (!empty($context)) {
            $contextStr = substr(json_encode($context, JSON_PRETTY_PRINT), 0, self::MAX_CONTEXT_LENGTH);
            $prompt .= "## Contexte:\n```json\n{$contextStr}\n```\n\n";
        }

        // Informations sur le repository si disponible
        if ($project && $project->getRepositoryUrl()) {
            $prompt .= "## Repository: {$project->getRepositoryUrl()}\n\n";
        }

        $prompt .= "IMPORTANT: Réponds UNIQUEMENT avec un objet JSON valide, sans texte avant ou après. Format exact :\n";
        $prompt .= "{\n";
        $prompt .= '  "severity": "low|medium|high|critical",';
        $prompt .= '  "category": "bug|configuration|dependency|performance|security",';
        $prompt .= '  "immediate_actions": ["action1", "action2"],';
        $prompt .= '  "root_cause": "Explication probable de la cause",';
        $prompt .= '  "solutions": [';
        $prompt .= '    {"title": "Solution 1", "description": "Description", "code_example": "code optionnel"},';
        $prompt .= '    {"title": "Solution 2", "description": "Description"}';
        $prompt .= '  ],';
        $prompt .= '  "preventive_measures": ["mesure1", "mesure2"],';
        $prompt .= '  "similar_issues_keywords": ["keyword1", "keyword2"]';
        $prompt .= "}\n";
        $prompt .= "Ne pas inclure de texte explicatif, juste le JSON.";

        return $prompt;
    }

    /**
     * Prépare le contexte pour l'IA
     */
    private function prepareContext(ErrorGroup $errorGroup): array
    {
        $context = [];

        // Ajouter le contexte de la dernière occurrence
        $latestOccurrence = $errorGroup->getLatestOccurrence();
        if ($latestOccurrence) {
            $latestContext = $latestOccurrence->getContext();
            if (!empty($latestContext)) {
                $context['latest_context'] = $latestContext;
            }
            
            // Ajouter les informations de requête
            $latestRequest = $latestOccurrence->getRequest();
            if (!empty($latestRequest)) {
                // Filtrer les données sensibles
                $filteredRequest = $this->filterSensitiveData($latestRequest);
                $context['request'] = $filteredRequest;
            }
        }

        return $context;
    }

    /**
     * Filtre les données sensibles du contexte
     */
    private function filterSensitiveData(array $data): array
    {
        $sensitiveKeys = ['password', 'token', 'secret', 'key', 'authorization', 'cookie', 'session'];

        return array_filter($data, function($key) use ($sensitiveKeys) {
            $keyLower = strtolower($key);
            foreach ($sensitiveKeys as $sensitive) {
                if (str_contains($keyLower, $sensitive)) {
                    return false;
                }
            }
            return true;
        }, ARRAY_FILTER_USE_KEY);
    }

    /**
     * Appelle le provider d'IA spécifié
     */
    private function callAiProvider(string $provider, string $prompt): array
    {
        switch ($provider) {
            case 'groq':
                return $this->callGroq($prompt);
            case 'openai':
                return $this->callOpenAI($prompt);
            default:
                throw new \Exception("Unknown AI provider: {$provider}");
        }
    }

    /**
     * Appelle l'API Groq
     */
    private function callGroq(string $prompt): array
    {
        $data = [
            'model' => 'llama-3.1-8b-instant', // Modèle Groq gratuit et performant
            'messages' => [
                ['role' => 'user', 'content' => $prompt]
            ],
            'max_tokens' => 1000,
            'temperature' => 0.7
        ];

        $options = [
            'http' => [
                'header' => [
                    "Content-Type: application/json",
                    "Authorization: Bearer {$this->groqApiKey}"
                ],
                'method' => 'POST',
                'content' => json_encode($data)
            ]
        ];

        $context = stream_context_create($options);
        $result = file_get_contents(self::GROQ_API_ENDPOINT, false, $context);

        if ($result === false) {
            throw new \Exception('Failed to call Groq API');
        }

        $response = json_decode($result, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('Invalid JSON response from Groq');
        }

        if (isset($response['error'])) {
            throw new \Exception('Groq API error: ' . $response['error']['message']);
        }

        return $response;
    }

    /**
     * Appelle l'API OpenAI
     */
    private function callOpenAI(string $prompt): array
    {
        $data = [
            'model' => 'gpt-3.5-turbo',
            'messages' => [
                ['role' => 'user', 'content' => $prompt]
            ],
            'max_tokens' => 1000,
            'temperature' => 0.7
        ];

        $options = [
            'http' => [
                'header' => [
                    "Content-Type: application/json",
                    "Authorization: Bearer {$this->openaiApiKey}"
                ],
                'method' => 'POST',
                'content' => json_encode($data)
            ]
        ];

        $context = stream_context_create($options);
        $result = file_get_contents(self::OPENAI_API_ENDPOINT, false, $context);

        if ($result === false) {
            throw new \Exception('Failed to call OpenAI API');
        }

        $response = json_decode($result, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('Invalid JSON response from OpenAI');
        }

        if (isset($response['error'])) {
            throw new \Exception('OpenAI API error: ' . $response['error']['message']);
        }

        return $response;
    }

    /**
     * Parse la réponse de l'IA
     */
    private function parseAiResponse(array $response, ErrorGroup $errorGroup, string $provider): array
    {
        if (!isset($response['choices'][0]['message']['content'])) {
            return $this->getFallbackSuggestions($errorGroup);
        }

        $content = $response['choices'][0]['message']['content'];

        // Extraire le JSON de la réponse
        if (preg_match('/\{.*\}/s', $content, $matches)) {
            $jsonResponse = json_decode($matches[0], true);

            if (json_last_error() === JSON_ERROR_NONE && $this->validateAiResponse($jsonResponse)) {
                $modelName = $provider === 'groq' ? 'llama-3.1-8b-instant' : 'gpt-3.5-turbo';

                return [
                    'source' => 'ai',
                    'provider' => $provider,
                    'suggestions' => $jsonResponse,
                    'generated_at' => (new \DateTime())->format('d/m/Y H:i'),
                    'model' => $modelName
                ];
            }
        }

        return $this->getFallbackSuggestions($errorGroup);
    }

    /**
     * Valide la structure de la réponse IA
     */
    private function validateAiResponse(array $response): bool
    {
        $required = ['severity', 'category', 'immediate_actions', 'root_cause', 'solutions'];

        foreach ($required as $field) {
            if (!isset($response[$field])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Génère des suggestions de fallback basées sur l'analyse de patterns
     */
    private function getFallbackSuggestions(ErrorGroup $errorGroup): array
    {
        $exceptionClass = $errorGroup->getExceptionClass();
        $message = $errorGroup->getMessage();
        $file = $errorGroup->getFile();

        $suggestions = [
            'source' => 'rules',
            'generated_at' => (new \DateTime())->format('d/m/Y H:i'),
        ];

        // Analyse basée sur le type d'exception
        switch (true) {
            case str_contains($exceptionClass, 'PDOException'):
            case str_contains($exceptionClass, 'DatabaseException'):
                $suggestions['suggestions'] = $this->getDatabaseErrorSuggestions($message);
                break;

            case str_contains($exceptionClass, 'FileNotFoundException'):
            case str_contains($exceptionClass, 'FileException'):
                $suggestions['suggestions'] = $this->getFileErrorSuggestions($message, $file);
                break;

            case str_contains($exceptionClass, 'MemoryLimitError'):
                $suggestions['suggestions'] = $this->getMemoryErrorSuggestions();
                break;

            case str_contains($exceptionClass, 'TypeError'):
            case str_contains($exceptionClass, 'ArgumentCountError'):
                $suggestions['suggestions'] = $this->getTypeErrorSuggestions($message);
                break;

            case str_contains($exceptionClass, 'HttpException'):
            case str_contains($message, '404'):
            case str_contains($message, '500'):
                $suggestions['suggestions'] = $this->getHttpErrorSuggestions($errorGroup);
                break;

            default:
                $suggestions['suggestions'] = $this->getGenericErrorSuggestions($errorGroup);
        }

        return $suggestions;
    }

    private function getDatabaseErrorSuggestions(string $message): array
    {
        return [
            'severity' => 'high',
            'category' => 'configuration',
            'immediate_actions' => [
                'Vérifier la connexion à la base de données',
                'Vérifier les paramètres de configuration DB',
                'Contrôler l\'état du serveur de base de données'
            ],
            'root_cause' => 'Problème de connexion ou de configuration de base de données',
            'solutions' => [
                [
                    'title' => 'Vérifier la configuration',
                    'description' => 'Contrôler les paramètres DATABASE_URL dans .env'
                ],
                [
                    'title' => 'Tester la connexion',
                    'description' => 'Utiliser un client DB pour tester la connexion directement'
                ]
            ],
            'preventive_measures' => [
                'Mettre en place un monitoring de la DB',
                'Configurer des alertes de connexion'
            ],
            'similar_issues_keywords' => ['database', 'connection', 'pdo', 'mysql', 'postgresql']
        ];
    }

    private function getFileErrorSuggestions(string $message, string $file): array
    {
        return [
            'severity' => 'medium',
            'category' => 'configuration',
            'immediate_actions' => [
                'Vérifier que le fichier existe',
                'Contrôler les permissions',
                'Vérifier le chemin d\'accès'
            ],
            'root_cause' => 'Fichier manquant ou permissions insuffisantes',
            'solutions' => [
                [
                    'title' => 'Créer le fichier manquant',
                    'description' => 'Vérifier si le fichier doit être créé ou restauré'
                ],
                [
                    'title' => 'Corriger les permissions',
                    'description' => 'Ajuster les permissions du fichier/dossier',
                    'code_example' => 'chmod 644 ' . basename($file)
                ]
            ],
            'preventive_measures' => [
                'Vérifier l\'existence avant utilisation',
                'Mettre en place des fallbacks'
            ],
            'similar_issues_keywords' => ['file', 'permission', 'path', 'missing']
        ];
    }

    private function getMemoryErrorSuggestions(): array
    {
        return [
            'severity' => 'high',
            'category' => 'performance',
            'immediate_actions' => [
                'Augmenter memory_limit temporairement',
                'Identifier les variables consommatrices',
                'Optimiser les requêtes'
            ],
            'root_cause' => 'Consommation mémoire excessive',
            'solutions' => [
                [
                    'title' => 'Optimiser les requêtes',
                    'description' => 'Utiliser la pagination pour les grandes collections'
                ],
                [
                    'title' => 'Libérer la mémoire',
                    'description' => 'Utiliser unset() pour les grandes variables'
                ]
            ],
            'preventive_measures' => [
                'Monitoring de la mémoire',
                'Optimisation des requêtes DB',
                'Pagination systématique'
            ],
            'similar_issues_keywords' => ['memory', 'limit', 'performance', 'optimization']
        ];
    }

    private function getTypeErrorSuggestions(string $message): array
    {
        return [
            'severity' => 'medium',
            'category' => 'bug',
            'immediate_actions' => [
                'Vérifier les types de paramètres',
                'Contrôler les valeurs null',
                'Valider les données d\'entrée'
            ],
            'root_cause' => 'Incompatibilité de types ou paramètres incorrects',
            'solutions' => [
                [
                    'title' => 'Ajouter la validation des types',
                    'description' => 'Utiliser les déclarations de types PHP strictes'
                ],
                [
                    'title' => 'Gestion des valeurs null',
                    'description' => 'Vérifier les valeurs null avant utilisation'
                ]
            ],
            'preventive_measures' => [
                'Utiliser declare(strict_types=1)',
                'Valider les entrées utilisateur',
                'Tests unitaires'
            ],
            'similar_issues_keywords' => ['type', 'argument', 'parameter', 'validation']
        ];
    }

    private function getHttpErrorSuggestions(ErrorGroup $errorGroup): array
    {
        $httpStatus = $errorGroup->getHttpStatusCode();

        return [
            'severity' => $httpStatus >= 500 ? 'high' : 'medium',
            'category' => $httpStatus >= 500 ? 'bug' : 'configuration',
            'immediate_actions' => [
                'Vérifier les logs du serveur web',
                'Contrôler la configuration des routes',
                'Tester la disponibilité du service'
            ],
            'root_cause' => $httpStatus >= 500 ? 'Erreur serveur interne' : 'Problème de configuration ou ressource manquante',
            'solutions' => [
                [
                    'title' => 'Vérifier les routes',
                    'description' => 'Contrôler la configuration du routing'
                ],
                [
                    'title' => 'Logs détaillés',
                    'description' => 'Activer les logs détaillés pour plus d\'informations'
                ]
            ],
            'preventive_measures' => [
                'Tests d\'intégration',
                'Monitoring des erreurs HTTP',
                'Documentation des API'
            ],
            'similar_issues_keywords' => ['http', 'route', 'controller', 'response']
        ];
    }

    private function getGenericErrorSuggestions(ErrorGroup $errorGroup): array
    {
        return [
            'severity' => 'medium',
            'category' => 'bug',
            'immediate_actions' => [
                'Analyser le contexte de l\'erreur',
                'Reproduire l\'erreur en local',
                'Vérifier les logs applicatifs'
            ],
            'root_cause' => 'Erreur applicative nécessitant une analyse approfondie',
            'solutions' => [
                [
                    'title' => 'Debug approfondi',
                    'description' => 'Ajouter des points de debug autour de l\'erreur'
                ],
                [
                    'title' => 'Vérifier les dépendances',
                    'description' => 'Contrôler les versions et la compatibilité'
                ]
            ],
            'preventive_measures' => [
                'Tests unitaires et fonctionnels',
                'Monitoring applicatif',
                'Revue de code'
            ],
            'similar_issues_keywords' => ['debug', 'error', 'exception', 'bug']
        ];
    }

    /**
     * Met en cache les suggestions pour éviter les appels répétés à l'IA
     */
    public function getCachedSuggestions(ErrorGroup $errorGroup): ?array
    {
        // Pour l'instant simple stockage en base, peut être étendu avec Redis
        $cacheKey = 'ai_suggestions_' . $errorGroup->getFingerprint();

        // Ici on pourrait implémenter un système de cache
        // Pour l'instant on retourne null pour générer à chaque fois
        return null;
    }

    /**
     * Sauvegarde les suggestions en cache
     */
    public function cacheSuggestions(ErrorGroup $errorGroup, array $suggestions): void
    {
        // Implémentation future avec Redis ou cache Symfony
        $cacheKey = 'ai_suggestions_' . $errorGroup->getFingerprint();

        // À implémenter avec le service de cache approprié
    }

    /**
     * Crée une réponse indiquant les limitations du plan
     */
    private function createPlanLimitationResponse(User $user): array
    {
        $plan = $user->getPlan();
        $usedSuggestions = $user->getCurrentMonthlyAiSuggestions();
        $maxSuggestions = $plan ? $plan->getMaxMonthlyAiSuggestions() : 0;

        if (!$plan || !$plan->hasAiSuggestions()) {
            return [
                'source' => 'plan_limitation',
                'generated_at' => (new \DateTime())->format('d/m/Y H:i'),
                'suggestions' => [
                    'severity' => 'info',
                    'category' => 'upgrade',
                    'immediate_actions' => [
                        'Mettre à niveau votre plan pour accéder aux suggestions IA',
                        'Utiliser les suggestions automatiques basées sur des règles'
                    ],
                    'root_cause' => 'Votre plan actuel ne comprend pas les suggestions IA avancées',
                    'solutions' => [
                        [
                            'title' => 'Plan IA Simple (Groq)',
                            'description' => 'Accès aux suggestions IA ultra-rapides avec Groq (gratuit et performant)'
                        ],
                        [
                            'title' => 'Plan IA Premium (OpenAI)',
                            'description' => 'Accès aux suggestions IA avancées avec OpenAI GPT + Groq en fallback'
                        ]
                    ],
                    'preventive_measures' => [
                        'Consulter nos plans tarifaires',
                        'Contacter le support pour plus d\'informations'
                    ],
                    'similar_issues_keywords' => ['plan', 'upgrade', 'ai', 'suggestions']
                ],
                'plan_info' => [
                    'current_plan' => $plan ? $plan->getName() : 'Aucun plan',
                    'has_ai' => $plan ? $plan->hasAiSuggestions() : false,
                    'used_suggestions' => $usedSuggestions,
                    'max_suggestions' => $maxSuggestions
                ]
            ];
        }

        return [
            'source' => 'quota_exceeded',
            'generated_at' => (new \DateTime())->format('d/m/Y H:i'),
            'suggestions' => [
                'severity' => 'warning',
                'category' => 'quota',
                'immediate_actions' => [
                    'Attendre le mois prochain pour de nouvelles suggestions IA',
                    'Mettre à niveau vers un plan avec plus de suggestions IA'
                ],
                'root_cause' => 'Quota mensuel de suggestions IA atteint',
                'solutions' => [
                    [
                        'title' => 'Attendre la réinitialisation',
                        'description' => 'Votre quota sera réinitialisé le mois prochain'
                    ],
                    [
                        'title' => 'Mettre à niveau le plan',
                        'description' => 'Accéder à plus de suggestions IA ou suggestions illimitées'
                    ]
                ],
                'preventive_measures' => [
                    'Optimiser l\'utilisation des suggestions IA',
                    'Considérer un plan avec plus de quota'
                ],
                'similar_issues_keywords' => ['quota', 'limit', 'upgrade', 'monthly']
            ],
            'quota_info' => [
                'used_suggestions' => $usedSuggestions,
                'max_suggestions' => $maxSuggestions,
                'remaining_suggestions' => max(0, $maxSuggestions - $usedSuggestions)
            ]
        ];
    }
}
