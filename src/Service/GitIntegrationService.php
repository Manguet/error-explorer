<?php

namespace App\Service;

use App\Entity\Project;
use Bitbucket\Client as BitbucketClient;
use Github\AuthMethod;
use Github\Client as GithubClient;
use Gitlab\Client as GitlabClient;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Contracts\Cache\ItemInterface;

class GitIntegrationService
{
    public function __construct(
        #[Autowire('%kernel.project_dir%')] private readonly string $projectDir
    ) {}

    public function fetchCodeSnippet(Project $project, string $commitHash, string $filePath, int $lineNumber, int $contextLines = 10): ?array
    {
        if (!$project->isGitConfigured()) {
            return null;
        }

        $cache = new FilesystemAdapter();
        $cacheKey = 'git.snippet.' . md5($project->getId() . $commitHash . $filePath . $lineNumber);

        return $cache->get($cacheKey, function (ItemInterface $item) use ($project, $commitHash, $filePath, $lineNumber, $contextLines) {
            $item->expiresAfter(3600); // Cache for 1 hour

            $result = match ($project->getGitProvider()) {
                'github' => $this->fetchFromGithub($project, $commitHash, $filePath, $lineNumber, $contextLines),
                'gitlab' => $this->fetchFromGitlab($project, $commitHash, $filePath, $lineNumber, $contextLines),
                'bitbucket' => $this->fetchFromBitbucket($project, $commitHash, $filePath, $lineNumber, $contextLines),
                default => null,
            };

            // Si échec avec 'main', essayer avec 'master'
            if (!$result && $commitHash === 'main') {
                $result = match ($project->getGitProvider()) {
                    'github' => $this->fetchFromGithub($project, 'master', $filePath, $lineNumber, $contextLines),
                    'gitlab' => $this->fetchFromGitlab($project, 'master', $filePath, $lineNumber, $contextLines),
                    'bitbucket' => $this->fetchFromBitbucket($project, 'master', $filePath, $lineNumber, $contextLines),
                    default => null,
                };
            }

            return $result;
        });
    }

    private function fetchFromGithub(Project $project, string $commitHash, string $filePath, int $lineNumber, int $contextLines): ?array
    {
        $client = $this->getGithubClient($project);
        if (!$client) {
            return null;
        }

        list($owner, $repo) = $this->parseGithubUrl($project->getRepositoryUrl());
        if (!$owner || !$repo) {
            return null;
        }

        try {
            $fileContent = $client->repo()->contents()->show($owner, $repo, $filePath, $commitHash);
            if ($fileContent['encoding'] !== 'base64') {
                return null;
            }

            $decodedContent = base64_decode($fileContent['content']);
            return $this->extractSnippet($decodedContent, $lineNumber, $contextLines);

        } catch (\Exception $e) {
            // Log pour debug
            error_log("GitHub API Error: " . $e->getMessage() . " - File: {$filePath}, Commit: {$commitHash}, Owner: {$owner}, Repo: {$repo}");
            return null;
        }
    }

    private function fetchFromGitlab(Project $project, string $commitHash, string $filePath, int $lineNumber, int $contextLines): ?array
    {
        $client = $this->getGitlabClient($project);
        if (!$client) {
            return null;
        }

        $projectPath = $this->parseGitlabUrl($project->getRepositoryUrl());
        if (!$projectPath) {
            return null;
        }

        try {
            $file = $client->repositoryFiles()->getRawFile($projectPath, $filePath, $commitHash);
            return $this->extractSnippet($file, $lineNumber, $contextLines);

        } catch (\Exception $e) {
            return null;
        }
    }

    private function fetchFromBitbucket(Project $project, string $commitHash, string $filePath, int $lineNumber, int $contextLines): ?array
    {
        $token = $this->decryptToken($project->getGitAccessToken());
        if (!$token) {
            return null;
        }

        list($workspace, $repo) = $this->parseBitbucketUrl($project->getRepositoryUrl());
        if (!$workspace || !$repo) {
            return null;
        }

        try {
            $client = new BitbucketClient();
            $client->authenticate($token, BitbucketClient::AUTH_HTTP_TOKEN);
            
            $response = $client->repositories()
                ->workspaces($workspace)
                ->src($repo)
                ->show($commitHash, $filePath);
            
            return $this->extractSnippet($response, $lineNumber, $contextLines);

        } catch (\Exception $e) {
            return null;
        }
    }

    private function extractSnippet(string $content, int $lineNumber, int $contextLines): array
    {
        $lines = explode("\n", $content);
        $startLine = max(0, $lineNumber - $contextLines - 1);
        $endLine = min(count($lines) - 1, $lineNumber + $contextLines - 1);

        $snippet = [];
        for ($i = $startLine; $i <= $endLine; $i++) {
            $snippet[] = [
                'line' => $i + 1,
                'content' => $lines[$i] ?? '',
                'is_error_line' => ($i + 1) === $lineNumber,
            ];
        }

        return $snippet;
    }

    private function getGithubClient(Project $project): ?GithubClient
    {
        $token = $this->decryptToken($project->getGitAccessToken());
        if (!$token) {
            return null;
        }

        $client = new GithubClient();
        $client->authenticate($token, null, AuthMethod::ACCESS_TOKEN);

        return $client;
    }

    private function getGitlabClient(Project $project): ?GitlabClient
    {
        $token = $this->decryptToken($project->getGitAccessToken());
        if (!$token) {
            return null;
        }

        $client = new GitlabClient();
        $client->authenticate($token, GitlabClient::AUTH_HTTP_TOKEN);

        return $client;
    }

    private function parseGithubUrl(string $url): array
    {
        if (preg_match('/github\.com[\/:]([^\/]+)\/([^\/\.]+)/', $url, $matches)) {
            return [$matches[1], $matches[2]];
        }
        return [null, null];
    }

    private function parseGitlabUrl(string $url): ?string
    {
        if (preg_match('/gitlab\.com[\/:](.+?)(?:\.git)?$/', $url, $matches)) {
            return $matches[1];
        }
        return null;
    }

    private function parseBitbucketUrl(string $url): array
    {
        if (preg_match('/bitbucket\.org[\/:]([^\/]+)\/([^\/\.]+)/', $url, $matches)) {
            return [$matches[1], $matches[2]];
        }
        return [null, null];
    }

    public function encryptToken(string $token): string
    {
        $key = $this->getEncryptionKey();
        $iv = random_bytes(16);
        $encrypted = openssl_encrypt($token, 'AES-256-CBC', $key, 0, $iv);
        return base64_encode($iv . $encrypted);
    }

    public function decryptToken(?string $encryptedToken): ?string
    {
        if ($encryptedToken === null) {
            return null;
        }
        
        try {
            $key = $this->getEncryptionKey();
            $data = base64_decode($encryptedToken);
            $iv = substr($data, 0, 16);
            $encrypted = substr($data, 16);
            return openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);
        } catch (\Exception $e) {
            return null;
        }
    }

    private function getEncryptionKey(): string
    {
        $appSecret = $_ENV['APP_SECRET'] ?? 'default-secret-key';
        return hash('sha256', $appSecret . 'git-integration', true);
    }
}