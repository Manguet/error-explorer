<?php

namespace App\Command;

use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:cleanup:tokens',
    description: 'Nettoie les tokens expirés (réinitialisation de mot de passe, vérification email)',
)]
class CleanupTokensCommand extends Command
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
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Affiche ce qui serait nettoyé sans effectuer les modifications')
            ->addOption('password-reset-hours', null, InputOption::VALUE_REQUIRED, 'Nombre d\'heures après lesquelles les tokens de réinitialisation expirent', 24)
            ->setHelp('Cette commande nettoie les tokens expirés pour maintenir la base de données propre et sécurisée.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = $input->getOption('dry-run');
        $passwordResetHours = (int) $input->getOption('password-reset-hours');

        $io->title('🧹 Nettoyage des tokens expirés');

        if ($dryRun) {
            $io->note('Mode DRY-RUN activé - Aucune modification ne sera effectuée');
        }

        try {
            // Nettoyer les tokens de réinitialisation de mot de passe expirés
            $expiredPasswordTokens = $this->userRepository->findExpiredPasswordResetTokens($passwordResetHours);
            $passwordTokenCount = count($expiredPasswordTokens);

            if ($passwordTokenCount > 0) {
                $io->section("Tokens de réinitialisation de mot de passe expirés: {$passwordTokenCount}");

                if (!$dryRun) {
                    foreach ($expiredPasswordTokens as $user) {
                        $user->clearPasswordResetToken();
                        $this->entityManager->persist($user);
                    }
                    $this->entityManager->flush();
                }

                $io->success("✅ {$passwordTokenCount} token(s) de réinitialisation nettoyé(s)");
            } else {
                $io->info('Aucun token de réinitialisation expiré trouvé');
            }

            // Log de l'opération
            $this->logger->info('Nettoyage des tokens effectué', [
                'password_tokens_cleaned' => $passwordTokenCount,
                'dry_run' => $dryRun,
                'password_reset_hours' => $passwordResetHours
            ]);

            $io->success('🎉 Nettoyage terminé avec succès !');

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $io->error('Erreur lors du nettoyage: ' . $e->getMessage());
            $this->logger->error('Erreur lors du nettoyage des tokens', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return Command::FAILURE;
        }
    }
}
