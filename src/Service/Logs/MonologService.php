<?php

namespace App\Service\Logs;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class MonologService
{
    public const SECURITY = 'security';
    public const BUSINESS = 'business';
    public const PERFORMANCE = 'performance';
    public const DEPRECATION = 'deprecation';

    public const DEBUG = 'debug';
    public const INFO = 'info';
    public const NOTICE = 'notice';
    public const WARNING = 'warning';
    public const ERROR = 'error';
    public const CRITICAL = 'critical';
    public const ALERT = 'alert';
    public const EMERGENCY = 'emergency';

    public function __construct(
        private readonly LoggerInterface $logger,
        #[Autowire(service: 'monolog.logger.security')]
        private readonly LoggerInterface $securityLogger,
        #[Autowire(service: 'monolog.logger.business')]
        private readonly LoggerInterface $businessLogger,
        #[Autowire(service: 'monolog.logger.performance')]
        private readonly LoggerInterface $performanceLogger,
        #[Autowire(service: 'monolog.logger.deprecation')]
        private readonly LoggerInterface $deprecationLogger,
    ) {}

    /**
     * Log un message sur un canal spécifique
     */
    public function capture(string $message, string $channel = self::BUSINESS, string $level = self::INFO, array $context = []): void
    {
        $logger = $this->getLoggerForChannel($channel);

        match ($level) {
            self::DEBUG => $logger->debug($message, $context),
            self::NOTICE => $logger->notice($message, $context),
            self::WARNING => $logger->warning($message, $context),
            self::ERROR => $logger->error($message, $context),
            self::CRITICAL => $logger->critical($message, $context),
            self::ALERT => $logger->alert($message, $context),
            self::EMERGENCY => $logger->emergency($message, $context),
            default => $logger->info($message, $context),
        };
    }

    public function security(string $message, string $level = self::WARNING, array $context = []): void
    {
        $this->capture($message, self::SECURITY, $level, $context);
    }

    public function business(string $message, string $level = self::INFO, array $context = []): void
    {
        $this->capture($message, self::BUSINESS, $level, $context);
    }

    public function performance(string $message, string $level = self::INFO, array $context = []): void
    {
        $this->capture($message, self::PERFORMANCE, $level, $context);
    }

    public function deprecation(string $message, array $context = []): void
    {
        $this->capture($message, self::DEPRECATION, self::WARNING, $context);
    }

    public function securityEvent(string $event, array $data = []): void
    {
        $this->security("Security event: {$event}", self::WARNING, $data);
    }

    public function loginAttempt(string $username, bool $success, string $ip = null): void
    {
        $context = [
            'username' => $username,
            'success' => $success,
            'ip' => $ip ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ];

        $level = $success ? self::INFO : self::WARNING;
        $message = $success ? "Successful login for user: {$username}" : "Failed login attempt for user: {$username}";

        $this->security($message, $level, $context);
    }

    public function accessDenied(string $resource, string $user = null): void
    {
        $context = [
            'resource' => $resource,
            'user' => $user,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ];

        $this->security("Access denied to resource: {$resource}", self::ERROR, $context);
    }

    public function businessEvent(string $event, array $data = []): void
    {
        $this->business("Business event: {$event}", self::INFO, $data);
    }

    public function userAction(string $action, string $user, array $data = []): void
    {
        $context = array_merge($data, [
            'user' => $user,
            'action' => $action,
            'timestamp' => date('Y-m-d H:i:s')
        ]);

        $this->business("User action: {$user} performed {$action}", self::INFO, $context);
    }

    public function performanceMetric(string $metric, float $value, string $unit = 'ms'): void
    {
        $context = [
            'metric' => $metric,
            'value' => $value,
            'unit' => $unit,
            'timestamp' => microtime(true)
        ];

        $this->performance("Performance metric: {$metric} = {$value}{$unit}", self::INFO, $context);
    }

    public function slowQuery(string $query, float $executionTime): void
    {
        $context = [
            'query' => $query,
            'execution_time' => $executionTime,
            'threshold_exceeded' => true
        ];

        $this->performance("Slow query detected", self::WARNING, $context);
    }

    private function getLoggerForChannel(string $channel): LoggerInterface
    {
        return match ($channel) {
            self::SECURITY => $this->securityLogger,
            self::BUSINESS => $this->businessLogger,
            self::PERFORMANCE => $this->performanceLogger,
            self::DEPRECATION => $this->deprecationLogger,
            default => $this->logger,
        };
    }
}
