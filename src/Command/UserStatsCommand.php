<?php

namespace App\Command;

use App\Repository\UserRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:users:stats',
    description: 'Affiche les statistiques des utilisateurs',
)]
class UserStatsCommand extends Command
{
    public function __construct(
        private readonly UserRepository $userRepository
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('📊 Statistiques des utilisateurs');

        try {
            // Statistiques générales
            $stats = $this->userRepository->countByStatus();

            $io->section('Statistiques générales');
            $io->horizontalTable(
                ['Métrique', 'Valeur'],
                [
                    ['Total utilisateurs', $stats['total']],
                    ['Utilisateurs actifs', $stats['active']],
                    ['Utilisateurs vérifiés', $stats['verified']],
                    ['Utilisateurs actifs et vérifiés', $stats['active_verified']],
                    ['Utilisateurs inactifs', $stats['inactive']],
                    ['Utilisateurs non vérifiés', $stats['unverified']],
                ]
            );

            // Utilisateurs récents
            $recentUsers = $this->userRepository->findRecentlyRegistered(5);
            if (!empty($recentUsers)) {
                $io->section('Derniers utilisateurs inscrits');
                $recentData = [];
                foreach ($recentUsers as $user) {
                    $recentData[] = [
                        $user->getEmail(),
                        $user->getFullName(),
                        $user->getPlan()?->getName() ?? 'Aucun',
                        $user->isVerified() ? '✅' : '❌',
                        $user->getCreatedAt()->format('Y-m-d H:i')
                    ];
                }
                $io->table(
                    ['Email', 'Nom', 'Plan', 'Vérifié', 'Inscrit le'],
                    $recentData
                );
            }

            // Utilisateurs actifs récents
            $activeUsers = $this->userRepository->findRecentlyActive(24);
            if (!empty($activeUsers)) {
                $io->section('Utilisateurs actifs (24h)');
                $io->text(sprintf('%d utilisateur(s) connecté(s) dans les dernières 24h', count($activeUsers)));
            }

            // Plans expirés ou expirant
            $expiringPlans = $this->userRepository->findExpiringPlans(7);
            if (!empty($expiringPlans)) {
                $io->section('Plans expirant dans 7 jours');
                $expiringData = [];
                foreach ($expiringPlans as $user) {
                    $expiringData[] = [
                        $user->getEmail(),
                        $user->getPlan()?->getName() ?? 'Inconnu',
                        $user->getPlanExpiresAt()?->format('Y-m-d H:i') ?? 'N/A',
                    ];
                }
                $io->table(
                    ['Email', 'Plan', 'Expire le'],
                    $expiringData
                );
            }

            $expiredPlans = $this->userRepository->findExpiredPlans();
            if (!empty($expiredPlans)) {
                $io->section('Plans expirés');
                $io->warning(sprintf('%d utilisateur(s) ont un plan expiré', count($expiredPlans)));
            }

            $io->success('📈 Statistiques générées avec succès !');

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $io->error('Erreur lors de la génération des statistiques: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
