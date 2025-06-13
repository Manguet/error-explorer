<?php

namespace App\Command\Email;

use App\Service\Email\EmailMonitoringService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Commande pour afficher les statistiques d'emails
 */
#[AsCommand(
    name: 'app:email:stats',
    description: 'Affiche les statistiques d\'envoi d\'emails'
)]
class EmailStatsCommand extends Command
{
    public function __construct(
        private readonly EmailMonitoringService $monitoringService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('since', 's', InputOption::VALUE_REQUIRED, 'Période depuis (ex: -7 days, -1 hour)', '-24 hours')
            ->addOption('type', 't', InputOption::VALUE_REQUIRED, 'Filtrer par type d\'email')
            ->addOption('detailed', 'd', InputOption::VALUE_NONE, 'Affichage détaillé')
            ->addOption('export', 'exp', InputOption::VALUE_REQUIRED, 'Exporter vers un fichier CSV');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $sinceString = $input->getOption('since');
        $since = new \DateTimeImmutable($sinceString);
        $detailed = $input->getOption('detailed');
        $exportFile = $input->getOption('export');

        $io->title("📊 Statistiques d'emails depuis " . $since->format('Y-m-d H:i:s'));

        // Statistiques globales
        $globalStats = $this->monitoringService->getGlobalStats($since);
        $this->displayGlobalStats($io, $globalStats);

        if ($detailed) {
            // Statistiques par type
            $typeStats = $this->monitoringService->getStatsByType($since);
            $this->displayTypeStats($io, $typeStats);

            // Statistiques par priorité
            $priorityStats = $this->monitoringService->getStatsByPriority($since);
            $this->displayPriorityStats($io, $priorityStats);

            // Détection de problèmes
            $issues = $this->monitoringService->detectIssues();
            $this->displayIssues($io, $issues);
        }

        if ($exportFile) {
            $this->exportStats($io, $exportFile, $since);
        }

        return Command::SUCCESS;
    }

    private function displayGlobalStats(SymfonyStyle $io, array $stats): void
    {
        $io->section('📈 Statistiques Globales');

        $table = new Table($io);
        $table->setHeaders(['Métrique', 'Valeur']);
        $table->addRows([
            ['Total envoyé', number_format($stats['total_sent'])],
            ['Taux de succès', $stats['success_rate'] . '%'],
            ['Tentatives moyennes', $stats['avg_attempts']],
            ['Temps moyen (ms)', number_format($stats['avg_execution_time_ms'], 2)],
            ['Temps max (ms)', number_format($stats['max_execution_time_ms'], 2)],
            ['Temps min (ms)', number_format($stats['min_execution_time_ms'], 2)]
        ]);
        $table->render();
    }

    private function displayTypeStats(SymfonyStyle $io, array $typeStats): void
    {
        if (empty($typeStats)) {
            return;
        }

        $io->section('📋 Statistiques par Type');

        $table = new Table($io);
        $table->setHeaders(['Type', 'Total', 'Succès %', 'Tentatives Moy.', 'Temps Moy. (ms)']);

        foreach ($typeStats as $type => $stats) {
            $table->addRow([
                $type,
                number_format($stats['total_sent']),
                $stats['success_rate'] . '%',
                $stats['avg_attempts'],
                number_format($stats['avg_execution_time_ms'], 2)
            ]);
        }

        $table->render();
    }

    private function displayPriorityStats(SymfonyStyle $io, array $priorityStats): void
    {
        if (empty($priorityStats)) {
            return;
        }

        $io->section('⚡ Statistiques par Priorité');

        $table = new Table($io);
        $table->setHeaders(['Priorité', 'Total', 'Succès %', 'Tentatives Moy.', 'Temps Moy. (ms)']);

        foreach ($priorityStats as $priority => $stats) {
            $icon = match($priority) {
                'high' => '🔴',
                'normal' => '🟡',
                'low' => '🟢',
                default => '⚪'
            };

            $table->addRow([
                $icon . ' ' . ucfirst($priority),
                number_format($stats['total_sent']),
                $stats['success_rate'] . '%',
                $stats['avg_attempts'],
                number_format($stats['avg_execution_time_ms'], 2)
            ]);
        }

        $table->render();
    }

    private function displayIssues(SymfonyStyle $io, array $issues): void
    {
        if (empty($issues)) {
            $io->success('✅ Aucun problème détecté');
            return;
        }

        $io->section('⚠️  Problèmes Détectés');

        foreach ($issues as $issue) {
            $icon = match($issue['severity']) {
                'high' => '🔴',
                'medium' => '🟡',
                'low' => '🟢',
                default => '⚪'
            };

            $io->writeln("$icon {$issue['message']}");
        }
    }

    private function exportStats(SymfonyStyle $io, string $filename, \DateTimeInterface $since): void
    {
        $io->info("Export des statistiques vers $filename...");

        // Logique d'export CSV
        $globalStats = $this->monitoringService->getGlobalStats($since);
        $typeStats = $this->monitoringService->getStatsByType($since);

        $fp = fopen($filename, 'wb');

        // Headers
        fputcsv($fp, ['Type', 'Statistique', 'Valeur']);

        // Stats globales
        foreach ($globalStats as $key => $value) {
            fputcsv($fp, ['Global', $key, $value]);
        }

        // Stats par type
        foreach ($typeStats as $type => $stats) {
            foreach ($stats as $key => $value) {
                fputcsv($fp, [$type, $key, $value]);
            }
        }

        fclose($fp);

        $io->success("Export terminé: $filename");
    }
}
