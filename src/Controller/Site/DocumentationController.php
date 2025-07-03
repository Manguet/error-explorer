<?php

namespace App\Controller\Site;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class DocumentationController extends AbstractController
{
    #[Route('/docs', name: 'documentation')]
    public function index(): Response
    {
        // Statistiques et informations pour la documentation basées sur les vrais packages
        $stats = [
            'supported_frameworks' => [
                'backend' => [
                    'symfony' => ['name' => 'Symfony', 'version' => '4.4+', 'icon' => '🎼', 'package' => 'error-explorer/error-reporter'],
                    'laravel' => ['name' => 'Laravel', 'version' => '8.0+', 'icon' => '🅻', 'package' => 'error-explorer/laravel-error-reporter'],
                    'php' => ['name' => 'PHP', 'version' => '7.2+', 'icon' => '🐘', 'package' => 'error-explorer/php-error-reporter'],
                    'python' => ['name' => 'Python', 'version' => '3.7+', 'icon' => '🐍', 'package' => 'error-explorer'],
                    'nodejs' => ['name' => 'Node.js', 'version' => '14+', 'icon' => '💚', 'package' => '@error-explorer/node-error-reporter'],
                    'wordpress' => ['name' => 'WordPress', 'version' => '5.0+', 'icon' => '📝', 'package' => 'error-explorer/wordpress-error-reporter'],
                ],
                'frontend' => [
                    'react' => ['name' => 'React', 'version' => '16.8+', 'icon' => '⚛️', 'package' => '@error-explorer/react-error-reporter'],
                    'vue' => ['name' => 'Vue.js', 'version' => '3.0+', 'icon' => '💚', 'package' => '@error-explorer/vue-error-reporter'],
                    'angular' => ['name' => 'Angular', 'version' => '12+', 'icon' => '🅰️', 'package' => '@error-explorer/angular-error-reporter'],
                ]
            ],
            'installation_time' => '< 5 minutes',
            'api_response_time' => '< 50ms',
            'uptime' => '99.9%'
        ];

        // Exemples de code réels basés sur les packages
        $codeExamples = [
            'symfony' => [
                'installation' => 'composer require error-explorer/error-reporter',
                'config' => 'error_reporter:
    webhook_url: \'%env(ERROR_WEBHOOK_URL)%\'
    token: \'%env(ERROR_WEBHOOK_TOKEN)%\'
    project_name: \'%env(PROJECT_NAME)%\'
    enabled: \'%env(bool:ERROR_REPORTING_ENABLED)%\'
    ignore_exceptions:
        - \'Symfony\Component\Security\Core\Exception\AccessDeniedException\'
        - \'Symfony\Component\HttpKernel\Exception\NotFoundHttpException\'',
                'usage' => 'use ErrorExplorer\ErrorReporter\ErrorReporter;

// Automatic capture (no code needed)
// Exceptions are automatically captured and sent

// Manual reporting
try {
    // Your code here
} catch (\Exception $e) {
    ErrorReporter::reportError($e, \'prod\', 500);
    throw $e;
}

// Add breadcrumbs for context
ErrorReporter::addBreadcrumb(\'User action\', \'user\', \'info\');'
            ],
            'laravel' => [
                'installation' => 'composer require error-explorer/laravel-error-reporter',
                'config' => 'ERROR_WEBHOOK_URL=https://your-error-explorer.com
ERROR_WEBHOOK_TOKEN=your-project-token
PROJECT_NAME="My Laravel App"
ERROR_REPORTING_ENABLED=true',
                'usage' => 'use ErrorExplorer\LaravelErrorReporter\Facades\ErrorReporter;

// Automatic via service provider
// Exceptions are captured automatically

// Manual reporting
try {
    // Your code here
} catch (\Exception $e) {
    ErrorReporter::reportError($e, \'production\', 500);
    throw $e;
}'
            ],
            'php' => [
                'installation' => 'composer require error-explorer/php-error-reporter',
                'config' => 'use ErrorExplorer\ErrorExplorer;

$config = [
    \'webhook_url\' => \'https://your-error-explorer.com\',
    \'token\' => \'your-project-token\',
    \'project\' => \'my-php-app\',
    \'environment\' => \'production\'
];

ErrorExplorer::init($config);',
                'usage' => 'use ErrorExplorer\ErrorExplorer;

try {
    // Your code here
} catch (Exception $e) {
    ErrorExplorer::captureException($e);
    throw $e;
}

// Add context
ErrorExplorer::addBreadcrumb(\'User login attempt\');'
            ],
            'react' => [
                'installation' => 'npm install @error-explorer/react-error-reporter',
                'config' => 'import { ErrorReporterProvider } from \'@error-explorer/react-error-reporter\';

<ErrorReporterProvider
  config={{
    projectToken: \'your-project-token\',
    apiUrl: \'https://your-error-explorer.com\',
    environment: \'production\'
  }}
>
  <App />
</ErrorReporterProvider>',
                'usage' => 'import { useErrorReporter } from \'@error-explorer/react-error-reporter\';

const { reportError, addBreadcrumb } = useErrorReporter();

try {
  await riskyOperation();
} catch (error) {
  await reportError(error, { context: \'user_action\' });
}

// Error Boundary
<ErrorBoundary fallback={<ErrorUI />}>
  <MyComponent />
</ErrorBoundary>'
            ],
            'vue' => [
                'installation' => 'npm install @error-explorer/vue-error-reporter',
                'config' => 'import { createApp } from \'vue\';
import { ErrorExplorerPlugin } from \'@error-explorer/vue-error-reporter\';

const app = createApp(App);
app.use(ErrorExplorerPlugin, {
  projectToken: \'your-project-token\',
  apiUrl: \'https://your-error-explorer.com\',
  environment: \'production\'
});',
                'usage' => 'import { useErrorExplorer } from \'@error-explorer/vue-error-reporter\';

const { captureException, addBreadcrumb } = useErrorExplorer();

try {
  await someAsyncOperation();
} catch (error) {
  captureException(error);
}'
            ],
            'nodejs' => [
                'installation' => 'npm install @error-explorer/node-error-reporter',
                'config' => 'const ErrorReporter = require(\'@error-explorer/node-error-reporter\');

ErrorReporter.init({
  webhookUrl: \'https://your-error-explorer.com\',
  token: \'your-project-token\',
  project: \'my-node-app\',
  environment: \'production\'
});',
                'usage' => 'const ErrorReporter = require(\'@error-explorer/node-error-reporter\');

// Express middleware
app.use(ErrorReporter.middleware());

// Manual reporting
try {
  // Your code here
} catch (error) {
  ErrorReporter.captureException(error);
  throw error;
}'
            ],
            'python' => [
                'installation' => 'pip install error-explorer',
                'config' => 'import error_explorer

error_explorer.init(
    webhook_url="https://your-error-explorer.com",
    token="your-project-token",
    project="my-python-app",
    environment="production"
)',
                'usage' => 'import error_explorer

try:
    # Your code here
    pass
except Exception as e:
    error_explorer.capture_exception(e)
    raise

# Flask integration
from error_explorer.integrations.flask import ErrorExplorerFlask
ErrorExplorerFlask(app)'
            ],
            'angular' => [
                'installation' => 'npm install @error-explorer/angular-error-reporter',
                'config' => 'import { ErrorExplorerModule } from \'@error-explorer/angular-error-reporter\';

@NgModule({
  imports: [
    ErrorExplorerModule.forRoot({
      projectToken: \'your-project-token\',
      apiUrl: \'https://your-error-explorer.com\',
      environment: \'production\'
    })
  ]
})',
                'usage' => 'import { ErrorExplorerService } from \'@error-explorer/angular-error-reporter\';

constructor(private errorReporter: ErrorExplorerService) {}

try {
  // Your code here
} catch (error) {
  this.errorReporter.captureException(error);
}'
            ],
            'wordpress' => [
                'installation' => 'composer require error-explorer/wordpress-error-reporter',
                'config' => '// wp-config.php
define(\'ERROR_EXPLORER_WEBHOOK_URL\', \'https://your-error-explorer.com\');
define(\'ERROR_EXPLORER_TOKEN\', \'your-project-token\');
define(\'ERROR_EXPLORER_PROJECT\', \'my-wordpress-site\');
define(\'ERROR_EXPLORER_ENABLED\', true);

// Activate the plugin',
                'usage' => '// Automatic error capture
// All PHP errors and exceptions are captured automatically

// Manual reporting in themes/plugins
if (function_exists(\'error_explorer_capture\')) {
    try {
        // Your code here
    } catch (Exception $e) {
        error_explorer_capture($e);
        throw $e;
    }
}'
            ]
        ];

        // Liens rapides et raccourcis
        $quickLinks = [
            [
                'title' => 'Démarrage rapide',
                'description' => 'Intégrez Error Explorer en moins de 5 minutes',
                'href' => '#getting-started',
                'icon' => '🚀',
                'color' => 'primary'
            ],
            [
                'title' => 'Intégrations',
                'description' => 'SDKs pour tous les frameworks populaires',
                'href' => '#integrations',
                'icon' => '🔧',
                'color' => 'success'
            ],
            [
                'title' => 'API Reference',
                'description' => 'Documentation complète de l\'API',
                'href' => '#api-reference',
                'icon' => '📚',
                'color' => 'info'
            ],
            [
                'title' => 'Exemples',
                'description' => 'Exemples de code prêts à utiliser',
                'href' => '#examples',
                'icon' => '💡',
                'color' => 'warning'
            ]
        ];

        // FAQ pour la documentation
        $faq = [
            [
                'question' => 'Comment installer Error Explorer ?',
                'answer' => 'L\'installation se fait en moins de 5 minutes ! Consultez notre guide d\'intégration qui couvre plus de 20 frameworks. Pour Symfony : `composer require error-explorer/error-reporter` et 5 variables d\'environnement.',
                'category' => 'installation'
            ],
            [
                'question' => 'Quels frameworks sont supportés ?',
                'answer' => 'Nous supportons tous les frameworks populaires : Symfony, Laravel, React, Vue.js, Angular, Node.js, Python, WordPress et bien d\'autres.',
                'category' => 'compatibility'
            ],
            [
                'question' => 'Comment tester l\'intégration ?',
                'answer' => 'Créez une route de test qui déclenche une exception. Les erreurs devraient apparaître dans votre dashboard en quelques secondes.',
                'category' => 'testing'
            ],
            [
                'question' => 'Les erreurs n\'apparaissent pas, que faire ?',
                'answer' => 'Vérifiez que ERROR_REPORTING_ENABLED=true, que l\'URL webhook et le token sont corrects, et consultez les logs pour les erreurs HTTP.',
                'category' => 'troubleshooting'
            ]
        ];

        return $this->render('home/documentation.html.twig', [
            'stats' => $stats,
            'code_examples' => $codeExamples,
            'quick_links' => $quickLinks,
            'faq' => $faq
        ]);
    }

    #[Route('/docs/api', name: 'api_docs')]
    public function apiDocs(): Response
    {
        // Documentation API détaillée
        $apiEndpoints = [
            [
                'method' => 'POST',
                'endpoint' => '/webhook/error/{token}',
                'description' => 'Signaler une erreur',
                'parameters' => [
                    'message' => ['type' => 'string', 'required' => true, 'description' => 'Message d\'erreur'],
                    'exception_class' => ['type' => 'string', 'required' => true, 'description' => 'Classe de l\'exception'],
                    'stack_trace' => ['type' => 'string', 'required' => true, 'description' => 'Stack trace complète'],
                    'file' => ['type' => 'string', 'required' => true, 'description' => 'Fichier où l\'erreur s\'est produite'],
                    'line' => ['type' => 'integer', 'required' => true, 'description' => 'Ligne où l\'erreur s\'est produite'],
                    'project' => ['type' => 'string', 'required' => true, 'description' => 'Nom du projet'],
                    'environment' => ['type' => 'string', 'required' => true, 'description' => 'Environnement (dev/staging/prod)'],
                    'http_status' => ['type' => 'integer', 'required' => false, 'description' => 'Code de statut HTTP'],
                    'timestamp' => ['type' => 'string', 'required' => true, 'description' => 'Date/heure ISO 8601'],
                    'fingerprint' => ['type' => 'string', 'required' => true, 'description' => 'Empreinte pour grouper les erreurs'],
                    'request' => ['type' => 'object', 'required' => false, 'description' => 'Données de la requête HTTP'],
                    'server' => ['type' => 'object', 'required' => false, 'description' => 'Informations serveur'],
                    'context' => ['type' => 'object', 'required' => false, 'description' => 'Contexte additionnel']
                ],
                'responses' => [
                    200 => 'Erreur enregistrée avec succès',
                    400 => 'Payload invalide',
                    401 => 'Token invalide ou manquant',
                    429 => 'Limite de taux dépassée',
                    500 => 'Erreur serveur interne'
                ]
            ],
            [
                'method' => 'GET',
                'endpoint' => '/api/projects/{id}/stats',
                'description' => 'Obtenir les statistiques d\'un projet',
                'parameters' => [
                    'period' => ['type' => 'string', 'required' => false, 'description' => 'Période (1h, 24h, 7d, 30d)'],
                    'group_by' => ['type' => 'string', 'required' => false, 'description' => 'Grouper par (hour, day, week)']
                ],
                'responses' => [
                    200 => 'Statistiques récupérées',
                    401 => 'Non autorisé',
                    404 => 'Projet non trouvé'
                ]
            ]
        ];

        $webhookFormat = [
            'request_headers' => [
                'Content-Type' => 'application/json',
                'User-Agent' => 'YourApp/1.0'
            ],
            'payload_example' => [
                'message' => 'Call to undefined method User::getEmail()',
                'exception_class' => 'Error',
                'stack_trace' => '#0 /var/www/src/Controller/UserController.php(45): UserController->updateProfile()',
                'file' => '/var/www/src/Controller/UserController.php',
                'line' => 45,
                'project' => 'mon-site-ecommerce',
                'environment' => 'production',
                'http_status' => 500,
                'timestamp' => '2025-06-02T14:30:25+02:00',
                'fingerprint' => 'abc123def456',
                'request' => [
                    'url' => 'https://monsite.com/profile/update',
                    'method' => 'POST',
                    'route' => 'user_profile_update',
                    'ip' => '192.168.1.100',
                    'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
                ],
                'server' => [
                    'php_version' => '8.2.7',
                    'memory_usage' => 12582912,
                    'memory_peak' => 15728640
                ],
                'context' => [
                    'user_id' => 123,
                    'additional_data' => 'any relevant context'
                ]
            ]
        ];

        return $this->render('home/api-docs.html.twig', [
            'api_endpoints' => $apiEndpoints,
            'webhook_format' => $webhookFormat
        ]);
    }

    #[Route('/docs/{framework}', name: 'docs_framework', requirements: ['framework' => 'symfony|laravel|react|vue|angular|nodejs|python|wordpress|php'])]
    public function frameworkDocs(string $framework): Response
    {
        // Documentation spécifique par framework
        $frameworkData = $this->getFrameworkData($framework);

        if (!$frameworkData) {
            throw $this->createNotFoundException('Framework documentation not found');
        }

        return $this->render('home/framework-docs.html.twig', [
            'framework' => $framework,
            'framework_data' => $frameworkData
        ]);
    }

    /**
     * Données spécifiques par framework
     */
    private function getFrameworkData(string $framework): ?array
    {
        $frameworksData = [
            'symfony' => [
                'name' => 'Symfony',
                'icon' => '🎼',
                'version' => '4.4+',
                'php_version' => '7.2+',
                'installation' => [
                    'composer' => 'composer require error-explorer/error-reporter',
                    'bundle_registration' => true,
                    'config_file' => 'config/packages/error_reporter.yaml'
                ],
                'features' => [
                    'Capture automatique des exceptions',
                    'Intégration avec le système d\'événements Symfony',
                    'Support des formulaires et validations',
                    'Gestion des utilisateurs Symfony',
                    'Compatible avec Doctrine ORM'
                ],
                'examples' => [
                    'basic_usage' => 'ErrorReporter::reportError($exception, \'prod\', 500);',
                    'with_context' => 'ErrorReporter::reportWithContext($exception, \'prod\', 500, $request);',
                    'breadcrumbs' => 'ErrorReporter::addBreadcrumb(\'User action\', \'user\');'
                ]
            ],
            'laravel' => [
                'name' => 'Laravel',
                'icon' => '🅻',
                'version' => '8.0+',
                'php_version' => '7.4+',
                'installation' => [
                    'composer' => 'composer require error-explorer/laravel-error-reporter',
                    'auto_discovery' => true,
                    'config_publish' => 'php artisan vendor:publish --tag=error-reporter-config'
                ],
                'features' => [
                    'Auto-découverte du service provider',
                    'Intégration avec les queues Laravel',
                    'Support d\'Eloquent ORM',
                    'Middleware automatique',
                    'Facade Laravel'
                ],
                'examples' => [
                    'facade_usage' => 'ErrorReporter::reportError($exception, \'production\', 500);',
                    'middleware' => 'Capture automatique via middleware',
                    'queue_jobs' => 'Support des jobs en arrière-plan'
                ]
            ],
            'react' => [
                'name' => 'React',
                'icon' => '⚛️',
                'version' => '16.8+',
                'node_version' => '14+',
                'installation' => [
                    'npm' => 'npm install @error-explorer/react-error-reporter',
                    'yarn' => 'yarn add @error-explorer/react-error-reporter'
                ],
                'features' => [
                    'Error Boundaries React',
                    'Hooks personnalisés',
                    'Support TypeScript',
                    'Breadcrumbs automatiques',
                    'Capture des erreurs de rendu'
                ],
                'examples' => [
                    'provider_setup' => '<ErrorReporterProvider config={config}><App /></ErrorReporterProvider>',
                    'hook_usage' => 'const { reportError } = useErrorReporter();',
                    'error_boundary' => '<ErrorBoundary fallback={<ErrorUI />}>'
                ]
            ],
        ];

        return $frameworksData[$framework] ?? null;
    }
}
