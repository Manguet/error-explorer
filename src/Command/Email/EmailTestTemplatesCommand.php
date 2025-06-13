<?php

namespace App\Command\Email;

use App\Service\Email\EmailTemplateService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Commande pour tester tous les templates
 */
#[AsCommand(
    name: 'app:email:test-templates',
    description: 'Teste le rendu de tous les templates d\'email'
)]
class EmailTestTemplatesCommand extends Command
{
    public function __construct(
        private readonly EmailTemplateService $templateService
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('🧪 Test des Templates Email');

        $results = $this->templateService->testAllTemplates();

        $successCount = 0;
        $errorCount = 0;

        $table = new Table($io);
        $table->setHeaders(['Template', 'Statut', 'Temps (ms)', 'Erreur']);

        foreach ($results as $template => $result) {
            $status = $result['status'] === 'success' ? '✅' : '❌';
            $time = $result['render_time_ms'] ?? 'N/A';
            $error = $result['error'] ?? '';

            $table->addRow([
                $template,
                $status,
                is_numeric($time) ? number_format($time, 2) : $time,
                $error
            ]);

            if ($result['status'] === 'success') {
                $successCount++;
            } else {
                $errorCount++;
            }
        }

        $table->render();

        $io->newLine();
        $io->info("Résumé: $successCount succès, $errorCount erreurs");

        if ($errorCount > 0) {
            $io->warning("Certains templates ont des erreurs. Veuillez les corriger.");
            return Command::FAILURE;
        }

        $io->success('Tous les templates ont été testés avec succès!');
        return Command::SUCCESS;
    }
}
