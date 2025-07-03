<?php

namespace App\Service\Error;

/**
 * Service responsable du formatage des données d'ErrorOccurrence
 * pour l'affichage dans le dashboard et les exports
 */
class ErrorOccurrenceFormatter
{
    /**
     * Formate l'usage mémoire en format lisible
     */
    public function formatMemoryUsage(?int $bytes): ?string
    {
        if (!$bytes) {
            return null;
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;

        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Formate le temps d'exécution en format lisible
     */
    public function formatExecutionTime(?float $seconds): ?string
    {
        if (!$seconds) {
            return null;
        }

        // Moins d'une seconde : afficher en millisecondes
        if ($seconds < 1) {
            return round($seconds * 1000) . ' ms';
        }

        // Plus d'une minute : afficher en minutes et secondes
        if ($seconds >= 60) {
            $minutes = floor($seconds / 60);
            $remainingSeconds = $seconds % 60;
            return sprintf('%d min %s s', $minutes, round($remainingSeconds, 1));
        }

        // Entre 1 seconde et 1 minute : afficher en secondes
        return round($seconds, 2) . ' s';
    }

    /**
     * Formate la stack trace pour l'affichage avec limitation de lignes
     */
    public function formatStackTrace(string $stackTrace, int $maxLines = 10, bool $includeLineNumbers = true): string
    {
        if (empty($stackTrace)) {
            return '';
        }

        $lines = explode("\n", $stackTrace);
        $limitedLines = array_slice($lines, 0, $maxLines);

        if ($includeLineNumbers) {
            $formattedLines = [];
            foreach ($limitedLines as $index => $line) {
                $formattedLines[] = sprintf('%2d: %s', $index + 1, $line);
            }
            return implode("\n", $formattedLines);
        }

        return implode("\n", $limitedLines);
    }

    /**
     * Retourne une version courte de la stack trace pour l'affichage en liste
     */
    public function getShortStackTrace(string $stackTrace, int $lines = 5): string
    {
        if (empty($stackTrace)) {
            return '';
        }

        $stackLines = explode("\n", $stackTrace);
        $shortStack = array_slice($stackLines, 0, $lines);

        return implode("\n", $shortStack);
    }

    /**
     * Formate l'adresse IP pour l'affichage (masquage partiel pour la confidentialité)
     */
    public function formatIpAddress(?string $ipAddress, bool $maskForPrivacy = false): ?string
    {
        if (!$ipAddress) {
            return null;
        }

        if (!$maskForPrivacy) {
            return $ipAddress;
        }

        // Masquer les derniers octets pour IPv4
        if (filter_var($ipAddress, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $parts = explode('.', $ipAddress);
            $parts[3] = 'xxx';
            return implode('.', $parts);
        }

        // Masquer les derniers segments pour IPv6
        if (filter_var($ipAddress, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $parts = explode(':', $ipAddress);
            $visibleParts = array_slice($parts, 0, 4);
            return implode(':', $visibleParts) . ':xxxx:xxxx:xxxx:xxxx';
        }

        return $ipAddress;
    }

    /**
     * Formate l'User-Agent pour l'affichage (extraction du navigateur principal)
     */
    public function formatUserAgent(?string $userAgent): ?string
    {
        if (!$userAgent) {
            return null;
        }

        // Extraire le navigateur principal
        $patterns = [
            '/Chrome\/([0-9.]+)/' => 'Chrome $1',
            '/Firefox\/([0-9.]+)/' => 'Firefox $1',
            '/Safari\/([0-9.]+)/' => 'Safari $1',
            '/Edge\/([0-9.]+)/' => 'Edge $1',
            '/OPR\/([0-9.]+)/' => 'Opera $1',
        ];

        foreach ($patterns as $pattern => $replacement) {
            if (preg_match($pattern, $userAgent, $matches)) {
                return preg_replace($pattern, $replacement, $userAgent);
            }
        }

        // Si aucun pattern ne correspond, retourner une version tronquée
        return strlen($userAgent) > 50 ? substr($userAgent, 0, 50) . '...' : $userAgent;
    }

    /**
     * Formate l'URL pour l'affichage (troncature et masquage des paramètres sensibles)
     */
    public function formatUrl(?string $url, int $maxLength = 80, bool $hideSensitiveParams = true): ?string
    {
        if (!$url) {
            return null;
        }

        if ($hideSensitiveParams) {
            $url = $this->hideSensitiveUrlParams($url);
        }

        if (strlen($url) <= $maxLength) {
            return $url;
        }

        // Garder le début et la fin de l'URL
        $start = substr($url, 0, $maxLength * 0.6);
        $end = substr($url, -($maxLength * 0.3));
        
        return $start . '...' . $end;
    }

    /**
     * Masque les paramètres sensibles dans une URL
     */
    private function hideSensitiveUrlParams(string $url): string
    {
        $sensitiveParams = ['token', 'password', 'api_key', 'secret', 'auth', 'session'];
        
        foreach ($sensitiveParams as $param) {
            $pattern = "/([?&]{$param}=)[^&]*/i";
            $url = preg_replace($pattern, '$1***', $url);
        }

        return $url;
    }

    /**
     * Formate la date relative (ex: "il y a 2 heures")
     */
    public function formatRelativeTime(\DateTimeInterface $dateTime): string
    {
        $now = new \DateTime();
        $diff = $now->diff($dateTime);

        if ($diff->y > 0) {
            return sprintf('il y a %d an%s', $diff->y, $diff->y > 1 ? 's' : '');
        }

        if ($diff->m > 0) {
            return sprintf('il y a %d mois', $diff->m);
        }

        if ($diff->d > 0) {
            return sprintf('il y a %d jour%s', $diff->d, $diff->d > 1 ? 's' : '');
        }

        if ($diff->h > 0) {
            return sprintf('il y a %d heure%s', $diff->h, $diff->h > 1 ? 's' : '');
        }

        if ($diff->i > 0) {
            return sprintf('il y a %d minute%s', $diff->i, $diff->i > 1 ? 's' : '');
        }

        return 'À l\'instant';
    }

    /**
     * Formate un tableau de contexte pour l'affichage
     */
    public function formatContextArray(array $context, int $maxDepth = 3): string
    {
        if (empty($context)) {
            return '';
        }

        return $this->formatArrayRecursive($context, 0, $maxDepth);
    }

    /**
     * Formater récursivement un tableau avec limitation de profondeur
     */
    private function formatArrayRecursive(array $array, int $currentDepth, int $maxDepth): string
    {
        if ($currentDepth >= $maxDepth) {
            return '[...profondeur max atteinte...]';
        }

        $result = [];
        foreach ($array as $key => $value) {
            $indent = str_repeat('  ', $currentDepth);
            
            if (is_array($value)) {
                $result[] = sprintf('%s%s: [', $indent, $key);
                $result[] = $this->formatArrayRecursive($value, $currentDepth + 1, $maxDepth);
                $result[] = $indent . ']';
            } elseif (is_object($value)) {
                $result[] = sprintf('%s%s: %s', $indent, $key, get_class($value));
            } elseif (is_string($value) && strlen($value) > 100) {
                $result[] = sprintf('%s%s: "%s..."', $indent, $key, substr($value, 0, 100));
            } else {
                $result[] = sprintf('%s%s: %s', $indent, $key, var_export($value, true));
            }
        }

        return implode("\n", $result);
    }
}