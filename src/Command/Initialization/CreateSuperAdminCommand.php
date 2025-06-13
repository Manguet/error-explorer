<?php

namespace App\Command\Initialization;

use App\Entity\Plan;
use App\Entity\User;
use App\Repository\PlanRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[AsCommand(
    name: 'app:create-super-admin',
    description: 'Crée un compte super administrateur avec tous les droits',
)]
class CreateSuperAdminCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly ValidatorInterface $validator,
        private readonly UserRepository $userRepository,
        private readonly PlanRepository $planRepository
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::OPTIONAL, 'Email du super admin')
            ->addArgument('password', InputArgument::OPTIONAL, 'Mot de passe du super admin')
            ->addOption('first-name', 'f', InputOption::VALUE_OPTIONAL, 'Prénom', 'Super')
            ->addOption('last-name', 'l', InputOption::VALUE_OPTIONAL, 'Nom', 'Admin')
            ->addOption('company', 'c', InputOption::VALUE_OPTIONAL, 'Entreprise', 'Error Explorer')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Force la création même si l\'email existe déjà')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $existingSuperAdmin = $this->userRepository->findOneBy(['roles' => ['ROLE_SUPER_ADMIN']]);

        if ($existingSuperAdmin && !$input->getOption('force')) {
            $io->error('Un super administrateur existe déjà.');
            return Command::FAILURE;
        }

        $io->title('🚀 Création d\'un compte Super Administrateur');

        // Récupérer ou demander l'email
        $email = $input->getArgument('email');
        if (!$email) {
            $question = new Question('Email du super admin: ');
            $question->setValidator(function ($answer) {
                if (!filter_var($answer, FILTER_VALIDATE_EMAIL)) {
                    throw new \RuntimeException('Email invalide');
                }
                return $answer;
            });
            $email = $io->askQuestion($question);
        }

        // Vérifier si l'utilisateur existe déjà
        $existingUser = $this->userRepository->findOneBy(['email' => $email]);
        if ($existingUser) {
            if (!$input->getOption('force')) {
                $io->error("Un utilisateur avec l'email '{$email}' existe déjà.");
                $io->note('Utilisez --force pour forcer la mise à jour vers super admin');
                return Command::FAILURE;
            }

            return $this->upgradeToSuperAdmin($existingUser, $io);
        }

        // Récupérer ou demander le mot de passe
        $password = $input->getArgument('password');
        if (!$password) {
            $question = new Question('Mot de passe du super admin: ');
            $question->setHidden(true);
            $question->setValidator(function ($answer) {
                if (strlen($answer) < 8) {
                    throw new \RuntimeException('Le mot de passe doit contenir au moins 8 caractères');
                }
                return $answer;
            });
            $password = $io->askQuestion($question);
        }

        // Récupérer le plan Enterprise ou créer un plan spécial
        $plan = $this->getOrCreateSuperAdminPlan();

        // Créer le super admin
        $user = new User();
        $user->setEmail($email)
            ->setFirstName($input->getOption('first-name'))
            ->setLastName($input->getOption('last-name'))
            ->setCompany($input->getOption('company'))
            ->setPlan($plan)
            ->setRoles(['ROLE_SUPER_ADMIN', 'ROLE_ADMIN', 'ROLE_USER'])
            ->setIsVerified(true)
            ->setIsActive(true);

        // Hasher le mot de passe
        $hashedPassword = $this->passwordHasher->hashPassword($user, $password);
        $user->setPassword($hashedPassword);

        // Valider l'entité
        $errors = $this->validator->validate($user);
        if (count($errors) > 0) {
            $io->error('Erreurs de validation:');
            foreach ($errors as $error) {
                $io->text('- ' . $error->getMessage());
            }
            return Command::FAILURE;
        }

        try {
            $this->entityManager->persist($user);
            $this->entityManager->flush();

            $io->success('Super administrateur créé avec succès !');

            $io->definitionList(
                ['Email' => $email],
                ['Nom complet' => $user->getFullName()],
                ['Entreprise' => $user->getCompany()],
                ['Plan' => $user->getPlan()->getName()],
                ['Rôles' => implode(', ', $user->getRoles())],
                ['URL admin' => '/admin']
            );

            $io->note([
                'Le super admin peut maintenant:',
                '• Accéder à l\'administration via /admin',
                '• Gérer tous les utilisateurs et plans',
                '• Voir toutes les statistiques du site',
                '• Modifier la configuration globale'
            ]);

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $io->error('Erreur lors de la création: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function upgradeToSuperAdmin(User $user, SymfonyStyle $io): int
    {
        $io->note("Mise à niveau de l'utilisateur '{$user->getEmail()}' vers super admin...");

        // Ajouter les rôles super admin
        $user->setRoles(['ROLE_SUPER_ADMIN', 'ROLE_ADMIN', 'ROLE_USER']);

        // S'assurer qu'il est actif et vérifié
        $user->setIsActive(true)
            ->setIsVerified(true);

        // Lui donner le plan le plus élevé
        $bestPlan = $this->getOrCreateSuperAdminPlan();
        $user->setPlan($bestPlan);

        try {
            $this->entityManager->flush();

            $io->success('Utilisateur mis à niveau vers super admin !');

            $io->definitionList(
                ['Email' => $user->getEmail()],
                ['Nom complet' => $user->getFullName()],
                ['Nouveaux rôles' => implode(', ', $user->getRoles())],
                ['Plan' => $user->getPlan()->getName()]
            );

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $io->error('Erreur lors de la mise à niveau: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function getOrCreateSuperAdminPlan(): Plan
    {
        // Chercher d'abord le plan Enterprise
        $plan = $this->planRepository->findOneBy(['slug' => 'enterprise']);

        if ($plan) {
            return $plan;
        }

        // Sinon chercher le plan avec les meilleures limites
        $plans = $this->planRepository->findBy(['isActive' => true], ['maxMonthlyErrors' => 'DESC']);

        if (!empty($plans)) {
            return $plans[0];
        }

        // En dernier recours, créer un plan super admin
        return $this->createSuperAdminPlan();
    }

    private function createSuperAdminPlan(): Plan
    {
        $plan = new Plan();
        $plan->setName('Super Admin')
            ->setSlug('super-admin')
            ->setDescription('Plan illimité pour les super administrateurs')
            ->setPriceMonthly('0.00')
            ->setMaxProjects(-1)
            ->setMaxMonthlyErrors(-1)
            ->setHasAdvancedFilters(true)
            ->setHasApiAccess(true)
            ->setHasEmailAlerts(true)
            ->setHasSlackIntegration(true)
            ->setHasPrioritySupport(true)
            ->setHasCustomRetention(true)
            ->setDataRetentionDays(365)
            ->setIsActive(true)
            ->setSortOrder(999)
            ->setFeatures('');

        $this->entityManager->persist($plan);

        return $plan;
    }
}
