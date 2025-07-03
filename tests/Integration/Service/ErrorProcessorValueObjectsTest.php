<?php

namespace App\Tests\Integration\Service;

use App\Service\ErrorProcessor;
use App\Service\Error\WebhookDataExtractor;
use App\Entity\Project;
use App\Entity\User;
use App\Entity\ErrorGroup;
use App\Entity\ErrorOccurrence;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Test d'intégration pour ErrorProcessor avec Value Objects
 */
class ErrorProcessorValueObjectsTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private ErrorProcessor $errorProcessor;
    private WebhookDataExtractor $webhookDataExtractor;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        $container = static::getContainer();

        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->errorProcessor = $container->get(ErrorProcessor::class);
        $this->webhookDataExtractor = $container->get(WebhookDataExtractor::class);

        // Nettoyer la base de données
        $this->entityManager->beginTransaction();
    }

    protected function tearDown(): void
    {
        $this->entityManager->rollback();
        parent::tearDown();
    }

    public function testProcessErrorWithCompletePayload(): void
    {
        // Créer un projet de test
        $user = new User();
        $user->setEmail('test@example.com')
             ->setPassword('password')
             ->setIsVerified(true);

        $project = new Project();
        $project->setName('Test Project')
                ->setSlug('test-project')
                ->setWebhookToken('test-token-123')
                ->setOwner($user);

        $this->entityManager->persist($user);
        $this->entityManager->persist($project);
        $this->entityManager->flush();

        // Payload webhook complet avec tous les Value Objects
        $payload = [
            'message' => 'Test error message',
            'exception_class' => 'RuntimeException',
            'file' => '/app/src/Controller/TestController.php',
            'line' => 42,
            'project' => 'original-project-name', // Sera override par le slug du projet
            'environment' => 'production',
            'http_status' => 500,
            'stack_trace' => "#0 /app/src/Controller/TestController.php(42): Test::method()\n#1 {main}",
            'timestamp' => '2023-12-01T10:30:00Z',
            'request' => [
                'url' => 'https://api.example.com/test',
                'method' => 'POST',
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer token123'
                ],
                'query' => ['param' => 'value'],
                'body' => ['data' => 'test'],
                'ip' => '192.168.1.100',
                'user_agent' => 'TestClient/1.0'
            ],
            'server' => [
                'php_version' => '8.2.0',
                'hostname' => 'web-server-01',
                'memory_usage' => 12582912,
                'execution_time' => 0.156,
                'pid' => 1234
            ],
            'context' => [
                'user' => [
                    'id' => 123,
                    'email' => 'user@example.com',
                    'username' => 'testuser'
                ],
                'breadcrumbs' => [
                    [
                        'message' => 'User logged in',
                        'category' => 'auth',
                        'level' => 'info',
                        'timestamp' => '2023-12-01T10:29:00Z',
                        'data' => ['user_id' => 123]
                    ]
                ],
                'level' => 'error',
                'custom_field' => 'custom_value'
            ]
        ];

        // Traitement de l'erreur
        $result = $this->errorProcessor->processError($payload, 'test-token-123', $project);

        // Vérifications
        $this->assertIsArray($result);
        $this->assertArrayHasKey('error_group_id', $result);
        $this->assertArrayHasKey('fingerprint', $result);

        // Vérifier que l'ErrorGroup a été créé
        $errorGroup = $this->entityManager->find(ErrorGroup::class, $result['error_group_id']);
        $this->assertInstanceOf(ErrorGroup::class, $errorGroup);
        $this->assertEquals('Test error message', $errorGroup->getMessage());
        $this->assertEquals('RuntimeException', $errorGroup->getExceptionClass());
        $this->assertEquals('/app/src/Controller/TestController.php', $errorGroup->getFile());
        $this->assertEquals(42, $errorGroup->getLine());
        $this->assertEquals('test-project', $errorGroup->getProject()); // Slug utilisé
        $this->assertEquals('production', $errorGroup->getEnvironment());
        $this->assertEquals(500, $errorGroup->getHttpStatusCode());
        $this->assertEquals('exception', $errorGroup->getErrorType());

        // Vérifier que l'ErrorOccurrence a été créée
        $errorOccurrences = $this->entityManager->getRepository(ErrorOccurrence::class)
            ->findBy(['errorGroup' => $errorGroup]);
        
        $this->assertCount(1, $errorOccurrences);
        $occurrence = $errorOccurrences[0];

        // Vérifier les données indexées
        $this->assertEquals('https://api.example.com/test', $occurrence->getUrl());
        $this->assertEquals('POST', $occurrence->getHttpMethod());
        $this->assertEquals('192.168.1.100', $occurrence->getIpAddress());
        $this->assertEquals('TestClient/1.0', $occurrence->getUserAgent());
        $this->assertEquals('123', $occurrence->getUserId());
        $this->assertEquals(12582912, $occurrence->getMemoryUsage());
        $this->assertEquals(0.156, $occurrence->getExecutionTime());

        // Vérifier les contextes JSON
        $requestData = $occurrence->getRequest();
        $this->assertIsArray($requestData);
        $this->assertEquals('https://api.example.com/test', $requestData['url']);
        $this->assertEquals('POST', $requestData['method']);
        
        $serverData = $occurrence->getServer();
        $this->assertIsArray($serverData);
        $this->assertEquals('8.2.0', $serverData['php_version']);
        $this->assertEquals('web-server-01', $serverData['hostname']);
        
        $contextData = $occurrence->getContext();
        $this->assertIsArray($contextData);
        $this->assertArrayHasKey('user', $contextData);
        $this->assertEquals('123', $contextData['user']['id']);
        $this->assertArrayHasKey('breadcrumbs', $contextData);
        $this->assertCount(1, $contextData['breadcrumbs']);
    }

    public function testProcessErrorWithMinimalPayload(): void
    {
        // Créer un projet de test
        $user = new User();
        $user->setEmail('test2@example.com')
             ->setPassword('password')
             ->setIsVerified(true);

        $project = new Project();
        $project->setName('Test Project 2')
                ->setSlug('test-project-2')
                ->setWebhookToken('test-token-456')
                ->setOwner($user);

        $this->entityManager->persist($user);
        $this->entityManager->persist($project);
        $this->entityManager->flush();

        // Payload minimal
        $payload = [
            'message' => 'Minimal error',
            'exception_class' => 'Exception',
            'file' => '/app/test.php',
            'line' => 1,
            'project' => 'minimal-project'
        ];

        // Traitement de l'erreur
        $result = $this->errorProcessor->processError($payload, 'test-token-456', $project);

        // Vérifications
        $this->assertIsArray($result);
        $this->assertArrayHasKey('error_group_id', $result);

        $errorGroup = $this->entityManager->find(ErrorGroup::class, $result['error_group_id']);
        $this->assertInstanceOf(ErrorGroup::class, $errorGroup);
        $this->assertEquals('Minimal error', $errorGroup->getMessage());
        $this->assertEquals('test-project-2', $errorGroup->getProject());
        $this->assertEquals('unknown', $errorGroup->getEnvironment()); // Valeur par défaut
        $this->assertNull($errorGroup->getHttpStatusCode());
    }

    public function testProcessErrorUpdatesExistingGroup(): void
    {
        // Créer un projet de test
        $user = new User();
        $user->setEmail('test3@example.com')
             ->setPassword('password')
             ->setIsVerified(true);

        $project = new Project();
        $project->setName('Test Project 3')
                ->setSlug('test-project-3')
                ->setWebhookToken('test-token-789')
                ->setOwner($user);

        $this->entityManager->persist($user);
        $this->entityManager->persist($project);
        $this->entityManager->flush();

        $payload = [
            'message' => 'Duplicate error',
            'exception_class' => 'DuplicateException',
            'file' => '/app/duplicate.php',
            'line' => 10,
            'project' => 'duplicate-project',
            'environment' => 'development'
        ];

        // Premier traitement
        $result1 = $this->errorProcessor->processError($payload, 'test-token-789', $project);
        $this->entityManager->clear(); // Clear pour simuler une nouvelle requête

        // Deuxième traitement (même erreur)
        $result2 = $this->errorProcessor->processError($payload, 'test-token-789', $project);

        // Vérifier que c'est le même groupe d'erreur
        $this->assertEquals($result1['error_group_id'], $result2['error_group_id']);
        $this->assertEquals($result1['fingerprint'], $result2['fingerprint']);

        // Vérifier que le compteur d'occurrences a été incrémenté
        $errorGroup = $this->entityManager->find(ErrorGroup::class, $result1['error_group_id']);
        $this->assertEquals(2, $errorGroup->getOccurrenceCount());

        // Vérifier qu'il y a 2 occurrences
        $occurrences = $this->entityManager->getRepository(ErrorOccurrence::class)
            ->findBy(['errorGroup' => $errorGroup]);
        $this->assertCount(2, $occurrences);
    }

    public function testWebhookDataExtractorIntegration(): void
    {
        // Test que WebhookDataExtractor fonctionne correctement avec des données réelles
        $payload = [
            'message' => 'Integration test error',
            'exception_class' => 'IntegrationException',
            'file' => '/app/integration.php',
            'line' => 100,
            'project' => 'integration-test',
            'environment' => 'staging',
            'request' => [
                'url' => 'https://integration.test/api',
                'method' => 'GET',
                'ip' => '10.0.0.1'
            ],
            'server' => [
                'php_version' => '8.1.0',
                'memory_usage' => 8388608
            ],
            'context' => [
                'user' => [
                    'id' => 'user_123',
                    'email' => 'integration@test.com'
                ],
                'custom_data' => 'test_value'
            ]
        ];

        $webhookData = $this->webhookDataExtractor->extractWebhookData($payload);

        // Vérifier les Value Objects
        $this->assertTrue($webhookData->isValid());
        $this->assertEquals('Integration test error', $webhookData->coreData->message);
        $this->assertEquals('IntegrationException', $webhookData->coreData->exceptionClass);
        $this->assertEquals('staging', $webhookData->coreData->environment);

        $this->assertNotNull($webhookData->requestContext);
        $this->assertEquals('https://integration.test/api', $webhookData->requestContext->url);
        $this->assertEquals('GET', $webhookData->requestContext->method);

        $this->assertNotNull($webhookData->serverContext);
        $this->assertEquals('8.1.0', $webhookData->serverContext->phpVersion);
        $this->assertEquals(8388608, $webhookData->serverContext->memoryUsage);

        $this->assertNotNull($webhookData->errorContext);
        $this->assertTrue($webhookData->errorContext->hasUserContext());
        $this->assertEquals('user_123', $webhookData->errorContext->userContext->id);

        // Test de la conversion backward compatibility
        $legacyArray = $webhookData->toLegacyArray();
        $this->assertIsArray($legacyArray);
        $this->assertEquals('Integration test error', $legacyArray['message']);
        $this->assertEquals('https://integration.test/api', $legacyArray['url']);
        $this->assertEquals('user_123', $legacyArray['user_id']);
        $this->assertEquals(8388608, $legacyArray['memory_usage']);
    }
}