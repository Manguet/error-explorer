<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Psr\Log\LoggerInterface;

class SettingsManager
{
    private const SETTINGS_FILE = 'config/settings.json';
    private const CACHE_KEY = 'app_settings';

    public function __construct(
        private readonly ParameterBagInterface $parameterBag,
        private readonly CacheInterface $cache,
        private readonly LoggerInterface $logger,
        private readonly string $projectDir
    ) {}

    /**
     * Récupère tous les paramètres
     */
    public function getAllSettings(): array
    {
        return $this->cache->get(self::CACHE_KEY, function() {
            return $this->loadSettingsFromFile();
        });
    }

    /**
     * Récupère un paramètre spécifique
     */
    public function getSetting(string $key, mixed $default = null): mixed
    {
        $settings = $this->getAllSettings();

        // Support des clés imbriquées (ex: "email.smtp_host")
        $keys = explode('.', $key);
        $value = $settings;

        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                return $default;
            }
            $value = $value[$k];
        }

        return $value;
    }

    /**
     * Met à jour une section complète de paramètres
     */
    public function updateSection(string $section, array $newSettings): bool
    {
        try {
            $currentSettings = $this->getAllSettings();

            // Fusionner avec les paramètres existants
            $currentSettings[$section] = array_merge(
                $currentSettings[$section] ?? [],
                $newSettings
            );

            return $this->saveAllSettings($currentSettings);

        } catch (\Exception $e) {
            $this->logger->error('Erreur lors de la mise à jour des paramètres', [
                'section' => $section,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Met à jour plusieurs sections en une fois
     */
    public function updateMultipleSections(array $sectionsData): bool
    {
        try {
            $currentSettings = $this->getAllSettings();

            foreach ($sectionsData as $section => $newSettings) {
                $currentSettings[$section] = array_merge(
                    $currentSettings[$section] ?? [],
                    $newSettings
                );
            }

            return $this->saveAllSettings($currentSettings);

        } catch (\Exception $e) {
            $this->logger->error('Erreur lors de la mise à jour multiple des paramètres', [
                'sections' => array_keys($sectionsData),
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Met à jour un paramètre individuel
     */
    public function updateSetting(string $key, mixed $value): bool
    {
        try {
            $settings = $this->getAllSettings();

            // Support des clés imbriquées
            $keys = explode('.', $key);
            $current = &$settings;

            foreach ($keys as $i => $k) {
                if ($i === count($keys) - 1) {
                    $current[$k] = $value;
                } else {
                    if (!isset($current[$k]) || !is_array($current[$k])) {
                        $current[$k] = [];
                    }
                    $current = &$current[$k];
                }
            }

            return $this->saveAllSettings($settings);

        } catch (\Exception $e) {
            $this->logger->error('Erreur lors de la mise à jour du paramètre', [
                'key' => $key,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Supprime un paramètre
     */
    public function deleteSetting(string $key): bool
    {
        try {
            $settings = $this->getAllSettings();

            $keys = explode('.', $key);
            $current = &$settings;

            foreach ($keys as $i => $k) {
                if ($i === count($keys) - 1) {
                    unset($current[$k]);
                } else {
                    if (!isset($current[$k])) {
                        return true; // Déjà supprimé
                    }
                    $current = &$current[$k];
                }
            }

            return $this->saveAllSettings($settings);

        } catch (\Exception $e) {
            $this->logger->error('Erreur lors de la suppression du paramètre', [
                'key' => $key,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Réinitialise une section aux valeurs par défaut
     */
    public function resetSection(string $section): bool
    {
        $defaults = $this->getDefaultSettings();

        if (!isset($defaults[$section])) {
            return false;
        }

        return $this->updateSection($section, $defaults[$section]);
    }

    /**
     * Exporte tous les paramètres
     */
    public function exportSettings(): array
    {
        return [
            'settings' => $this->getAllSettings(),
            'exported_at' => date('Y-m-d H:i:s'),
            'version' => $this->parameterBag->get('app.version', '1.0.0')
        ];
    }

    /**
     * Importe des paramètres depuis un export
     */
    public function importSettings(array $importData): bool
    {
        if (!isset($importData['settings']) || !is_array($importData['settings'])) {
            throw new \InvalidArgumentException('Format d\'import invalide');
        }

        // Valider les paramètres avant import
        $this->validateSettings($importData['settings']);

        return $this->saveAllSettings($importData['settings']);
    }

    /**
     * Vide le cache des paramètres
     */
    public function clearCache(): bool
    {
        try {
            $this->cache->delete(self::CACHE_KEY);
            return true;
        } catch (\Exception $e) {
            $this->logger->error('Erreur lors du vidage du cache des paramètres', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Charge les paramètres depuis le fichier
     */
    public function loadSettingsFromFile(): array
    {
        $settingsFile = $this->projectDir . '/' . self::SETTINGS_FILE;

        if (!file_exists($settingsFile)) {
            $this->createDefaultSettingsFile();
        }

        $content = file_get_contents($settingsFile);
        if ($content === false) {
            throw new \RuntimeException('Impossible de lire le fichier de paramètres');
        }

        $settings = json_decode($content, true, 512, JSON_THROW_ON_ERROR);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Fichier de paramètres JSON invalide : ' . json_last_error_msg());
        }

        // Fusionner avec les valeurs par défaut
        return $settings ?? $this->getDefaultSettings();
    }

    /**
     * Sauvegarde tous les paramètres
     */
    private function saveAllSettings(array $settings): bool
    {
        try {
            $settingsFile = $this->projectDir . '/' . self::SETTINGS_FILE;

            // Créer le répertoire si nécessaire
            $dir = dirname($settingsFile);
            if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
                throw new \RuntimeException(sprintf('Directory "%s" was not created', $dir));
            }

            // Sauvegarder avec formatage lisible
            $json = json_encode($settings, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            if ($json === false) {
                throw new \RuntimeException('Erreur d\'encodage JSON : ' . json_last_error_msg());
            }

            if (file_put_contents($settingsFile, $json, LOCK_EX) === false) {
                throw new \RuntimeException('Impossible d\'écrire le fichier de paramètres');
            }

            // Vider le cache
            $this->clearCache();

            $this->logger->info('Paramètres sauvegardés avec succès');
            return true;

        } catch (\Exception $e) {
            $this->logger->error('Erreur lors de la sauvegarde des paramètres', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Crée le fichier de paramètres par défaut
     */
    private function createDefaultSettingsFile(): void
    {
        $defaults = $this->getDefaultSettings();
        $this->saveAllSettings($defaults);
    }

    /**
     * Retourne les paramètres par défaut
     */
    private function getDefaultSettings(): array
    {
        return [
            'general' => [
                'site_name' => 'Error Explorer',
                'site_url' => '',
                'site_description' => 'Monitoring d\'erreurs nouvelle génération',
                'max_file_size' => '10MB',
                'session_timeout' => 3600,
                'maintenance_mode' => false,
                'registration_enabled' => true,
                'email_notifications' => true,
            ],
            'email' => [
                'smtp_host' => '',
                'smtp_port' => 587,
                'smtp_username' => '',
                'smtp_password' => '',
                'mail_from_email' => '',
                'mail_from_name' => 'Error Explorer',
                'smtp_encryption' => true,
                'admin_notifications' => true,
                'welcome_emails' => true,
                'error_alerts' => false,
                'daily_reports' => false,
                'weekly_reports' => true,
                'limit_alerts' => true,
                'limit_alert_threshold' => 80,
                'admin_emails' => '',
                'email_rate_limit' => 100,
                'alert_cooldown' => 30,
                'daily_report_time' => '08:00',
                'weekly_report_day' => 1,
                'email_queue_enabled' => true,
                'email_retry_enabled' => true,
                'email_detailed_logs' => false,
                'welcome_email_template' => '',
                'error_alert_template' => '',
                'limit_reached_template' => '',
            ],
            'api' => [
                'api_enabled' => true,
                'api_docs_public' => false,
                'api_rate_limit' => 1000,
                'api_timeout' => 30,
                'main_api_key' => 'ee_sk_' . bin2hex(random_bytes(16)),
                'custom_webhooks_enabled' => false,
                'custom_webhook_urls' => '',
                'webhook_timeout' => 10,
                'webhook_retry_attempts' => 3,
                'webhook_secret' => '',
            ],
            'integrations' => [
                'slack_enabled' => false,
                'slack_webhook_url' => '',
                'slack_default_channel' => '#errors',
                'discord_enabled' => false,
                'discord_webhook_url' => '',
                'teams_enabled' => false,
                'teams_webhook_url' => '',
            ],
            'security' => [
                'session_timeout' => 60,
                'remember_me_duration' => 30,
                'max_login_attempts' => 5,
                'lockout_duration' => 15,
                'two_factor_enabled' => false,
                'force_2fa_admin' => false,
                'logout_on_ip_change' => false,
                'password_min_length' => 8,
                'password_expiry_days' => 90,
                'strong_passwords_required' => true,
                'password_history_enabled' => false,
                'check_pwned_passwords' => false,
                'auto_anonymization' => true,
                'database_encryption' => false,
                'security_logging' => true,
                'allowed_ips' => '',
                'anonymization_rules' => '',
                'force_https' => true,
                'csp_enabled' => true,
                'x_frame_options' => true,
                'custom_csp' => '',
            ],
        ];
    }

    /**
     * Valide la structure des paramètres
     */
    private function validateSettings(array $settings): void
    {
        $defaults = $this->getDefaultSettings();

        foreach ($settings as $section => $sectionSettings) {
            if (!isset($defaults[$section])) {
                throw new \InvalidArgumentException("Section inconnue : $section");
            }

            if (!is_array($sectionSettings)) {
                throw new \InvalidArgumentException("Section $section doit être un tableau");
            }
        }
    }
}
