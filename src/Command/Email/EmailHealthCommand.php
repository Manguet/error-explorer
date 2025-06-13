<?php

namespace App\Command\Email;

use App\Service\Email\EmailMonitoringService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Commande pour générer un rapport de santé des emails
 */
#[AsCommand(
    name: 'app:email:health',
    description: 'Génère un rapport de santé du système d\'email'
)]
class EmailHealthCommand extends Command
{
    public function __construct(
        private readonly EmailMonitoringService $monitoringService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('json', 'j', InputOption::VALUE_NONE, 'Sortie au format JSON')
            ->addOption('save', 's', InputOption::VALUE_REQUIRED, 'Sauvegarder le rapport dans un fichier');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $jsonOutput = $input->getOption('json');
        $saveFile = $input->getOption('save');

        $report = $this->monitoringService->generateHealthReport();

        if ($jsonOutput) {
            $output->writeln(json_encode($report, JSON_PRETTY_PRINT));
        } else {
            $this->displayHealthReport($io, $report);
        }

        if ($saveFile) {
            file_put_contents($saveFile, json_encode($report, JSON_PRETTY_PRINT));
            $io->info("Rapport sauvegardé dans: $saveFile");
        }

        // Code de retour basé sur la santé
        return match($report['health']) {
            'healthy' => Command::SUCCESS,
            'warning' => Command::SUCCESS,
            'critical' => Command::FAILURE,
            default => Command::SUCCESS
        };
    }

    private function displayHealthReport(SymfonyStyle $io, array $report): void
    {
        $healthIcon = match($report['health']) {
            'healthy' => '✅',
            'warning' => '⚠️',
            'critical' => '🔴',
            default => '❓'
        };

        $io->title("$healthIcon Rapport de Santé Email - " . $report['health']);

        // Statistiques globales
        $stats = $report['global_stats'];
        $io->section('📊 Statistiques (24h)');
        $io->listing([
            "Total envoyé: " . number_format($stats['total_sent']),
            "Taux de succès: {$stats['success_rate']}%",
            "Tentatives moyennes: {$stats['avg_attempts']}",
            "Temps moyen: " . number_format($stats['avg_execution_time_ms'], 2) . "ms"
        ]);

        // Problèmes
        if (!empty($report['issues'])) {
            $io->section('⚠️  Problèmes Détectés');
            foreach ($report['issues'] as $issue) {
                $io->writeln("• {$issue['message']}");
            }
        }

        // Recommandations
        if (!empty($report['recommendations'])) {
            $io->section('💡 Recommandations');
            foreach ($report['recommendations'] as $recommendation) {
                $io->writeln("• $recommendation");
            }
        }

        $io->info("Rapport généré le: " . $report['timestamp']);
    }
}
