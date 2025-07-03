<?php

namespace App\Tests\Unit\Service\Error;

use App\Service\Error\WebhookDataExtractor;
use Psr\Log\LoggerInterface;
use PHPUnit\Framework\TestCase;

class WebhookDataExtractorTest extends TestCase
{
    private WebhookDataExtractor $extractor;
    private LoggerInterface $logger;

    protected function setUp(): void
    {
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->extractor = new WebhookDataExtractor($this->logger);
    }

    public function testExtractWebhookDataWithCompletePayload(): void
    {
        $payload = [
            'message' => 'Test error message',
            'exception_class' => 'RuntimeException',
            'file' => '/app/src/Controller/TestController.php',
            'line' => 42,
            'project' => 'test-project',
            'environment' => 'production',
            'http_status' => 500,
            'stack_trace' => "#0 /app/test.php(42): Test::method()\n#1 {main}",
            'timestamp' => '2023-12-01T10:30:00Z',
            'request' => [
                'url' => 'https://api.example.com/test',
                'method' => 'POST',
                'ip' => '192.168.1.1',
                'user_agent' => 'TestClient/1.0'
            ],
            'server' => [
                'php_version' => '8.2.0',
                'hostname' => 'web-server',
                'memory_usage' => 12582912,
                'execution_time' => 0.156
            ],
            'context' => [
                'user' => [
                    'id' => 123,
                    'email' => 'user@example.com'
                ],
                'level' => 'error',
                'custom_field' => 'custom_value'
            ]
        ];

        $webhookData = $this->extractor->extractWebhookData($payload);

        // Test core data
        $this->assertTrue($webhookData->isValid());
        $this->assertEquals('Test error message', $webhookData->coreData->message);
        $this->assertEquals('RuntimeException', $webhookData->coreData->exceptionClass);
        $this->assertEquals('src/Controller/TestController.php', $webhookData->coreData->file);
        $this->assertEquals(42, $webhookData->coreData->line);
        $this->assertEquals('test-project', $webhookData->coreData->project);
        $this->assertEquals('production', $webhookData->coreData->environment);
        $this->assertEquals(500, $webhookData->coreData->httpStatus);
        $this->assertEquals('exception', $webhookData->coreData->errorType);

        // Test request context
        $this->assertNotNull($webhookData->requestContext);
        $this->assertEquals('https://api.example.com/test', $webhookData->requestContext->url);
        $this->assertEquals('POST', $webhookData->requestContext->method);
        $this->assertEquals('192.168.1.1', $webhookData->requestContext->ip);

        // Test server context
        $this->assertNotNull($webhookData->serverContext);
        $this->assertEquals('8.2.0', $webhookData->serverContext->phpVersion);
        $this->assertEquals('web-server', $webhookData->serverContext->hostname);
        $this->assertEquals(12582912, $webhookData->serverContext->memoryUsage);

        // Test error context
        $this->assertNotNull($webhookData->errorContext);
        $this->assertTrue($webhookData->errorContext->hasUserContext());
        $this->assertEquals('123', $webhookData->errorContext->userContext->id);
        $this->assertEquals('custom_value', $webhookData->errorContext->getCustomValue('custom_field'));
    }

    public function testExtractWebhookDataWithMinimalPayload(): void
    {
        $payload = [
            'message' => 'Minimal error',
            'exception_class' => 'Exception',
            'file' => '/app/test.php',
            'line' => 1,
            'project' => 'minimal-project'
        ];

        $webhookData = $this->extractor->extractWebhookData($payload);

        $this->assertTrue($webhookData->isValid());
        $this->assertEquals('Minimal error', $webhookData->coreData->message);
        $this->assertEquals('unknown', $webhookData->coreData->environment);
        $this->assertNull($webhookData->coreData->httpStatus);
        $this->assertNull($webhookData->requestContext);
        $this->assertNull($webhookData->serverContext);
        $this->assertNull($webhookData->errorContext);
    }

    public function testExtractIndexedFields(): void
    {
        $payload = [
            'message' => 'Test error',
            'exception_class' => 'Exception',
            'file' => '/app/test.php',
            'line' => 1,
            'project' => 'test',
            'request' => [
                'url' => 'https://example.com',
                'method' => 'GET',
                'ip' => '127.0.0.1',
                'user_agent' => 'Browser/1.0'
            ],
            'server' => [
                'memory_usage' => 1048576,
                'execution_time' => 0.05
            ],
            'context' => [
                'user' => ['id' => 'user_123']
            ]
        ];

        $webhookData = $this->extractor->extractWebhookData($payload);
        $indexedFields = $this->extractor->extractIndexedFields($webhookData);

        $this->assertEquals('https://example.com', $indexedFields['url']);
        $this->assertEquals('GET', $indexedFields['http_method']);
        $this->assertEquals('127.0.0.1', $indexedFields['ip_address']);
        $this->assertEquals('Browser/1.0', $indexedFields['user_agent']);
        $this->assertEquals(1048576, $indexedFields['memory_usage']);
        $this->assertEquals(0.05, $indexedFields['execution_time']);
        $this->assertEquals('user_123', $indexedFields['user_id']);
    }

