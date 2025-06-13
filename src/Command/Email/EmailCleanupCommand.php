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
 * Commande pour nettoyer les anciennes métriques
 */
#[AsCommand(
    name: 'app:email:cleanup',
    description: 'Nettoie les anciennes métriques d\'email'
)]
class EmailCleanupCommand extends Command
{
    public function __construct(
        private readonly EmailMonitoringService $monitoringService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('days', 'd', InputOption::VALUE_REQUIRED, 'Nombre de jours à conserver', 90)
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Simulation sans suppression réelle');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $days = (int) $input->getOption('days');
        $dryRun = $input->getOption('dry-run');

        $io->title("🧹 Nettoyage des métriques email");

        if ($dryRun) {
            $io->note("Mode simulation activé - aucune suppression ne sera effectuée");
        }

        if (!$dryRun) {
            $io->caution("Cette action supprimera définitivement les métriques de plus de $days jours");

            if (!$io->confirm('Êtes-vous sûr de vouloir continuer?')) {
                $io->info('Opération annulée');
                return Command::SUCCESS;
            }
        }

        try {
            if ($dryRun) {
                $io->info("En mode simulation, $days jours de métriques seraient conservés");
                $deletedCount = 0; // Simulation
            } else {
                $deletedCount = $this->monitoringService->cleanOldMetrics($days);
            }

            $io->success("$deletedCount enregistrements supprimés");

        } catch (\Exception $e) {
            $io->error("Erreur lors du nettoyage: " . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
