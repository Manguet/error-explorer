<?php

namespace App\Controller\Admin;

use App\Repository\ErrorGroupRepository;
use App\Repository\ErrorOccurrenceRepository;
use App\Repository\PlanRepository;
use App\Repository\ProjectRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin', name: 'admin_')]
#[IsGranted('ROLE_ADMIN')]
class SystemController extends AbstractController
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly PlanRepository $planRepository,
        private readonly ProjectRepository $projectRepository,
        private readonly ErrorGroupRepository $errorGroupRepository,
        private readonly ErrorOccurrenceRepository $errorOccurrenceRepository
    ) {}

    #[Route('/system-info', name: 'system_info')]
    public function systemInfo(): Response
    {
        // Informations PHP et Symfony
        $phpInfo = [
            'version' => PHP_VERSION,
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
            'timezone' => date_default_timezone_get(),
            'extensions' => get_loaded_extensions()
        ];

        $symfonyInfo = [
            'version' => \Symfony\Component\HttpKernel\Kernel::VERSION,
            'environment' => $this->getParameter('kernel.environment'),
            'debug' => $this->getParameter('kernel.debug'),
            'project_dir' => $this->getParameter('kernel.project_dir'),
            'cache_dir' => $this->getParameter('kernel.cache_dir'),
            'log_dir' => $this->getParameter('kernel.logs_dir')
        ];

        // Informations serveur
        $serverInfo = [
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'N/A',
            'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? 'N/A',
            'server_admin' => $_SERVER['SERVER_ADMIN'] ?? 'N/A',
            'server_name' => $_SERVER['SERVER_NAME'] ?? 'N/A',
            'server_port' => $_SERVER['SERVER_PORT'] ?? 'N/A',
            'https' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'N/A'
        ];

        // Statistiques de la base de données
        $dbStats = [
            'users_count' => $this->userRepository->count(),
            'plans_count' => $this->planRepository->count(),
            'projects_count' => $this->projectRepository->count(),
            'error_groups_count' => $this->errorGroupRepository->count(),
            'error_occurrences_count' => $this->errorOccurrenceRepository->count()
        ];

        // Informations système (si disponibles)
        $systemInfo = [
            'os' => php_uname('s') . ' ' . php_uname('r'),
            'architecture' => php_uname('m'),
            'hostname' => php_uname('n'),
            'uptime' => $this->getSystemUptime(),
            'load_average' => $this->getLoadAverage(),
            'memory_usage' => $this->getMemoryUsage(),
            'disk_usage' => $this->getDiskUsage()
        ];

        // Extensions PHP importantes
        $importantExtensions = [
            'pdo' => extension_loaded('pdo'),
            'pdo_mysql' => extension_loaded('pdo_mysql'),
            'pdo_pgsql' => extension_loaded('pdo_pgsql'),
            'curl' => extension_loaded('curl'),
            'json' => extension_loaded('json'),
            'mbstring' => extension_loaded('mbstring'),
            'openssl' => extension_loaded('openssl'),
            'zip' => extension_loaded('zip'),
            'gd' => extension_loaded('gd'),
            'intl' => extension_loaded('intl'),
            'redis' => extension_loaded('redis'),
            'memcached' => extension_loaded('memcached'),
            'opcache' => extension_loaded('opcache'),
            'xdebug' => extension_loaded('xdebug')
        ];

        // Configuration Symfony importante
        $config = [
            'session_name' => session_name(),
            'session_save_path' => session_save_path(),
            'session_gc_maxlifetime' => ini_get('session.gc_maxlifetime'),
            'error_reporting' => error_reporting(),
            'display_errors' => ini_get('display_errors'),
            'log_errors' => ini_get('log_errors')
        ];

        return $this->render('admin/system/info.html.twig', [
            'php_info' => $phpInfo,
            'symfony_info' => $symfonyInfo,
            'server_info' => $serverInfo,
            'db_stats' => $dbStats,
            'system_info' => $systemInfo,
            'extensions' => $importantExtensions,
            'config' => $config
        ]);
    }

    private function getSystemUptime(): ?string
    {
        if (function_exists('sys_getloadavg') && is_readable('/proc/uptime')) {
            $uptime = file_get_contents('/proc/uptime');
            if ($uptime !== false) {
                $seconds = (float) explode(' ', $uptime)[0];
                $days = floor($seconds / 86400);
                $hours = floor(($seconds % 86400) / 3600);
                $minutes = floor(($seconds % 3600) / 60);

                return sprintf('%d jours, %d heures, %d minutes', $days, $hours, $minutes);
            }
        }

        return null;
    }

    private function getLoadAverage(): ?array
    {
        if (function_exists('sys_getloadavg')) {
            $load = sys_getloadavg();
            return [
                '1min' => round($load[0], 2),
                '5min' => round($load[1], 2),
                '15min' => round($load[2], 2)
            ];
        }

        return null;
    }

    private function getMemoryUsage(): array
    {
        return [
            'current' => $this->formatBytes(memory_get_usage()),
            'peak' => $this->formatBytes(memory_get_peak_usage()),
            'current_real' => $this->formatBytes(memory_get_usage(true)),
            'peak_real' => $this->formatBytes(memory_get_peak_usage(true))
        ];
    }

    private function getDiskUsage(): ?array
    {
        $projectDir = $this->getParameter('kernel.project_dir');

        if (function_exists('disk_free_space') && function_exists('disk_total_space')) {
            $free = disk_free_space($projectDir);
            $total = disk_total_space($projectDir);

            if ($free !== false && $total !== false) {
                $used = $total - $free;
                $percentage = round(($used / $total) * 100, 1);

                return [
                    'total' => $this->formatBytes($total),
                    'used' => $this->formatBytes($used),
                    'free' => $this->formatBytes($free),
                    'percentage' => $percentage
                ];
            }
        }

        return null;
    }

    private function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision) . ' ' . $units[$i];
    }
}