    public function testValidateWebhookData(): void
    {
        // Test valid data
        $validPayload = [
            'message' => 'Valid error',
            'exception_class' => 'Exception',
            'file' => '/app/test.php',
            'line' => 1,
            'project' => 'test'
        ];

        $validWebhookData = $this->extractor->extractWebhookData($validPayload);
        $errors = $this->extractor->validateWebhookData($validWebhookData);
        $this->assertEmpty($errors);

        // Test invalid data
        $invalidPayload = [
            'message' => '',
            'exception_class' => '',
            'file' => '',
            'line' => 'invalid',
            'project' => 'test'
        ];

        $invalidWebhookData = $this->extractor->extractWebhookData($invalidPayload);
        $errors = $this->extractor->validateWebhookData($invalidWebhookData);
        $this->assertNotEmpty($errors);
        $this->assertContains('Message is required', $errors);
        $this->assertContains('File is required', $errors);
        
        // Vérifier que line invalide devient 0
        $this->assertEquals(0, $invalidWebhookData->coreData->line);
    }

    public function testErrorTypeDetection(): void
    {
        $testCases = [
            ['RuntimeError', 'error'],
            ['Warning', 'warning'],
            ['Notice', 'notice'],
            ['RuntimeException', 'exception'],
            ['CustomClass', 'exception'] // default
        ];

        foreach ($testCases as [$exceptionClass, $expectedType]) {
            $payload = [
                'message' => 'Test',
                'exception_class' => $exceptionClass,
                'file' => '/test.php',
                'line' => 1,
                'project' => 'test'
            ];

            $webhookData = $this->extractor->extractWebhookData($payload);
            $this->assertEquals($expectedType, $webhookData->coreData->errorType);
        }
    }

    public function testTimestampParsing(): void
    {
        // Test valid timestamp
        $payload = [
            'message' => 'Test',
            'exception_class' => 'Exception',
            'file' => '/test.php',
            'line' => 1,
            'project' => 'test',
            'timestamp' => '2023-12-01T10:30:00Z'
        ];

        $webhookData = $this->extractor->extractWebhookData($payload);
        $this->assertEquals('2023-12-01 10:30:00', $webhookData->coreData->timestamp->format('Y-m-d H:i:s'));

        // Test invalid timestamp (should use current time)
        $payload['timestamp'] = 'invalid-timestamp';
        $webhookData = $this->extractor->extractWebhookData($payload);
        $this->assertInstanceOf(\DateTimeInterface::class, $webhookData->coreData->timestamp);

        // Test future timestamp (should be adjusted to now)
        $futureDate = (new \DateTime())->add(new \DateInterval('PT2H'))->format('c');
        $payload['timestamp'] = $futureDate;
        
        $this->logger->expects($this->once())
                     ->method('warning')
                     ->with($this->stringContains('Timestamp futur détecté'));
                     
        $webhookData = $this->extractor->extractWebhookData($payload);
        $this->assertInstanceOf(\DateTimeInterface::class, $webhookData->coreData->timestamp);
    }

    public function testHttpStatusValidation(): void
    {
        // Test valid HTTP status
        $payload = [
            'message' => 'Test',
            'exception_class' => 'Exception',
            'file' => '/test.php',
            'line' => 1,
            'project' => 'test',
            'http_status' => 500
        ];

        $webhookData = $this->extractor->extractWebhookData($payload);
        $this->assertEquals(500, $webhookData->coreData->httpStatus);

        // Test invalid HTTP status
        $payload['http_status'] = 999;
        
        $this->logger->expects($this->once())
                     ->method('warning')
                     ->with($this->stringContains('Code HTTP invalide'));

        $webhookData = $this->extractor->extractWebhookData($payload);
        $this->assertNull($webhookData->coreData->httpStatus);
    }

    public function testMessageTruncation(): void
    {
        $longMessage = str_repeat('a', 2001);
        $payload = [
            'message' => $longMessage,
            'exception_class' => 'Exception',
            'file' => '/test.php',
            'line' => 1,
            'project' => 'test'
        ];

        $this->logger->expects($this->once())
                     ->method('info')
                     ->with($this->stringContains('Message tronqué'));

        $webhookData = $this->extractor->extractWebhookData($payload);
        $this->assertEquals(2000, strlen($webhookData->coreData->message));
        $this->assertStringEndsWith('...', $webhookData->coreData->message);
    }
}