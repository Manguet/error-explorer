<?php

namespace App\Controller\Api;

use App\Entity\ErrorOccurrence;
use App\Entity\Project;
use App\Service\GitIntegrationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/project/{id}', name: 'api_project_')]
#[IsGranted('ROLE_USER')]
class CodeContextController extends AbstractController
{
    public function __construct(
        private readonly GitIntegrationService $gitIntegrationService,
        private readonly EntityManagerInterface $entityManager
    ) {}

    #[Route('/code-context/{errorId}', name: 'code_context', methods: ['GET'])]
    public function getCodeContext(Project $project, int $errorId): JsonResponse
    {
        // Verify project ownership
        if ($project->getOwner() !== $this->getUser()) {
            return $this->json(['error' => 'Access denied'], Response::HTTP_FORBIDDEN);
        }

        // Get the error occurrence
        $errorOccurrence = $this->entityManager
            ->getRepository(ErrorOccurrence::class)
            ->findOneBy(['id' => $errorId]);

        if (!$errorOccurrence || $errorOccurrence->getErrorGroup()->getProjectEntity()?->getId() !== $project->getId()) {
            return $this->json(['error' => 'Error not found'], Response::HTTP_NOT_FOUND);
        }

        // Check if project has Git configured
        if (!$project->isGitConfigured()) {
            return $this->json(['error' => 'Git integration not configured'], Response::HTTP_BAD_REQUEST);
        }

        $context = $errorOccurrence->getContext();
        $commitHash = $context['commit_hash'] ?? null;
        
        // Si pas de commit hash disponible, utiliser la branche principale
        if (!$commitHash) {
            $commitHash = 'main'; // Ou 'master' selon le dépôt
            
            // Essayer de détecter la branche principale si l'URL Git est GitHub
            $repoUrl = $project->getRepositoryUrl();
            if ($repoUrl) {
                // Pour GitHub, on peut essayer 'main' puis 'master' si échec
                $commitHash = 'main';
            }
        }

        // Get stack trace
        $stackTrace = $errorOccurrence->getStackTrace();
        if (empty($stackTrace)) {
            return $this->json(['error' => 'No stack trace available'], Response::HTTP_BAD_REQUEST);
        }

        // Parse stack trace if it's a string
        if (is_string($stackTrace)) {
            $stackTrace = $this->parseStackTraceString($stackTrace);
        }

        // Ajouter aussi le fichier principal de l'erreur en premier
        $mainErrorFile = $errorOccurrence->getErrorGroup()->getFile();
        $mainErrorLine = $errorOccurrence->getErrorGroup()->getLine();
        
        if ($mainErrorFile && $mainErrorLine) {
            array_unshift($stackTrace, [
                'file' => $mainErrorFile,
                'line' => $mainErrorLine,
                'function' => 'main_error',
                'class' => null
            ]);
        }

        $codeSnippets = [];
        $processedFiles = []; // Avoid fetching the same file multiple times

        foreach ($stackTrace as $frame) {
            $file = $frame['file'] ?? null;
            $line = $frame['line'] ?? null;

            if (!$file || !$line) {
                continue;
            }

            // Skip vendor/external files
            if (str_contains($file, 'vendor/') || str_contains($file, 'node_modules/')) {
                continue;
            }

            // Normaliser le chemin du fichier pour qu'il corresponde au dépôt Git
            $normalizedFile = $this->normalizeFilePathForGit($file);

            // Create unique key for this file/line combination
            $key = $normalizedFile . ':' . $line;
            if (isset($processedFiles[$key])) {
                continue;
            }

            // Try to fetch code snippet
            $snippet = $this->gitIntegrationService->fetchCodeSnippet(
                $project,
                $commitHash,
                $normalizedFile,
                (int) $line,
                10 // Context lines before/after
            );

            if ($snippet) {
                $codeSnippets[] = [
                    'file' => $file,
                    'normalized_file' => $normalizedFile,
                    'line' => $line,
                    'function' => $frame['function'] ?? null,
                    'class' => $frame['class'] ?? null,
                    'snippet' => $snippet
                ];
                $processedFiles[$key] = true;
            }

            // Limit to first 5 frames with code
            if (count($codeSnippets) >= 5) {
                break;
            }
        }

        return $this->json([
            'commit_hash' => $commitHash,
            'snippets' => $codeSnippets,
            'provider' => $project->getGitProvider()
        ]);
    }

    /**
     * Parse une stack trace sous forme de chaîne en array de frames
     */
    private function parseStackTraceString(string $stackTrace): array
    {
        $frames = [];
        $lines = explode("\n", $stackTrace);
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;
            
            // Pattern pour parser les lignes de stack trace PHP
            // Ex: "#0 /path/to/file.php(123): Class->method()"
            if (preg_match('/^#\d+\s+(.+?)\((\d+)\):\s*(.*)$/', $line, $matches)) {
                $file = $matches[1];
                $lineNumber = (int) $matches[2];
                $call = $matches[3];
                
                // Extraire la classe et la méthode si possible
                $class = null;
                $function = null;
                
                if (preg_match('/^(.+?)(->|::)(.+?)\(/', $call, $callMatches)) {
                    $class = $callMatches[1];
                    $function = $callMatches[3];
                } elseif (preg_match('/^(.+?)\(/', $call, $funcMatches)) {
                    $function = $funcMatches[1];
                }
                
                $frames[] = [
                    'file' => $file,
                    'line' => $lineNumber,
                    'class' => $class,
                    'function' => $function
                ];
            }
        }
        
        return $frames;
    }

    /**
     * Normalise le chemin d'un fichier pour qu'il corresponde à la structure du dépôt Git
     */
    private function normalizeFilePathForGit(string $filePath): string
    {
        // Supprimer les chemins absolus courants
        $patterns = [
            '/^\/var\/www\/[^\/]+\//',  // /var/www/project-name/
            '/^\/home\/[^\/]+\/[^\/]+\//', // /home/user/project/
            '/^\/app\//',               // Docker /app/
            '/^C:\\\\[^\\\\]+\\\\/',    // Windows C:\project\
        ];

        foreach ($patterns as $pattern) {
            $filePath = preg_replace($pattern, '', $filePath);
        }

        // Nettoyer les chemins qui commencent par des patterns connus
        $cleanPatterns = [
            'public/',
            'web/',
            'htdocs/',
            'www/',
        ];

        foreach ($cleanPatterns as $pattern) {
            if (str_starts_with($filePath, $pattern)) {
                $filePath = substr($filePath, strlen($pattern));
                break;
            }
        }

        // Normaliser les séparateurs de chemin
        $filePath = str_replace('\\', '/', $filePath);

        // Supprimer les doubles slashes
        $filePath = preg_replace('/\/+/', '/', $filePath);

        // Supprimer le slash initial s'il existe
        $filePath = ltrim($filePath, '/');

        return $filePath;
    }
}