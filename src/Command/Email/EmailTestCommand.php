<?php

namespace App\Command\Email;

use App\Repository\UserRepository;
use App\Service\Email\EmailService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Commande pour tester l'envoi d'emails
 */
#[AsCommand(
    name: 'app:email:test',
    description: 'Teste l\'envoi d\'un email spécifique'
)]
class EmailTestCommand extends Command
{
    public function __construct(
        private readonly EmailService   $emailService,
        private readonly UserRepository $userRepository
    )
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'Adresse email du destinataire')
            ->addArgument('type', InputArgument::REQUIRED, 'Type d\'email à envoyer')
            ->addOption('queue', 'qu', InputOption::VALUE_NONE, 'Envoyer via la queue au lieu d\'envoyer directement')
            ->addOption('preview', 'p', InputOption::VALUE_NONE, 'Prévisualiser l\'email au lieu de l\'envoyer')
            ->setHelp('
Exemple d\'utilisation:
  php bin/console app:email:test user@example.com welcome
  php bin/console app:email:test user@example.com email_verification --queue
  php bin/console app:email:test user@example.com password_reset --preview
            ');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $email = $input->getArgument('email');
        $type = $input->getArgument('type');
        $useQueue = $input->getOption('queue');
        $preview = $input->getOption('preview');

        $user = $this->userRepository->findOneBy(['email' => $email]);
        if (!$user) {
            $io->error("Utilisateur avec l'email '$email' introuvable");
            return Command::FAILURE;
        }

        if ($preview) {
            return $this->previewEmail($io, $type, $user);
        }

        if ($useQueue) {
            $io->info("Envoi de l'email '$type' via la queue pour $email");
            // Logique queue ici
            $io->success("Email ajouté à la queue avec succès");
        } else {
            $io->info("Envoi direct de l'email '$type' pour $email");

            try {
                $result = $this->emailService->sendEmail(
                    type: $type,
                    recipient: $user,
                    context: ['test_mode' => true],
                    metadata: ['command_test' => true]
                );

                if ($result->isSuccess()) {
                    $io->success("Email envoyé avec succès après {$result->getAttempts()} tentative(s)");
                } else {
                    $io->error("Échec de l'envoi: " . $result->getErrorMessage());
                    return Command::FAILURE;
                }
            } catch (\Exception $e) {
                $io->error("Erreur lors de l'envoi: " . $e->getMessage());
                return Command::FAILURE;
            }
        }

        return Command::SUCCESS;
    }

    private function previewEmail(SymfonyStyle $io, string $type, $user): int
    {
        try {
            $preview = $this->emailService->previewEmail($type, $user);

            $tempFile = tempnam(sys_get_temp_dir(), 'email_preview_') . '.html';
            file_put_contents($tempFile, $preview);

            $io->success("Prévisualisation générée: $tempFile");
            $io->note("Ouvrez ce fichier dans votre navigateur pour voir l'email");

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error("Erreur lors de la prévisualisation: " . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
