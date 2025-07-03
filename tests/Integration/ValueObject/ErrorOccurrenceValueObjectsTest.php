<?php

namespace App\Tests\Integration\ValueObject;

use App\ValueObject\Error\RequestContext;
use App\ValueObject\Error\ServerContext;
use App\ValueObject\Error\ErrorContext;
use App\ValueObject\Error\UserContext;
use App\ValueObject\Error\Breadcrumb;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Test d'intégration pour vérifier la compatibilité avec les payloads SDK
 */
class ErrorOccurrenceValueObjectsTest extends KernelTestCase
{
    public function testPhpSdkPayloadCompatibility(): void
    {
        // Payload type envoyé par le PHP SDK
        $phpPayload = [
            'request' => [
                'url' => 'https://app.example.com/api/users',
                'method' => 'POST',
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer token123',
                    'User-Agent' => 'Mozilla/5.0 (Symfony HttpClient)',
                ],
                'query' => ['limit' => 10, 'offset' => 0],
                'body' => ['name' => 'John Doe', 'email' => 'john@example.com'],
                'ip' => '192.168.1.100',
                'user_agent' => 'Mozilla/5.0 (Symfony HttpClient)',
                'route' => 'api_users_create',
                'parameters' => ['version' => 'v1'],
                'cookies' => ['session_id' => 'abc123def456'],
                'files' => ['avatar' => 'profile.jpg']
            ],
            'server' => [
                'php_version' => '8.2.12',
                'server_software' => 'nginx/1.20.1',
                'hostname' => 'web-server-01',
                'memory_usage' => 12582912,
                'memory_limit' => '256M',
                'memory_peak' => 15728640,
                'pid' => 1234,
                'extensions' => ['json', 'pdo', 'curl'],
                'execution_time' => 0.156
            ],
            'context' => [
                'user' => [
                    'id' => 123,
                    'email' => 'john@example.com',
                    'username' => 'johndoe',
                    'name' => 'John Doe',
                    'role' => 'admin'
                ],
                'breadcrumbs' => [
                    [
                        'message' => 'User authentication successful',
                        'category' => 'auth',
                        'level' => 'info',
                        'timestamp' => '2023-12-01T10:30:00Z',
                        'data' => ['user_id' => 123]
                    ],
                    [
                        'message' => 'Database query executed',
                        'category' => 'database',
                        'level' => 'debug',
                        'timestamp' => '2023-12-01T10:30:01Z',
                        'data' => ['query' => 'SELECT * FROM users', 'duration' => 15.2]
                    ]
                ],
                'level' => 'error',
                'custom_field' => 'custom_value',
                'environment' => 'production'
            ]
        ];

        // Test RequestContext
        $requestContext = RequestContext::fromArray($phpPayload['request']);
        $this->assertEquals('https://app.example.com/api/users', $requestContext->url);
        $this->assertEquals('POST', $requestContext->method);
        $this->assertEquals('192.168.1.100', $requestContext->ip);
        $this->assertEquals('api_users_create', $requestContext->route);
        $this->assertTrue($requestContext->isValid());
        $this->assertTrue($requestContext->isSecure());
        $this->assertTrue($requestContext->hasBody());

        // Test backward compatibility
        $requestArray = $requestContext->toArray();
        $this->assertEquals('https://app.example.com/api/users', $requestArray['url']);
        $this->assertEquals('POST', $requestArray['method']);

        // Test ServerContext
        $serverContext = ServerContext::fromArray($phpPayload['server']);
        $this->assertEquals('8.2.12', $serverContext->phpVersion);
        $this->assertEquals('web-server-01', $serverContext->hostname);
        $this->assertEquals(12582912, $serverContext->memoryUsage);
        $this->assertEquals('PHP', $serverContext->getRuntimeType());
        $this->assertEquals('8.2.12', $serverContext->getRuntimeVersion());
        $this->assertTrue($serverContext->hasPerformanceMetrics());

        // Test ErrorContext avec UserContext et Breadcrumbs
        $errorContext = ErrorContext::fromArray($phpPayload['context']);
        $this->assertEquals('error', $errorContext->level);
        $this->assertEquals('custom_value', $errorContext->getCustomValue('custom_field'));
        $this->assertTrue($errorContext->hasUserContext());
        $this->assertTrue($errorContext->hasBreadcrumbs());
        $this->assertEquals(2, $errorContext->getBreadcrumbCount());

        // Test UserContext
        $userContext = $errorContext->userContext;
        $this->assertEquals('123', $userContext->id);
        $this->assertEquals('john@example.com', $userContext->email);
        $this->assertEquals('John Doe', $userContext->getDisplayName());
        $this->assertTrue($userContext->isValid());

