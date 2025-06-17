<?php

namespace App\Service\Changelog;

/**
 * Service pour parser un fichier changelog.md
 * Convertit le markdown en données structurées pour l'affichage
 */
class ChangelogParser
{
    private string $changelogPath;

    public function __construct(string $projectDir)
    {
        $this->changelogPath = $projectDir . '/CHANGELOG.md';
    }

    /**
     * Parse le fichier changelog.md et retourne les données structurées
     */
    public function parse(): array
    {
        if (!file_exists($this->changelogPath)) {
            throw new \RuntimeException('Le fichier changelog.md n\'existe pas');
        }

        $content = file_get_contents($this->changelogPath);
        if ($content === false) {
            throw new \RuntimeException('Impossible de lire le fichier changelog.md');
        }

        return $this->parseContent($content);
    }

    /**
     * Parse le contenu markdown du changelog
     */
    private function parseContent(string $content): array
    {
        $entries = [];
        $lines = explode("\n", $content);
        $currentEntry = null;
        $currentSection = null;
        $currentItems = [];

        foreach ($lines as $line) {
            $line = trim($line);

            // Ignorer les lignes vides et les commentaires
            if (empty($line) || str_starts_with($line, '<!--')) {
                continue;
            }

            // Version (## [2.1.0] - 2025-06-15)
            if (preg_match('/^## \[([^\]]+)\] - (.+)$/', $line, $matches)) {
                // Sauvegarder l'entrée précédente si elle existe
                if ($currentEntry) {
                    if ($currentSection && !empty($currentItems)) {
                        $currentEntry['sections'][$currentSection] = $currentItems;
                    }
                    $entries[] = $currentEntry;
                }

                // Nouvelle entrée
                $currentEntry = [
                    'version' => $matches[1],
                    'date' => $this->parseDate($matches[2]),
                    'type' => $this->determineVersionType($matches[1]),
                    'sections' => [],
                    'breaking_changes' => []
                ];

                $currentSection = null;
                $currentItems = [];
            }

            // Sections (### 🚀 Nouveautés)
            elseif (preg_match('/^### (.+)$/', $line, $matches)) {
                // Sauvegarder la section précédente
                if ($currentSection && !empty($currentItems)) {
                    $currentEntry['sections'][$currentSection] = $currentItems;
                }

                $sectionTitle = $matches[1];
                $currentSection = $this->normalizeSectionType($sectionTitle);
                $currentItems = [];
            }

            // Éléments de liste (- **Titre** : Description)
            elseif (preg_match('/^- \*\*([^*]+)\*\*\s*:\s*(.+)$/', $line, $matches)) {
                if ($currentSection) {
                    $currentItems[] = [
                        'title' => trim($matches[1]),
                        'description' => trim($matches[2]),
                        'type' => $this->mapSectionToFeatureType($currentSection)
                    ];
                }
            }

            // Éléments de liste simples (- Description simple)
            elseif (preg_match('/^- (.+)$/', $line, $matches)) {
                if ($currentSection) {
                    $description = trim($matches[1]);

                    // Extraire le titre si présent
                    if (preg_match('/\*\*([^*]+)\*\*\s*:\s*(.+)/', $description, $titleMatches)) {
                        $title = trim($titleMatches[1]);
                        $desc = trim($titleMatches[2]);
                    } else {
                        $title = $this->extractTitleFromDescription($description);
                        $desc = $description;
                    }

                    $currentItems[] = [
                        'title' => $title,
                        'description' => $desc,
                        'type' => $this->mapSectionToFeatureType($currentSection)
                    ];
                }
            }
        }

        // Sauvegarder la dernière entrée
        if ($currentEntry) {
            if ($currentSection && !empty($currentItems)) {
                $currentEntry['sections'][$currentSection] = $currentItems;
            }
            $entries[] = $currentEntry;
        }

        return $this->processEntries($entries);
    }

    /**
     * Parse une date au format YYYY-MM-DD
     */
    private function parseDate(string $dateString): \DateTime
    {
        try {
            return new \DateTime($dateString);
        } catch (\Exception) {
            return new \DateTime(); // Date actuelle par défaut
        }
    }

    /**
     * Détermine le type de version (major, minor, patch)
     */
    private function determineVersionType(string $version): string
    {
        $parts = explode('.', $version);
        if (count($parts) < 3) {
            return 'minor';
        }

        $major = (int)$parts[0];
        $minor = (int)$parts[1];
        $patch = (int)$parts[2];

        // Version majeure si X.0.0 ou changement de version majeure
        if ($minor === 0 && $patch === 0) {
            return 'major';
        }

        // Version mineure si X.Y.0
        if ($patch === 0) {
            return 'minor';
        }

        // Sinon c'est un patch
        return 'patch';
    }

