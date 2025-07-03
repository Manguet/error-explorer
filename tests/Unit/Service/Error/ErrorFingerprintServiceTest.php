<?php

namespace App\Tests\Unit\Service\Error;

use App\Service\Error\ErrorFingerprintService;
use App\ValueObject\Error\WebhookData;
use App\ValueObject\Error\CoreErrorData;
use App\ValueObject\Error\RequestContext;
use App\ValueObject\Error\ServerContext;
use App\ValueObject\Error\ErrorContext;
use Psr\Log\LoggerInterface;
use PHPUnit\Framework\TestCase;

class ErrorFingerprintServiceTest extends TestCase
{
    private ErrorFingerprintService $service;
    private LoggerInterface $logger;

    protected function setUp(): void
    {
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->service = new ErrorFingerprintService($this->logger);
    }

    public function testGenerateFingerprintWithProvidedFingerprint(): void
    {
        $providedFingerprint = hash('sha256', 'test-fingerprint');
        $coreData = new CoreErrorData(
            message: 'Test error',
            exceptionClass: 'RuntimeException',
            file: '/app/test.php',
            line: 42,
            project: 'test-project',
            environment: 'production',
            httpStatus: 500,
            stackTrace: '',
            timestamp: new \DateTime(),
            errorType: 'exception',
            fingerprint: $providedFingerprint
        );

        $webhookData = new WebhookData(
            requestContext: null,
            serverContext: null,
            errorContext: null,
            coreData: $coreData
        );

        $result = $this->service->generateFingerprint($webhookData);
        $this->assertEquals($providedFingerprint, $result);
    }

    public function testGenerateFingerprintStandardStrategy(): void
    {
        $coreData = new CoreErrorData(
            message: 'Test error message',
            exceptionClass: 'RuntimeException',
            file: '/app/src/Controller/TestController.php',
            line: 42,
            project: 'test-project',
            environment: 'production',
            httpStatus: null,
            stackTrace: '',
            timestamp: new \DateTime(),
            errorType: 'exception',
            fingerprint: null
        );

        $webhookData = new WebhookData(
            requestContext: null,
            serverContext: null,
            errorContext: null,
            coreData: $coreData
        );

        $result = $this->service->generateFingerprint($webhookData);
        
        // Should be a valid SHA256 hash
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $result);
        
