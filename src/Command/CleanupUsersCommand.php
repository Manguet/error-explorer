<?php

namespace App\Command;

use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:users:cleanup',
    description: 'Supprime les utilisateurs non vérifiés anciens',
)]
class CleanupUsersCommand extends Command
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Affiche ce qui serait supprimé sans effectuer les suppressions')
            ->addOption('days-old', null, InputOption::VALUE_REQUIRED, 'Nombre de jours après lesquels les comptes non vérifiés sont supprimés', 30)
            ->setHelp('Cette commande supprime les utilisateurs non vérifiés anciens pour maintenir une base propre.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = $input->getOption('dry-run');
        $daysOld = (int) $input->getOption('days-old');

        $io->title('🗑️  Nettoyage des utilisateurs non vérifiés');

        if ($dryRun) {
            $io->note('Mode DRY-RUN activé - Aucune suppression ne sera effectuée');
        }

        $io->warning([
            'Cette commande va supprimer définitivement les utilisateurs non vérifiés',
            "créés il y a plus de {$daysOld} jours.",
            'Cette action est irréversible !'
        ]);

        try {
            // Trouver les utilisateurs non vérifiés anciens
            $unverifiedUsers = $this->userRepository->findUnverifiedUsers($daysOld);
            $userCount = count($unverifiedUsers);

            if ($userCount === 0) {
                $io->info('Aucun utilisateur non vérifié ancien trouvé');
                return Command::SUCCESS;
            }

            $io->section("Utilisateurs non vérifiés trouvés: {$userCount}");

            // Afficher la liste des utilisateurs
            $tableData = [];
            foreach ($unverifiedUsers as $user) {
                $tableData[] = [
                    $user->getId(),
                    $user->getEmail(),
                    $user->getFullName(),
                    $user->getCreatedAt()->format('Y-m-d H:i:s'),
                    $user->getCreatedAt()->diff(new \DateTime())->days . ' jours'
                ];
            }

            $io->table(
                ['ID', 'Email', 'Nom', 'Créé le', 'Age'],
                $tableData
            );

            if (!$dryRun) {
                if (!$io->confirm("Êtes-vous sûr de vouloir supprimer ces {$userCount} utilisateur(s) ?", false)) {
                    $io->info('Opération annulée');
                    return Command::SUCCESS;
                }

                // Supprimer les utilisateurs
                $deletedCount = $this->userRepository->deleteUnverifiedOldUsers($daysOld);

                $io->success("✅ {$deletedCount} utilisateur(s) supprimé(s)");
            } else {
                $io->info("🔍 {$userCount} utilisateur(s) seraient supprimé(s) sans le mode dry-run");
            }

            // Log de l'opération
            $this->logger->info('Nettoyage des utilisateurs effectué', [
                'users_deleted' => $dryRun ? 0 : ($deletedCount ?? 0),
                'users_found' => $userCount,
                'dry_run' => $dryRun,
                'days_old' => $daysOld
            ]);

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $io->error('Erreur lors du nettoyage: ' . $e->getMessage());
            $this->logger->error('Erreur lors du nettoyage des utilisateurs', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return Command::FAILURE;
        }
    }
}