    /**
     * Normalise le type de section
     */
    private function normalizeSectionType(string $sectionTitle): string
    {
        $normalized = strtolower(trim($sectionTitle));

        // Supprimer les emojis
        $normalized = preg_replace('/[\x{1F600}-\x{1F64F}]|[\x{1F300}-\x{1F5FF}]|[\x{1F680}-\x{1F6FF}]|[\x{1F1E0}-\x{1F1FF}]|[\x{2600}-\x{26FF}]|[\x{2700}-\x{27BF}]/u', '', $normalized);
        $normalized = trim($normalized);

        // Mapping des sections communes
        $mapping = [
            'nouveautés' => 'features',
            'nouvelles fonctionnalités' => 'features',
            'fonctionnalités' => 'features',
            'améliorations' => 'improvements',
            'corrections' => 'fixes',
            'correctifs' => 'fixes',
            'bugs' => 'fixes',
            'sécurité' => 'security',
            'changements importants' => 'breaking_changes',
            'breaking changes' => 'breaking_changes',
            'déprécié' => 'deprecated',
            'supprimé' => 'removed'
        ];

        return $mapping[$normalized] ?? 'other';
    }

    /**
     * Mappe le type de section au type de fonctionnalité
     */
    private function mapSectionToFeatureType(string $sectionType): string
    {
        $mapping = [
            'features' => 'feature',
            'improvements' => 'improvement',
            'fixes' => 'fix',
            'security' => 'security',
            'breaking_changes' => 'breaking',
            'deprecated' => 'deprecated',
            'removed' => 'removed'
        ];

        return $mapping[$sectionType] ?? 'other';
    }

    /**
     * Extrait un titre à partir d'une description
     */
    private function extractTitleFromDescription(string $description): string
    {
        // Prendre les premiers mots jusqu'à deux points ou première phrase
        if (str_contains($description, ':')) {
            return trim(explode(':', $description)[0]);
        }

        // Prendre les 5 premiers mots maximum
        $words = explode(' ', $description);
        return implode(' ', array_slice($words, 0, min(5, count($words))));
    }

    /**
     * Traite les entrées pour les formater correctement
     */
    private function processEntries(array $entries): array
    {
        return array_map(function ($entry) {
            // Combiner toutes les fonctionnalités de toutes les sections
            $allFeatures = [];
            $breakingChanges = [];

            foreach ($entry['sections'] as $sectionType => $items) {
                if ($sectionType === 'breaking_changes') {
                    $breakingChanges = array_merge($breakingChanges, array_column($items, 'description'));
                } else {
                    $allFeatures = array_merge($allFeatures, $items);
                }
            }

            // Générer titre et description automatiquement si pas présents
            $title = $this->generateEntryTitle($entry, $allFeatures);
            $description = $this->generateEntryDescription($entry, $allFeatures);

            return [
                'version' => $entry['version'],
                'date' => $entry['date'],
                'type' => $entry['type'],
                'title' => $title,
                'description' => $description,
                'features' => $allFeatures,
                'breaking_changes' => $breakingChanges
            ];
        }, $entries);
    }

    /**
     * Génère un titre pour l'entrée
     */
    private function generateEntryTitle(array $entry, array $features): string
    {
        $featureCount = count($features);
        $type = $entry['type'];

        if ($type === 'major') {
            return 'Version Majeure avec ' . $featureCount . ' Nouveautés';
        }

        if ($type === 'minor') {
            return 'Nouvelles Fonctionnalités & Améliorations';
        }

        return 'Correctifs & Améliorations';
    }

    /**
     * Génère une description pour l'entrée
     */
    private function generateEntryDescription(array $entry, array $features): string
    {
        $featureCount = count($features);
        $type = $entry['type'];

        if ($featureCount === 0) {
            return 'Mise à jour technique et optimisations diverses.';
        }

        $descriptions = [
            'major' => "Version majeure incluant {$featureCount} nouveautés et améliorations importantes.",
            'minor' => "Nouvelles fonctionnalités et améliorations pour une meilleure expérience utilisateur.",
            'patch' => "Correctifs et optimisations pour améliorer la stabilité et les performances."
        ];

        return $descriptions[$type] ?? "Mise à jour avec {$featureCount} changements.";
    }

    /**
     * Obtient la dernière version
     */
    public function getLatestVersion(): ?array
    {
        $entries = $this->parse();
        return $entries[0] ?? null;
    }

    /**
     * Obtient les statistiques du changelog
     */
    public function getStats(): array
    {
        $entries = $this->parse();

        return [
            'total_versions' => count($entries),
            'major_versions' => count(array_filter($entries, fn($e) => $e['type'] === 'major')),
            'minor_versions' => count(array_filter($entries, fn($e) => $e['type'] === 'minor')),
            'patch_versions' => count(array_filter($entries, fn($e) => $e['type'] === 'patch')),
            'total_features' => array_sum(array_map(fn($e) => count($e['features']), $entries)),
            'latest_version' => $entries[0]['version'] ?? null,
            'latest_date' => $entries[0]['date'] ?? null
        ];
    }
}
