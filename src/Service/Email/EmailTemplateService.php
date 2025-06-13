<?php

namespace App\Service\Email;

use Exception;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Twig\Environment;
use Twig\Error\LoaderError;
use Psr\Log\LoggerInterface;

/**
 * Service de gestion et optimisation des templates d'email
 */
class EmailTemplateService
{
    private const TEMPLATE_CACHE_TTL = 3600; // 1 heure
    private const COMPILED_TEMPLATE_CACHE_TTL = 86400; // 24 heures

    public function __construct(
        private readonly Environment $twig,
        private readonly CacheInterface $cache,
        private readonly LoggerInterface $logger
    ) {}

    /**
     * Rend un template avec mise en cache optimisée
     */
    public function renderTemplate(string $template, array $context = []): string
    {
        $cacheKey = $this->generateCacheKey($template, $context);

        try {
            return $this->cache->get($cacheKey, function (ItemInterface $item) use ($template, $context) {
                $item->expiresAfter(self::TEMPLATE_CACHE_TTL);

                $item->tag(['email_template', 'template_' . md5($template)]);

                return $this->twig->render($template, $context);
            });
        } catch (Exception $e) {
            $this->logger->error('Erreur rendu template email', [
                'template' => $template,
                'error' => $e->getMessage(),
                'context_keys' => array_keys($context)
            ]);

            throw new RuntimeException("Impossible de rendre le template $template", 0, $e);
        }
    }

    /**
     * Précompile les templates fréquemment utilisés
     */
    public function precompileFrequentTemplates(): void
    {
        $frequentTemplates = [
            'emails/email_verification.html.twig',
            'emails/password_reset.html.twig',
            'emails/welcome.html.twig',
            'emails/password_changed.html.twig'
        ];

        foreach ($frequentTemplates as $template) {
            try {
                $this->precompileTemplate($template);
                $this->logger->info("Template précompilé : $template");
            } catch (Exception $e) {
                $this->logger->warning("Échec précompilation template : $template", [
                    'error' => $e->getMessage()
                ]);
            }
        }
    }

    /**
     * Précompile un template spécifique
     */
    private function precompileTemplate(string $template): void
    {
        $cacheKey = "precompiled_template_" . md5($template);

        $this->cache->get($cacheKey, function (ItemInterface $item) use ($template) {
            $item->expiresAfter(self::COMPILED_TEMPLATE_CACHE_TTL);
            $item->tag(['precompiled_template']);

            $this->twig->load($template);

            return [
                'compiled_at' => time(),
                'template' => $template,
                'checksum' => $this->getTemplateChecksum($template)
            ];
        });
    }

    /**
     * Valide qu'un template existe et est valide
     */
    public function validateTemplate(string $template): bool
    {
        $cacheKey = "template_validation_" . md5($template);

        try {
            return $this->cache->get($cacheKey, function (ItemInterface $item) use ($template) {
                $item->expiresAfter(self::TEMPLATE_CACHE_TTL);

                try {
                    $this->twig->load($template);
                    return true;
                } catch (LoaderError) {
                    return false;
                }
            });
        } catch (Exception) {
            return false;
        }
    }

    /**
     * Obtient la liste des templates disponibles
     */
    public function getAvailableTemplates(): array
    {
        return $this->cache->get('available_email_templates', function (ItemInterface $item) {
            $item->expiresAfter(3600);

            $templates = [];
            $templateDir = $this->twig->getLoader()->getPaths('emails')[0] ?? null;

            if ($templateDir && is_dir($templateDir)) {
                $iterator = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($templateDir)
                );

                foreach ($iterator as $file) {
                    if ($file->isFile() && $file->getExtension() === 'twig') {
                        $relativePath = str_replace($templateDir . '/', '', $file->getPathname());
                        $templates[] = 'emails/' . $relativePath;
                    }
                }
            }

            return $templates;
        });
    }

    /**
     * Invalide le cache des templates
     */
    public function clearTemplateCache(?string $specificTemplate = null): void
    {
        if ($specificTemplate) {
            $this->cache->invalidateTags(['template_' . md5($specificTemplate)]);
            $this->logger->info("Cache invalidé pour template : $specificTemplate");
        } else {
            $this->cache->invalidateTags(['email_template', 'precompiled_template']);
            $this->logger->info('Cache de tous les templates email invalidé');
        }
    }

    /**
     * Génère une clé de cache pour un template et son contexte
     */
    private function generateCacheKey(string $template, array $context): string
    {
        // Exclure les variables dynamiques du cache
        $cacheableContext = array_diff_key($context, [
            'verification_url' => null,
            'reset_url' => null,
            'timestamp' => null,
            'expires_at' => null,
            'changed_at' => null
        ]);

        return 'email_template_' . md5($template . serialize($cacheableContext));
    }

    /**
     * Obtient le checksum d'un template pour détecter les changements
     */
    private function getTemplateChecksum(string $template): string
    {
        try {
            $source = $this->twig->getLoader()->getSourceContext($template);
            return md5($source->getCode());
        } catch (Exception) {
            return '';
        }
    }

    /**
     * Test de rendu de tous les templates avec données factices
     */
    public function testAllTemplates(): array
    {
        $results = [];
        $templates = $this->getAvailableTemplates();

        $testContext = [
            'user' => new class {
                public function getFullName(): string { return 'John Doe'; }
                public function getFirstName(): string { return 'John'; }
                public function getEmail(): string { return 'john@example.com'; }
            },
            'app_name' => 'Error Explorer',
            'year' => date('Y'),
            'verification_url' => 'https://example.com/verify',
            'reset_url' => 'https://example.com/reset',
            'dashboard_url' => 'https://example.com/dashboard'
        ];

        foreach ($templates as $template) {
            try {
                $startTime = microtime(true);
                $this->twig->render($template, $testContext);
                $renderTime = (microtime(true) - $startTime) * 1000;

                $results[$template] = [
                    'status' => 'success',
                    'render_time_ms' => $renderTime
                ];
            } catch (Exception $e) {
                $results[$template] = [
                    'status' => 'error',
                    'error' => $e->getMessage()
                ];
            }
        }

        return $results;
    }
}
