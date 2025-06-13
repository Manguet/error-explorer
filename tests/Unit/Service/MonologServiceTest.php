<?php

namespace App\Tests\Unit\Service;

use App\Service\Logs\MonologService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Tests unitaires pour MonologService
 */
class MonologServiceTest extends TestCase
{
    private MonologService $monologService;
    private LoggerInterface $securityLogger;
    private LoggerInterface $businessLogger;
    private LoggerInterface $performanceLogger;

    protected function setUp(): void
    {
        $mainLogger = $this->createMock(LoggerInterface::class);
        $this->securityLogger = $this->createMock(LoggerInterface::class);
        $this->businessLogger = $this->createMock(LoggerInterface::class);
        $this->performanceLogger = $this->createMock(LoggerInterface::class);
        $deprecationLogger = $this->createMock(LoggerInterface::class);

        $this->monologService = new MonologService(
            $mainLogger,
            $this->securityLogger,
            $this->businessLogger,
            $this->performanceLogger,
            $deprecationLogger
        );
    }

    public function testCaptureWithDefaultParameters(): void
    {
        $message = 'Test message';

        $this->businessLogger
            ->expects($this->once())
            ->method('info')
            ->with($message, []);

        $this->monologService->capture($message);
    }

    public function testCaptureWithSecurityChannel(): void
    {
        $message = 'Security event';
        $context = ['user_id' => 123];

        $this->securityLogger
            ->expects($this->once())
            ->method('warning')
            ->with($message, $context);

        $this->monologService->capture(
            $message,
            MonologService::SECURITY,
            MonologService::WARNING,
            $context
        );
    }

    public function testSecurityMethod(): void
    {
        $message = 'Security alert';
        $context = ['ip' => '192.168.1.1'];

        $this->securityLogger
            ->expects($this->once())
            ->method('warning')
            ->with($message, $context);

        $this->monologService->security($message, MonologService::WARNING, $context);
    }

    public function testLoginAttemptSuccess(): void
    {
        $username = 'test@example.com';
        $ip = '192.168.1.1';

        $this->securityLogger
            ->expects($this->once())
            ->method('info')
            ->with(
                "Successful login for user: {$username}",
                [
                    'username' => $username,
                    'success' => true,
                    'ip' => $ip
                ]
            );

        $this->monologService->loginAttempt($username, true, $ip);
    }

    public function testLoginAttemptFailure(): void
    {
        $username = 'test@example.com';
        $ip = '192.168.1.1';

        $this->securityLogger
            ->expects($this->once())
            ->method('warning')
            ->with(
                "Failed login attempt for user: {$username}",
                [
                    'username' => $username,
                    'success' => false,
                    'ip' => $ip
                ]
            );

        $this->monologService->loginAttempt($username, false, $ip);
    }

    public function testSecurityEvent(): void
    {
        $event = 'brute_force_detected';
        $data = ['attempts' => 5];

        $this->securityLogger
            ->expects($this->once())
            ->method('warning')
            ->with(
                "Security event: {$event}",
                $data
            );

        $this->monologService->securityEvent($event, $data);
    }

    public function testBusinessEvent(): void
    {
        $event = 'user_registered';
        $data = ['user_id' => 123];

        $this->businessLogger
            ->expects($this->once())
            ->method('info')
            ->with(
                "Business event: {$event}",
                $data
            );

        $this->monologService->businessEvent($event, $data);
    }

    public function testUserAction(): void
    {
        $action = 'create_post';
        $user = 'john_doe';
        $data = ['post_id' => 456];

        $this->businessLogger
            ->expects($this->once())
            ->method('info')
            ->with(
                "User action: {$user} performed {$action}",
                $this->callback(function ($context) use ($user, $action, $data) {
                    return $context['user'] === $user
                        && $context['action'] === $action
                        && $context['post_id'] === 456
                        && isset($context['timestamp']);
                })
            );

        $this->monologService->userAction($action, $user, $data);
    }

    public function testPerformanceMetric(): void
    {
        $metric = 'page_load_time';
        $value = 250.5;
        $unit = 'ms';

        $this->performanceLogger
            ->expects($this->once())
            ->method('info')
            ->with(
                "Performance metric: {$metric} = {$value}{$unit}",
                $this->callback(function ($context) use ($metric, $value, $unit) {
                    return $context['metric'] === $metric
                        && $context['value'] === $value
                        && $context['unit'] === $unit
                        && isset($context['timestamp']);
                })
            );

        $this->monologService->performanceMetric($metric, $value, $unit);
    }

    public function testAccessDenied(): void
    {
        $resource = '/admin/users';
        $user = 'john_doe';

        $_SERVER['REMOTE_ADDR'] = '192.168.1.1';

        $this->securityLogger
            ->expects($this->once())
            ->method('error')
            ->with(
                "Access denied to resource: {$resource}",
                [
                    'resource' => $resource,
                    'user' => $user,
                    'ip' => '192.168.1.1'
                ]
            );

        $this->monologService->accessDenied($resource, $user);

        unset($_SERVER['REMOTE_ADDR']);
    }

    public function testSlowQuery(): void
    {
        $query = 'SELECT * FROM users WHERE name LIKE "%test%"';
        $executionTime = 2.5;

        $this->performanceLogger
            ->expects($this->once())
            ->method('warning')
            ->with(
                'Slow query detected',
                [
                    'query' => $query,
                    'execution_time' => $executionTime,
                    'threshold_exceeded' => true
                ]
            );

        $this->monologService->slowQuery($query, $executionTime);
    }

    /**
     * Test des constantes
     */
    public function testConstants(): void
    {
        $this->assertSame('security', MonologService::SECURITY);
        $this->assertSame('business', MonologService::BUSINESS);
        $this->assertSame('performance', MonologService::PERFORMANCE);
        $this->assertSame('deprecation', MonologService::DEPRECATION);
        $this->assertSame('info', MonologService::INFO);
        $this->assertSame('warning', MonologService::WARNING);
        $this->assertSame('error', MonologService::ERROR);
    }

    /**
     * Test avec niveau de log invalide (doit utiliser info par défaut)
     */
    public function testCaptureWithInvalidLevel(): void
    {
        $message = 'Test message';

        $this->businessLogger
            ->expects($this->once())
            ->method('info')
            ->with($message, []);

        $this->monologService->capture($message, MonologService::BUSINESS, 'invalid_level');
    }
}
