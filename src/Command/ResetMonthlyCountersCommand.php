<?php

namespace App\Command;

use App\Repository\UserRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:users:reset-monthly',
    description: 'Remet à zéro les compteurs mensuels des utilisateurs',
)]
class ResetMonthlyCountersCommand extends Command
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Affiche ce qui serait fait sans effectuer les modifications')
            ->setHelp('Cette commande remet à zéro les compteurs mensuels (erreurs, suggestions IA) de tous les utilisateurs. À exécuter via un cron job le 1er de chaque mois.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = $input->getOption('dry-run');

        $io->title('🔄 Remise à zéro des compteurs mensuels');

        if ($dryRun) {
            $io->note('Mode DRY-RUN activé - Aucune modification ne sera effectuée');
        }

        try {
            if (!$dryRun) {
                $affectedRows = $this->userRepository->resetMonthlyCounters();
                $io->success("✅ Compteurs remis à zéro pour {$affectedRows} utilisateur(s)");

                $this->logger->info('Compteurs mensuels remis à zéro', [
                    'affected_users' => $affectedRows,
                    'date' => new \DateTime()
                ]);
            } else {
                $io->info('🔍 Les compteurs mensuels seraient remis à zéro pour tous les utilisateurs');
            }

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $io->error('Erreur lors de la remise à zéro: ' . $e->getMessage());
            $this->logger->error('Erreur lors de la remise à zéro des compteurs', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return Command::FAILURE;
        }
    }
}
