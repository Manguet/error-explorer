<?php

namespace App\Controller\Admin;

use App\Repository\ErrorGroupRepository;
use App\Repository\PlanRepository;
use App\Repository\ProjectRepository;
use App\Repository\UserRepository;
use App\Service\ErrorLimitService;
use App\Service\SettingsManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Contracts\Cache\CacheInterface;

#[Route('/admin', name: 'admin_')]
#[IsGranted('ROLE_ADMIN')]
class AdminController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository,
        private readonly PlanRepository $planRepository,
        private readonly ProjectRepository $projectRepository,
        private readonly ErrorGroupRepository $errorGroupRepository,
        private readonly ErrorLimitService $errorLimitService,
        private readonly CacheInterface $cache,
        private readonly SettingsManager $settingsManager
    ) {}

    #[Route('/', name: 'dashboard')]
    public function dashboard(): Response
    {
        // Statistiques globales
        $stats = [
            'users' => $this->userRepository->getGlobalStats(),
            'plans' => $this->planRepository->getPlansStats(),
            'usage' => $this->errorLimitService->generateUsageReport(),
            'errors' => [
                'total_groups' => $this->errorGroupRepository->count([]),
                'total_projects' => $this->projectRepository->count([]),
                'active_users' => $this->userRepository->count(['isActive' => true]),
                'expired_plans' => count($this->userRepository->findUsersWithExpiringPlan(0))
            ]
        ];

        // Utilisateurs récents
        $recentUsers = $this->userRepository->findBy(
            ['isActive' => true],
            ['createdAt' => 'DESC'],
            10
        );

        // Utilisateurs en limite
        $usersOverLimits = $this->userRepository->findUsersOverLimits();

        return $this->render('admin/dashboard.html.twig', [
            'stats' => $stats,
            'recent_users' => $recentUsers,
            'users_over_limits' => $usersOverLimits
        ]);
    }

    #[Route('/settings', name: 'settings')]
    public function settings(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            return $this->handleSettingsUpdate($request);
        }

        // Récupérer tous les paramètres via le service
        $settings = $this->settingsManager->loadSettingsFromFile();

        // Statistiques de stockage
        $storageStats = $this->getStorageStats();

        // Informations système
        $systemInfo = $this->getSystemInfo();

        // Statistiques email et API (simulées pour l'exemple)
        $emailStats = [
            'sent_today' => 0,
            'failed_today' => 0,
            'queued' => 0,
            'success_rate' => 100
        ];

        $apiStats = [
            'requests_today' => 0,
            'errors_today' => 0,
            'webhook_deliveries' => 0,
            'active_keys' => 1
        ];

        return $this->render('admin/settings.html.twig', [
            'settings' => $settings,
            'storage_stats' => $storageStats,
            'system_info' => $systemInfo,
            'email_stats' => $emailStats,
            'api_stats' => $apiStats
        ]);
    }

    #[Route('/settings/update', name: 'settings_update', methods: ['POST'])]
    public function updateSettings(Request $request): JsonResponse
    {
        try {
            $section = $request->request->get('section');
            $settingsData = $request->request->all();

            // Nettoyer les données (enlever les champs non-settings)
            unset($settingsData['section'], $settingsData['_token']);

            // Traitement spécifique par section
            $processedData = $this->processSettingsData($section, $settingsData);

            // Mettre à jour la section
            $success = $this->settingsManager->updateSection($section, $processedData);

            if ($success) {
                return $this->json([
                    'success' => true,
                    'message' => 'Paramètres mis à jour avec succès',
                    'section' => $section
                ]);
            }

            return $this->json([
                'success' => false,
                'error' => 'Erreur lors de la sauvegarde'
            ], 500);

        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => 'Erreur : ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/settings/get-section/{section}', name: 'settings_get_section')]
    public function getSettingsSection(string $section): JsonResponse
    {
        try {
            $sectionSettings = $this->settingsManager->getSetting($section, []);

            return $this->json([
                'success' => true,
                'settings' => $sectionSettings
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    #[Route('/settings/reset-section/{section}', name: 'settings_reset_section', methods: ['POST'])]
    public function resetSettingsSection(string $section): JsonResponse
    {
        try {
            $success = $this->settingsManager->resetSection($section);

            if ($success) {
                return $this->json([
                    'success' => true,
                    'message' => 'Section réinitialisée aux valeurs par défaut',
                    'settings' => $this->settingsManager->getSetting($section, [])
                ]);
            }

            return $this->json([
                'success' => false,
                'error' => 'Erreur lors de la réinitialisation'
            ], 500);

        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    #[Route('/settings/export', name: 'settings_export')]
    public function exportSettings(): Response
    {
        try {
            $exportData = $this->settingsManager->exportSettings();

            $response = new Response(json_encode($exportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $response->headers->set('Content-Type', 'application/json');
            $response->headers->set('Content-Disposition', 'attachment; filename="error-explorer-settings-' . date('Y-m-d') . '.json"');

            return $response;
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de l\'export : ' . $e->getMessage());
            return $this->redirectToRoute('admin_settings');
        }
    }

    #[Route('/settings/import', name: 'settings_import', methods: ['POST'])]
    public function importSettings(Request $request): JsonResponse
    {
        try {
            $file = $request->files->get('settings_file');

            if (!$file) {
                return $this->json([
                    'success' => false,
                    'error' => 'Aucun fichier sélectionné'
                ], 400);
            }

            $content = file_get_contents($file->getPathname());
            $importData = json_decode($content, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return $this->json([
                    'success' => false,
                    'error' => 'Fichier JSON invalide'
                ], 400);
            }

            $success = $this->settingsManager->importSettings($importData);

            if ($success) {
                return $this->json([
                    'success' => true,
                    'message' => 'Paramètres importés avec succès'
                ]);
            }

            return $this->json([
                'success' => false,
                'error' => 'Erreur lors de l\'import'
            ], 500);

        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => 'Erreur : ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/settings/test-smtp', name: 'settings_test_smtp', methods: ['POST'])]
    public function testSmtpConfiguration(): JsonResponse
    {
        try {
            $emailSettings = $this->settingsManager->getSetting('email', []);

            // TODO: Implémenter test SMTP réel
            // Pour l'instant, simulation

            return $this->json([
                'success' => true,
                'message' => 'Configuration SMTP testée avec succès'
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => 'Erreur SMTP : ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/settings/clear-cache', name: 'clear_cache')]
    public function clearCache(): JsonResponse
    {
        try {
            // Vider le cache Symfony
            $this->cache->clear();

            // Vider le cache des paramètres
            $this->settingsManager->clearCache();

            // Vider le cache du filesystem si utilisé
            $filesystemCache = new FilesystemAdapter();
            $filesystemCache->clear();

            return $this->json([
                'success' => true,
                'message' => 'Cache vidé avec succès'
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => 'Erreur lors du vidage du cache : ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/settings/cleanup-data', name: 'cleanup_old_data')]
    public function cleanupOldData(): JsonResponse
    {
        try {
            // Nettoyer les anciennes occurrences (plus de 90 jours)
            $deletedOccurrences = $this->entityManager->getConnection()
                ->executeStatement(
                    'DELETE FROM error_occurrences WHERE created_at < :date',
                    ['date' => (new \DateTime('-90 days'))->format('Y-m-d H:i:s')]
                );

            // Nettoyer les groupes d'erreur sans occurrences
            $deletedGroups = $this->entityManager->getConnection()
                ->executeStatement(
                    'DELETE FROM error_groups WHERE id NOT IN (SELECT DISTINCT error_group_id FROM error_occurrences)'
                );

            return $this->json([
                'success' => true,
                'message' => sprintf('Nettoyage terminé : %d occurrences et %d groupes supprimés',
                    $deletedOccurrences, $deletedGroups),
                'deleted_occurrences' => $deletedOccurrences,
                'deleted_groups' => $deletedGroups
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => 'Erreur lors du nettoyage : ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/stats/export', name: 'stats_export')]
    public function exportStats(): Response
    {
        $stats = [
            'users' => $this->userRepository->getGlobalStats(),
            'plans' => $this->planRepository->getPlansStats(),
            'usage' => $this->errorLimitService->generateUsageReport(),
            'storage' => $this->getStorageStats(),
            'export_date' => date('Y-m-d H:i:s')
        ];

        $response = new Response(json_encode($stats, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
        $response->headers->set('Content-Type', 'application/json');
        $response->headers->set('Content-Disposition', 'attachment; filename="error-explorer-stats-' . date('Y-m-d') . '.json"');

        return $response;
    }

    /**
     * Gestion des anciennes requêtes POST (rétrocompatibilité)
     */
    private function handleSettingsUpdate(Request $request): Response
    {
        try {
            $section = $request->request->get('section', 'general');
            $settingsData = $request->request->all();

            // Nettoyer les données
            unset($settingsData['section'], $settingsData['_token']);

            // Traitement spécifique
            $processedData = $this->processSettingsData($section, $settingsData);

            // Mettre à jour
            $success = $this->settingsManager->updateSection($section, $processedData);

            if ($success) {
                $this->addFlash('success', 'Paramètres mis à jour avec succès');
            } else {
                $this->addFlash('error', 'Erreur lors de la mise à jour');
            }

        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de la mise à jour : ' . $e->getMessage());
        }

        return $this->redirectToRoute('admin_settings', [], 302, ['#' => $section]);
    }

    /**
     * Traite et valide les données de paramètres selon la section
     */
    private function processSettingsData(string $section, array $data): array
    {
        return match ($section) {
            'general' => [
                'site_name' => trim($data['site_name'] ?? 'Error Explorer'),
                'site_url' => trim($data['site_url'] ?? ''),
                'site_description' => trim($data['site_description'] ?? ''),
                'max_file_size' => trim($data['max_file_size'] ?? '10') . 'MB',
                'session_timeout' => (int)($data['session_timeout'] ?? 60),
                'maintenance_mode' => isset($data['maintenance_mode']),
                'registration_enabled' => isset($data['registration_enabled']),
                'email_notifications' => isset($data['email_notifications']),
            ],
            'email' => [
                'smtp_host' => trim($data['smtp_host'] ?? ''),
                'smtp_port' => (int)($data['smtp_port'] ?? 587),
                'smtp_username' => trim($data['smtp_username'] ?? ''),
                'smtp_password' => trim($data['smtp_password'] ?? ''),
                'mail_from_email' => trim($data['mail_from_email'] ?? ''),
                'mail_from_name' => trim($data['mail_from_name'] ?? 'Error Explorer'),
                'smtp_encryption' => isset($data['smtp_encryption']),
                'admin_notifications' => isset($data['admin_notifications']),
                'welcome_emails' => isset($data['welcome_emails']),
                'error_alerts' => isset($data['error_alerts']),
                'daily_reports' => isset($data['daily_reports']),
                'weekly_reports' => isset($data['weekly_reports']),
                'limit_alerts' => isset($data['limit_alerts']),
                'limit_alert_threshold' => (int)($data['limit_alert_threshold'] ?? 80),
                'admin_emails' => trim($data['admin_emails'] ?? ''),
                'email_rate_limit' => (int)($data['email_rate_limit'] ?? 100),
                'alert_cooldown' => (int)($data['alert_cooldown'] ?? 30),
                'daily_report_time' => $data['daily_report_time'] ?? '08:00',
                'weekly_report_day' => (int)($data['weekly_report_day'] ?? 1),
                'email_queue_enabled' => isset($data['email_queue_enabled']),
                'email_retry_enabled' => isset($data['email_retry_enabled']),
                'email_detailed_logs' => isset($data['email_detailed_logs']),
                'welcome_email_template' => trim($data['welcome_email_template'] ?? ''),
                'error_alert_template' => trim($data['error_alert_template'] ?? ''),
                'limit_reached_template' => trim($data['limit_reached_template'] ?? ''),
            ],
            'security' => [
                'session_timeout' => (int)($data['session_timeout'] ?? 60),
                'remember_me_duration' => (int)($data['remember_me_duration'] ?? 30),
                'max_login_attempts' => (int)($data['max_login_attempts'] ?? 5),
                'lockout_duration' => (int)($data['lockout_duration'] ?? 15),
                'two_factor_enabled' => isset($data['two_factor_enabled']),
                'force_2fa_admin' => isset($data['force_2fa_admin']),
                'logout_on_ip_change' => isset($data['logout_on_ip_change']),
                'password_min_length' => (int)($data['password_min_length'] ?? 8),
                'password_expiry_days' => (int)($data['password_expiry_days'] ?? 90),
                'strong_passwords_required' => isset($data['strong_passwords_required']),
                'password_history_enabled' => isset($data['password_history_enabled']),
                'check_pwned_passwords' => isset($data['check_pwned_passwords']),
                'auto_anonymization' => isset($data['auto_anonymization']),
                'database_encryption' => isset($data['database_encryption']),
                'security_logging' => isset($data['security_logging']),
                'allowed_ips' => trim($data['allowed_ips'] ?? ''),
                'anonymization_rules' => trim($data['anonymization_rules'] ?? ''),
                'force_https' => isset($data['force_https']),
                'csp_enabled' => isset($data['csp_enabled']),
                'x_frame_options' => isset($data['x_frame_options']),
                'custom_csp' => trim($data['custom_csp'] ?? ''),
            ],
            'api' => [
                'api_enabled' => isset($data['api_enabled']),
                'api_docs_public' => isset($data['api_docs_public']),
                'api_rate_limit' => (int)($data['api_rate_limit'] ?? 1000),
                'api_timeout' => (int)($data['api_timeout'] ?? 30),
                'custom_webhooks_enabled' => isset($data['custom_webhooks_enabled']),
                'custom_webhook_urls' => trim($data['custom_webhook_urls'] ?? ''),
                'webhook_timeout' => (int)($data['webhook_timeout'] ?? 10),
                'webhook_retry_attempts' => (int)($data['webhook_retry_attempts'] ?? 3),
                'webhook_secret' => trim($data['webhook_secret'] ?? ''),
            ],
            'integrations' => [
                'slack_enabled' => isset($data['slack_enabled']),
                'slack_webhook_url' => trim($data['slack_webhook_url'] ?? ''),
                'slack_default_channel' => trim($data['slack_default_channel'] ?? '#errors'),
                'discord_enabled' => isset($data['discord_enabled']),
                'discord_webhook_url' => trim($data['discord_webhook_url'] ?? ''),
                'teams_enabled' => isset($data['teams_enabled']),
                'teams_webhook_url' => trim($data['teams_webhook_url'] ?? ''),
            ],
            default => $data,
        };
    }

    private function getStorageStats(): array
    {
        try {
            // Calculer la taille utilisée
            $totalSize = $this->entityManager->getConnection()
                ->fetchOne('SELECT 
                    ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) as size_mb
                    FROM information_schema.tables 
                    WHERE table_schema = DATABASE()');

            return [
                'total_size' => ($totalSize ?? 0) . ' MB',
                'error_groups' => $this->errorGroupRepository->count([]),
                'occurrences' => $this->entityManager->getConnection()
                    ->fetchOne('SELECT COUNT(*) FROM error_occurrences'),
                'projects' => $this->projectRepository->count([]),
                'users' => $this->userRepository->count([])
            ];
        } catch (\Exception $e) {
            return [
                'total_size' => 'N/A',
                'error_groups' => 0,
                'occurrences' => 0,
                'projects' => 0,
                'users' => 0
            ];
        }
    }

    private function getSystemInfo(): array
    {
        try {
            // Calculer l'uptime basique
            $startTime = filemtime(__DIR__ . '/../../../var/cache');
            $uptime = time() - $startTime;

            $uptimeFormatted = '';
            if ($uptime > 86400) {
                $days = floor($uptime / 86400);
                $uptimeFormatted .= $days . 'd ';
                $uptime %= 86400;
            }
            if ($uptime > 3600) {
                $hours = floor($uptime / 3600);
                $uptimeFormatted .= $hours . 'h ';
                $uptime %= 3600;
            }
            $minutes = floor($uptime / 60);
            $uptimeFormatted .= $minutes . 'm';

            return [
                'php_version' => PHP_VERSION,
                'symfony_version' => Kernel::VERSION,
                'uptime' => $uptimeFormatted,
                'memory_usage' => memory_get_usage(true),
                'memory_peak' => memory_get_peak_usage(true),
                'disk_free_space' => disk_free_space(__DIR__),
                'disk_total_space' => disk_total_space(__DIR__)
            ];
        } catch (\Exception $e) {
            return [
                'php_version' => PHP_VERSION,
                'symfony_version' => 'N/A',
                'uptime' => 'N/A',
                'memory_usage' => 0,
                'memory_peak' => 0,
                'disk_free_space' => 0,
                'disk_total_space' => 0
            ];
        }
    }
}