        // Same input should produce same output
        $result2 = $this->service->generateFingerprint($webhookData);
        $this->assertEquals($result, $result2);
    }

    public function testGenerateFingerprintHttpStrategy(): void
    {
        $coreData = new CoreErrorData(
            message: 'HTTP error',
            exceptionClass: 'HttpException',
            file: '/app/src/Controller/ApiController.php',
            line: 123,
            project: 'api-project',
            environment: 'production',
            httpStatus: 404,
            stackTrace: '',
            timestamp: new \DateTime(),
            errorType: 'exception',
            fingerprint: null
        );

        $requestContext = new RequestContext(
            url: 'https://api.example.com/users/123',
            method: 'GET',
            headers: [],
            query: [],
            body: null,
            ip: '192.168.1.1',
            userAgent: 'TestClient/1.0',
            route: null,
            parameters: [],
            cookies: [],
            files: []
        );

        $webhookData = new WebhookData(
            requestContext: $requestContext,
            serverContext: null,
            errorContext: null,
            coreData: $coreData
        );

        $result = $this->service->generateFingerprint($webhookData);
        
        // Should be a valid SHA256 hash
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $result);
        
        // Should be different from standard strategy
        $standardCoreData = new CoreErrorData(
            message: 'HTTP error',
            exceptionClass: 'HttpException',
            file: '/app/src/Controller/ApiController.php',
            line: 123,
            project: 'api-project',
            environment: 'production',
            httpStatus: null, // No HTTP status = standard strategy
            stackTrace: '',
            timestamp: new \DateTime(),
            errorType: 'exception',
            fingerprint: null
        );

        $standardWebhookData = new WebhookData(
            requestContext: null,
            serverContext: null,
            errorContext: null,
            coreData: $standardCoreData
        );

        $standardResult = $this->service->generateFingerprint($standardWebhookData);
        $this->assertNotEquals($result, $standardResult);
    }

    public function testGenerateFingerprintDatabaseStrategy(): void
    {
        $coreData = new CoreErrorData(
            message: 'SQLSTATE[42S02]: Base table or view not found',
            exceptionClass: 'Doctrine\DBAL\Exception\TableNotFoundException',
            file: '/app/src/Repository/UserRepository.php',
            line: 45,
            project: 'db-project',
            environment: 'production',
            httpStatus: null,
            stackTrace: '',
            timestamp: new \DateTime(),
            errorType: 'exception',
            fingerprint: null
        );

        $webhookData = new WebhookData(
            requestContext: null,
            serverContext: null,
            errorContext: null,
            coreData: $coreData
        );

        $result = $this->service->generateFingerprint($webhookData);
        
        // Should be a valid SHA256 hash
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $result);
    }

    public function testGenerateFingerprintNetworkStrategy(): void
    {
        $coreData = new CoreErrorData(
            message: 'cURL error 28: Connection timeout after 30 seconds',
            exceptionClass: 'GuzzleHttp\Exception\ConnectException',
            file: '/app/src/Service/ApiClient.php',
            line: 78,
            project: 'client-project',
            environment: 'production',
            httpStatus: null,
            stackTrace: '',
            timestamp: new \DateTime(),
            errorType: 'exception',
            fingerprint: null
        );

        $webhookData = new WebhookData(
            requestContext: null,
            serverContext: null,
            errorContext: null,
            coreData: $coreData
        );

        $result = $this->service->generateFingerprint($webhookData);
        
        // Should be a valid SHA256 hash
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $result);
    }

    public function testGenerateFingerprintFatalStrategy(): void
    {
        $coreData = new CoreErrorData(
            message: 'Fatal error: Allowed memory size of 134217728 bytes exhausted',
            exceptionClass: 'FatalErrorException',
            file: '/app/src/Service/DataProcessor.php',
            line: 156,
            project: 'memory-project',
            environment: 'production',
            httpStatus: null,
            stackTrace: '',
            timestamp: new \DateTime(),
            errorType: 'error',
            fingerprint: null
        );

        $webhookData = new WebhookData(
            requestContext: null,
            serverContext: null,
            errorContext: null,
            coreData: $coreData
        );

        $result = $this->service->generateFingerprint($webhookData);
        
        // Should be a valid SHA256 hash
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $result);
    }

    public function testGenerateFingerprintStackTraceStrategy(): void
    {
        $stackTrace = "#0 /app/src/Controller/TestController.php(42): TestService->method()\n" .
                     "#1 /app/src/Controller/TestController.php(25): TestController->action()\n" .
                     "#2 /app/vendor/symfony/http-kernel/HttpKernel.php(158): TestController->index()\n" .
                     "#3 /app/vendor/symfony/http-kernel/HttpKernel.php(74): HttpKernel->handleRaw()\n" .
                     "#4 /app/vendor/symfony/http-kernel/Kernel.php(201): HttpKernel->handle()\n" .
                     "#5 /app/public/index.php(25): Kernel->handle()\n" .
                     "#6 {main}";

        $coreData = new CoreErrorData(
            message: 'Complex error with long stack trace',
            exceptionClass: 'RuntimeException',
            file: '/app/src/Controller/TestController.php',
            line: 42,
            project: 'stack-project',
            environment: 'production',
            httpStatus: null,
            stackTrace: $stackTrace,
            timestamp: new \DateTime(),
            errorType: 'exception',
            fingerprint: null
        );

        $webhookData = new WebhookData(
            requestContext: null,
            serverContext: null,
            errorContext: null,
            coreData: $coreData
        );

        $result = $this->service->generateFingerprint($webhookData);
        
        // Should be a valid SHA256 hash
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $result);
    }

    public function testFingerprintConsistency(): void
    {
        $coreData = new CoreErrorData(
            message: 'Test error with ID 12345',
            exceptionClass: 'RuntimeException',
            file: '/app/src/Controller/TestController.php',
            line: 42,
            project: 'test-project',
            environment: 'production',
            httpStatus: null,
            stackTrace: '',
            timestamp: new \DateTime(),
            errorType: 'exception',
            fingerprint: null
        );

        $webhookData = new WebhookData(
            requestContext: null,
            serverContext: null,
            errorContext: null,
            coreData: $coreData
        );

        // Similar error with different ID should produce same fingerprint
        $coreData2 = new CoreErrorData(
            message: 'Test error with ID 67890',
            exceptionClass: 'RuntimeException',
            file: '/app/src/Controller/TestController.php',
            line: 42,
            project: 'test-project',
            environment: 'production',
            httpStatus: null,
            stackTrace: '',
            timestamp: new \DateTime(),
            errorType: 'exception',
            fingerprint: null
        );

        $webhookData2 = new WebhookData(
            requestContext: null,
            serverContext: null,
            errorContext: null,
            coreData: $coreData2
        );

        $result1 = $this->service->generateFingerprint($webhookData);
        $result2 = $this->service->generateFingerprint($webhookData2);
        
        // Should produce same fingerprint despite different IDs
        $this->assertEquals($result1, $result2);
    }

    public function testGetStrategyStats(): void
    {
        $stats = $this->service->getStrategyStats();
        
        $this->assertIsArray($stats);
        $this->assertArrayHasKey('strategies_used', $stats);
        $this->assertArrayHasKey('version', $stats);
        $this->assertEquals('v2', $stats['version']);
        $this->assertContains('http', $stats['strategies_used']);
        $this->assertContains('database', $stats['strategies_used']);
        $this->assertContains('network', $stats['strategies_used']);
        $this->assertContains('fatal', $stats['strategies_used']);
        $this->assertContains('stacktrace', $stats['strategies_used']);
        $this->assertContains('standard', $stats['strategies_used']);
    }
}