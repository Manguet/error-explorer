<?php

namespace App\Command\Clean;

use App\Service\Logs\NotificationService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:notifications:cleanup',
    description: 'Nettoyer les notifications expirées et anciennes'
)]
class NotificationCleanupCommand extends Command
{
    public function __construct(
        private readonly NotificationService $notificationService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('days', 'd', InputOption::VALUE_OPTIONAL, 'Supprimer les notifications plus anciennes que X jours', 30)
            ->addOption('expired-only', 'e', InputOption::VALUE_NONE, 'Supprimer uniquement les notifications expirées')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Afficher ce qui serait supprimé sans le faire');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $days = (int) $input->getOption('days');
        $expiredOnly = $input->getOption('expired-only');
        $dryRun = $input->getOption('dry-run');

        if ($dryRun) {
            $io->note('Mode dry-run activé - aucune suppression ne sera effectuée');
        }

        $deletedCount = 0;

        // Nettoyage des notifications expirées
        if (!$dryRun) {
            $deletedExpired = $this->notificationService->cleanupExpired();
            $deletedCount += $deletedExpired;

            if ($deletedExpired > 0) {
                $io->success("$deletedExpired notifications expirées supprimées");
            } else {
                $io->info('Aucune notification expirée trouvée');
            }
        }

        // Nettoyage des notifications anciennes (si pas expired-only)
        if (!$expiredOnly && !$dryRun) {
            $deletedOld = $this->notificationService->cleanupOld($days);
            $deletedCount += $deletedOld;

            if ($deletedOld > 0) {
                $io->success("$deletedOld notifications anciennes (> $days jours) supprimées");
            } else {
                $io->info("Aucune notification ancienne (> $days jours) trouvée");
            }
        }

        if ($dryRun) {
            $io->info('Utilisez la commande sans --dry-run pour effectuer le nettoyage');
            return Command::SUCCESS;
        }

        if ($deletedCount === 0) {
            $io->info('Aucune notification à nettoyer');
        } else {
            $io->success("Total: $deletedCount notifications supprimées");
        }

        return Command::SUCCESS;
    }
}
