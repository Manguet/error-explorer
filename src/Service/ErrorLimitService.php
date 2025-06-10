<?php

namespace App\Service;

use App\Entity\Project;
use App\Entity\User;
use App\Repository\ProjectRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class ErrorLimitService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ProjectRepository $projectRepository,
        private UserRepository $userRepository,
        private LoggerInterface $logger
    ) {}

    /**
     * Vérifie si un projet peut recevoir une nouvelle erreur
     */
    public function canReceiveError(Project $project): bool
    {
        $owner = $project->getOwner();

        if (!$owner || !$owner->isActive()) {
            $this->logger->warning('Tentative d\'envoi d\'erreur pour un projet sans propriétaire actif', [
                'project_id' => $project->getId(),
                'project_slug' => $project->getSlug()
            ]);
            return false;
        }

        // Vérifier si le plan a expiré
        if ($owner->isPlanExpired()) {
            $this->logger->info('Erreur bloquée: plan expiré', [
                'user_id' => $owner->getId(),
                'project_id' => $project->getId(),
                'plan_expires_at' => $owner->getPlanExpiresAt()?->format('Y-m-d H:i:s')
            ]);
            return false;
        }

        $plan = $owner->getPlan();
        if (!$plan || !$plan->isActive()) {
            $this->logger->warning('Tentative d\'envoi d\'erreur pour un utilisateur sans plan actif', [
                'user_id' => $owner->getId(),
                'project_id' => $project->getId()
            ]);
            return false;
        }

        // Vérifier les limites mensuelles
        if (!$this->checkMonthlyLimit($project, $owner)) {
            return false;
        }

        return true;
    }

    /**
     * Vérifie les limites mensuelles d'erreurs
     */
    private function checkMonthlyLimit(Project $project, User $owner): bool
    {
        $plan = $owner->getPlan();

        // Plan illimité
        if ($plan?->getMaxMonthlyErrors() === -1) {
            return true;
        }

        // Reset les compteurs si nécessaire
        $this->resetCountersIfNeeded($project, $owner);

        // Vérifier la limite du projet
        if ($project->getCurrentMonthErrors() >= $plan?->getMaxMonthlyErrors()) {
            $this->logger->info('Erreur bloquée: limite mensuelle du projet atteinte', [
                'project_id' => $project->getId(),
                'current_errors' => $project->getCurrentMonthErrors(),
                'max_errors' => $plan?->getMaxMonthlyErrors()
            ]);
            return false;
        }

        // Vérifier la limite globale de l'utilisateur
        if ($owner->getCurrentMonthlyErrors() >= $plan?->getMaxMonthlyErrors()) {
            $this->logger->info('Erreur bloquée: limite mensuelle utilisateur atteinte', [
                'user_id' => $owner->getId(),
                'current_errors' => $owner->getCurrentMonthlyErrors(),
                'max_errors' => $plan?->getMaxMonthlyErrors()
            ]);
            return false;
        }

        return true;
    }

    /**
     * Incrémente les compteurs d'erreurs
     */
    public function incrementErrorCounters(Project $project): void
    {
        $owner = $project->getOwner();

        if (!$owner) {
            return;
        }

        // Reset les compteurs si nécessaire
        $this->resetCountersIfNeeded($project, $owner);

        // Incrémenter les compteurs
        $project->incrementMonthlyErrors();
        $owner->incrementMonthlyErrors();

        // Incrémenter aussi les compteurs totaux
        $project->incrementTotalOccurrences();

        $this->entityManager->flush();

        $this->logger->debug('Compteurs d\'erreurs incrémentés', [
            'project_id' => $project->getId(),
            'project_monthly_errors' => $project->getCurrentMonthErrors(),
            'user_monthly_errors' => $owner->getCurrentMonthlyErrors()
        ]);
    }

    /**
     * Reset les compteurs mensuels si nécessaire
     */
    private function resetCountersIfNeeded(Project $project, User $owner): void
    {
        $now = new \DateTime();
        $firstDayOfMonth = new \DateTime('first day of this month');

        // Reset compteur projet
        if (!$project->getMonthlyCounterResetAt() || $project->getMonthlyCounterResetAt() < $firstDayOfMonth) {
            $project->setCurrentMonthErrors(0);
            $project->setMonthlyCounterResetAt(new \DateTime('first day of next month'));

            $this->logger->info('Compteur mensuel projet réinitialisé', [
                'project_id' => $project->getId()
            ]);
        }

        // Reset compteur utilisateur (on peut ajouter un champ similaire à User si nécessaire)
        // Pour simplifier, on peut reset tous les utilisateurs avec une commande cron
    }

    /**
     * Vérifie si un utilisateur peut créer un nouveau projet
     */
    public function canCreateProject(User $user): bool
    {
        $plan = $user->getPlan();

        if (!$plan || !$plan->isActive()) {
            return false;
        }

        // Plan illimité
        if ($plan->getMaxProjects() === -1) {
            return true;
        }

        $currentProjects = $this->projectRepository->countActiveByOwner($user);

        if ($currentProjects >= $plan->getMaxProjects()) {
            $this->logger->info('Création de projet bloquée: limite atteinte', [
                'user_id' => $user->getId(),
                'current_projects' => $currentProjects,
                'max_projects' => $plan->getMaxProjects()
            ]);
            return false;
        }

        return true;
    }

    /**
     * Met à jour le compteur de projets d'un utilisateur
     */
    public function updateProjectCount(User $user): void
    {
        $activeProjects = $this->projectRepository->countActiveByOwner($user);
        $user->setCurrentProjectsCount($activeProjects);

        $this->entityManager->flush();
    }

    /**
     * Retourne les statistiques d'usage pour un utilisateur
     */
    public function getUsageStats(User $user): array
    {
        $plan = $user->getPlan();

        if (!$plan) {
            return [
                'projects' => ['current' => 0, 'max' => 0, 'percentage' => 0],
                'monthly_errors' => ['current' => 0, 'max' => 0, 'percentage' => 0],
                'plan_expired' => true
            ];
        }

        $currentProjects = $this->projectRepository->countActiveByOwner($user);
        $maxProjects = $plan->getMaxProjects();

        $projectsPercentage = $maxProjects > 0 ? round(($currentProjects / $maxProjects) * 100, 1) : 0;

        $currentMonthlyErrors = $user->getCurrentMonthlyErrors();
        $maxMonthlyErrors = $plan->getMaxMonthlyErrors();

        $errorsPercentage = $maxMonthlyErrors > 0 ? round(($currentMonthlyErrors / $maxMonthlyErrors) * 100, 1) : 0;

        return [
            'projects' => [
                'current' => $currentProjects,
                'max' => $maxProjects,
                'max_label' => $maxProjects === -1 ? 'Illimité' : $maxProjects,
                'percentage' => $projectsPercentage,
                'can_create_more' => $this->canCreateProject($user)
            ],
            'monthly_errors' => [
                'current' => $currentMonthlyErrors,
                'max' => $maxMonthlyErrors,
                'max_label' => $maxMonthlyErrors === -1 ? 'Illimité' : number_format($maxMonthlyErrors),
                'percentage' => $errorsPercentage,
                'can_receive_more' => $user->canReceiveError()
            ],
            'plan_expired' => $user->isPlanExpired(),
            'plan_expires_at' => $user->getPlanExpiresAt(),
            'plan_name' => $plan->getName()
        ];
    }

    /**
     * Génère un rapport d'usage pour tous les utilisateurs
     */
    public function generateUsageReport(): array
    {
        $users = $this->userRepository->findBy(['isActive' => true]);
        $report = [
            'total_users' => count($users),
            'users_over_project_limit' => 0,
            'users_over_error_limit' => 0,
            'users_with_expired_plan' => 0,
            'by_plan' => []
        ];

        foreach ($users as $user) {
            $stats = $this->getUsageStats($user);
            $planName = $user->getPlan()?->getName() ?? 'Aucun';

            if (!isset($report['by_plan'][$planName])) {
                $report['by_plan'][$planName] = [
                    'users' => 0,
                    'total_projects' => 0,
                    'total_monthly_errors' => 0
                ];
            }

            $report['by_plan'][$planName]['users']++;
            $report['by_plan'][$planName]['total_projects'] += $stats['projects']['current'];
            $report['by_plan'][$planName]['total_monthly_errors'] += $stats['monthly_errors']['current'];

            if ($stats['projects']['percentage'] >= 100) {
                $report['users_over_project_limit']++;
            }

            if ($stats['monthly_errors']['percentage'] >= 100) {
                $report['users_over_error_limit']++;
            }

            if ($stats['plan_expired']) {
                $report['users_with_expired_plan']++;
            }
        }

        return $report;
    }

    /**
     * Commande pour reset les compteurs mensuels (à exécuter via cron)
     */
    public function resetMonthlyCounters(): int
    {
        $resetUsers = $this->userRepository->resetMonthlyCounters();

        // Reset également les compteurs des projets
        $resetProjects = $this->entityManager->getConnection()
            ->executeStatement(
                'UPDATE projects SET current_month_errors = 0, monthly_counter_reset_at = :reset_date',
                ['reset_date' => new \DateTime('first day of next month')]
            );

        $this->logger->info('Compteurs mensuels réinitialisés', [
            'users_reset' => $resetUsers,
            'projects_reset' => $resetProjects
        ]);

        return $resetUsers;
    }
}
