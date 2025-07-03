<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Service helper pour la génération d'URLs
 * Utilise le SettingsManager pour récupérer l'URL de base configurée
 */
class UrlHelper
{
    public function __construct(
        private readonly SettingsManager $settingsManager,
        #[Autowire('%app.mailer_from_email%')] private readonly string $mailerFromEmail
    ) {}

    /**
     * Récupère l'URL de base du site depuis la configuration
     */
    public function getBaseUrl(): string
    {
        $url = $this->settingsManager->getSetting('general.site_url', 'http://localhost');
        return rtrim($url, '/');
    }

    /**
     * Génère une URL complète vers une route du dashboard
     */
    public function generateDashboardUrl(string $path = ''): string
    {
        $baseUrl = $this->getBaseUrl();
        $path = ltrim($path, '/');
        
        return $path ? "$baseUrl/$path" : $baseUrl;
    }

    /**
     * Génère une URL vers les détails d'une erreur
     */
    public function generateErrorUrl(string $projectSlug, int $errorId): string
    {
        return $this->generateDashboardUrl("dashboard/project/$projectSlug/error/$errorId");
    }

    /**
     * Génère une URL vers un projet
     */
    public function generateProjectUrl(string $projectSlug): string
    {
        return $this->generateDashboardUrl("dashboard/project/$projectSlug");
    }

    /**
     * Génère une URL de désinscription
     */
    public function generateUnsubscribeUrl(int $userId, int $projectId): string
    {
        return $this->generateDashboardUrl("unsubscribe/$userId/$projectId");
    }

    /**
     * Récupère l'email d'expéditeur depuis les paramètres de l'application
     */
    public function getSenderEmail(): string
    {
        return $this->mailerFromEmail;
    }

    /**
     * Récupère l'email de support depuis les paramètres de l'application
     */
    public function getSupportEmail(): string
    {
        return $this->mailerFromEmail;
    }
}