        // Test Breadcrumbs
        $breadcrumbs = $errorContext->breadcrumbs;
        $this->assertCount(2, $breadcrumbs);
        $this->assertEquals('User authentication successful', $breadcrumbs[0]->message);
        $this->assertEquals('auth', $breadcrumbs[0]->category);
        $this->assertEquals('info', $breadcrumbs[0]->level);
    }

    public function testNodeJsSdkPayloadCompatibility(): void
    {
        // Payload type envoyé par le Node.js SDK
        $nodePayload = [
            'request' => [
                'url' => 'http://localhost:3000/api/data',
                'method' => 'GET',
                'headers' => [
                    'accept' => 'application/json',
                    'user-agent' => 'axios/0.27.2'
                ],
                'ip' => '127.0.0.1',
                'user_agent' => 'axios/0.27.2'
            ],
            'server' => [
                'node_version' => '18.17.0',
                'platform' => 'linux',
                'arch' => 'x64',
                'hostname' => 'node-server',
                'memory_usage' => 25165824,
                'uptime' => 3600.5,
                'pid' => 5678
            ],
            'context' => [
                'user' => [
                    'id' => 'user_456',
                    'email' => 'jane@example.com'
                ],
                'level' => 'warning'
            ]
        ];

        $requestContext = RequestContext::fromArray($nodePayload['request']);
        $this->assertEquals('http://localhost:3000/api/data', $requestContext->url);
        $this->assertFalse($requestContext->isSecure());

        $serverContext = ServerContext::fromArray($nodePayload['server']);
        $this->assertEquals('18.17.0', $serverContext->nodeVersion);
        $this->assertEquals('Node.js', $serverContext->getRuntimeType());
        $this->assertEquals('linux', $serverContext->platform);
        $this->assertEquals(3600.5, $serverContext->uptime);

        $errorContext = ErrorContext::fromArray($nodePayload['context']);
        $this->assertEquals('warning', $errorContext->level);
        $this->assertTrue($errorContext->hasUserContext());
        $this->assertEquals('user_456', $errorContext->userContext->id);
    }

    public function testPythonSdkPayloadCompatibility(): void
    {
        // Payload type envoyé par le Python SDK
        $pythonPayload = [
            'request' => [
                'url' => 'https://api.example.com/webhooks',
                'method' => 'POST',
                'ip' => '203.0.113.1'
            ],
            'server' => [
                'python_version' => '3.11.5',
                'platform' => 'Linux-5.15.0-72-generic-x86_64-with-glibc2.35',
                'hostname' => 'python-worker',
                'memory_usage' => 8388608,
                'pid' => 9999,
                'thread_id' => 140123456789
            ],
            'context' => [
                'user' => [
                    'id' => 789,
                    'username' => 'pythondev',
                    'department' => 'engineering'
                ]
            ]
        ];

        $serverContext = ServerContext::fromArray($pythonPayload['server']);
        $this->assertEquals('3.11.5', $serverContext->pythonVersion);
        $this->assertEquals('Python', $serverContext->getRuntimeType());
        $this->assertEquals(140123456789, $serverContext->threadId);

        $errorContext = ErrorContext::fromArray($pythonPayload['context']);
        $userContext = $errorContext->userContext;
        $this->assertEquals('789', $userContext->id);
        $this->assertEquals('pythondev', $userContext->username);
        $this->assertEquals('engineering', $userContext->getExtra('department'));
    }

    public function testBackwardCompatibilityToArray(): void
    {
        // Test que les Value Objects peuvent être reconvertis en arrays
        // compatibles avec le code existant
        
        $originalData = [
            'url' => 'https://example.com',
            'method' => 'POST',
            'ip' => '192.168.1.1'
        ];

        $requestContext = RequestContext::fromArray($originalData);
        $backToArray = $requestContext->toArray();

        // Vérifier que les données essentielles sont préservées
        $this->assertEquals($originalData['url'], $backToArray['url']);
        $this->assertEquals($originalData['method'], $backToArray['method']);
        $this->assertEquals($originalData['ip'], $backToArray['ip']);
    }

    public function testMixedPayloadHandling(): void
    {
        // Test avec un payload mixte qui combine des données de différents SDKs
        $mixedPayload = [
            'request' => [
                'url' => 'https://mixed.example.com',
                'method' => 'PUT',
                'headers' => ['Authorization' => 'Bearer token'],
                'route' => 'api_update' // Symfony/Laravel specifique
            ],
            'server' => [
                'php_version' => '8.2.0',
                'node_version' => '18.0.0', // Les deux présents
                'hostname' => 'mixed-server',
                'memory_usage' => 16777216
            ],
            'context' => [
                'user' => [
                    'id' => 'mixed_123',
                    'email' => 'mixed@example.com',
                    'custom_field' => 'value'
                ],
                'environment' => 'staging',
                'deployment_version' => '1.2.3'
            ]
        ];

        $requestContext = RequestContext::fromArray($mixedPayload['request']);
        $this->assertEquals('api_update', $requestContext->route);

        $serverContext = ServerContext::fromArray($mixedPayload['server']);
        // PHP version prend la priorité dans getRuntimeType()
        $this->assertEquals('PHP', $serverContext->getRuntimeType());
        $this->assertEquals('8.2.0', $serverContext->getRuntimeVersion());

        $errorContext = ErrorContext::fromArray($mixedPayload['context']);
        $this->assertEquals('staging', $errorContext->getCustomValue('environment'));
        $this->assertEquals('1.2.3', $errorContext->getCustomValue('deployment_version'));
    }
}