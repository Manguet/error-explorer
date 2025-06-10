<?php

namespace App\Controller\Admin;

use App\DataTable\Type\UserDataTableType;
use App\Entity\User;
use App\Form\UserType;
use App\Repository\PlanRepository;
use App\Repository\ProjectRepository;
use App\Repository\UserRepository;
use App\Service\ErrorLimitService;
use Doctrine\ORM\EntityManagerInterface;
use Omines\DataTablesBundle\DataTableFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/users', name: 'admin_users_')]
#[IsGranted('ROLE_ADMIN')]
class UserController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository,
        private readonly PlanRepository $planRepository,
        private readonly ProjectRepository $projectRepository,
        private readonly ErrorLimitService $errorLimitService,
        private readonly UserPasswordHasherInterface $passwordHasher
    ) {}

    #[Route('', name: 'index')]
    public function index(Request $request, DataTableFactory $dataTableFactory): Response
    {
        $table = $dataTableFactory->createFromType(UserDataTableType::class)->handleRequest($request);

        $stats = [
            'total_users' => $this->userRepository->count([]),
            'active_users' => $this->userRepository->count(['isActive' => true]),
            'users_this_month' => $this->userRepository->countUsersThisMonth(),
            'expired_plans' => count($this->userRepository->findUsersWithExpiringPlan(0)),
            'users_over_limits' => count($this->userRepository->findUsersOverLimits())
        ];

        if ($table->isCallback()) {
            return $table->getResponse();
        }

        return $this->render('admin/users/index.html.twig', [
            'datatable' => $table,
            'stats' => $stats,
        ]);
    }

    #[Route('/create', name: 'create')]
    public function create(Request $request): Response
    {
        $user = new User();
        $form = $this->createForm(UserType::class, $user, [
            'is_edit' => false,
            'is_admin' => true
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $plainPassword = $form->get('plainPassword')->getData();
                if ($plainPassword) {
                    $hashedPassword = $this->passwordHasher->hashPassword($user, $plainPassword);
                    $user->setPassword($hashedPassword);
                }

                $user->setCreatedAt(new \DateTime());
                $user->setUpdatedAt(new \DateTime());

                if (empty($user->getRoles())) {
                    $user->setRoles(['ROLE_USER']);
                }

                if (!$user->getPlan()) {
                    $freePlan = $this->planRepository->findFreePlan();
                    if ($freePlan) {
                        $user->setPlan($freePlan);
                    }
                }

                $this->entityManager->persist($user);
                $this->entityManager->flush();

                $this->addFlash('success', sprintf('Utilisateur "%s" créé avec succès !', $user->getFullName()));

                return $this->redirectToRoute('admin_users_show', ['id' => $user->getId()]);

            } catch (\Exception $e) {
                $this->addFlash('error', 'Erreur lors de la création de l\'utilisateur : ' . $e->getMessage());
            }
        }

        return $this->render('admin/users/create.html.twig', [
            'form' => $form->createView(),
            'user' => $user,
            'is_edit' => false
        ]);
    }

    #[Route('/{id}', name: 'show', requirements: ['id' => '\d+'])]
    public function show(int $id): Response
    {
        $user = $this->userRepository->find($id);

        if (!$user) {
            throw $this->createNotFoundException('Utilisateur non trouvé');
        }

        // Récupérer les projets de l'utilisateur
        $projects = $this->projectRepository->findByOwner($user, 10);

        // Statistiques d'usage
        $usageStats = $this->errorLimitService->getUsageStats($user);

        // Statistiques des projets
        $projectStats = $this->projectRepository->getStatsForUser($user);

        // Historique récent (projets avec erreurs récentes)
        $recentErrorProjects = $this->projectRepository->findWithRecentErrorsForUser($user, 7);

        // Récupérer les informations de facturation
        $subscription = $this->entityManager->getRepository(\App\Entity\Subscription::class)
            ->findOneBy(['user' => $user], ['createdAt' => 'DESC']);
        
        $paymentMethods = $this->entityManager->getRepository(\App\Entity\PaymentMethod::class)
            ->findBy(['user' => $user], ['isDefault' => 'DESC', 'createdAt' => 'DESC']);
        
        $invoices = [];
        if ($subscription) {
            $invoices = $this->entityManager->getRepository(\App\Entity\Invoice::class)
                ->findBy(['subscription' => $subscription], ['createdAt' => 'DESC'], 5);
        }

        return $this->render('admin/users/show.html.twig', [
            'user' => $user,
            'projects' => $projects,
            'usage_stats' => $usageStats,
            'project_stats' => $projectStats,
            'recent_error_projects' => $recentErrorProjects,
            'subscription' => $subscription,
            'payment_methods' => $paymentMethods,
            'recent_invoices' => $invoices,
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', requirements: ['id' => '\d+'])]
    public function edit(int $id, Request $request): Response
    {
        $user = $this->userRepository->find($id);

        if (!$user) {
            throw $this->createNotFoundException('Utilisateur non trouvé');
        }

        $form = $this->createForm(UserType::class, $user, [
            'is_edit' => true,
            'is_admin' => true
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                // Gestion du nouveau mot de passe
                $newPassword = $form->get('newPassword')->getData();
                if ($newPassword) {
                    $hashedPassword = $this->passwordHasher->hashPassword($user, $newPassword);
                    $user->setPassword($hashedPassword);
                }

                $user->setUpdatedAt(new \DateTime());

                $this->entityManager->flush();

                $this->addFlash('success', sprintf('Utilisateur "%s" mis à jour avec succès !', $user->getFullName()));

                return $this->redirectToRoute('admin_users_show', ['id' => $user->getId()]);

            } catch (\Exception $e) {
                $this->addFlash('error', 'Erreur lors de la mise à jour : ' . $e->getMessage());
            }
        }

        return $this->render('admin/users/create.html.twig', [
            'form' => $form->createView(),
            'user' => $user,
            'is_edit' => true
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST', 'DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $user = $this->userRepository->find($id);

        if (!$user) {
            return $this->json([
                'success' => false,
                'error' => 'Utilisateur non trouvé'
            ], 404);
        }

        // Vérifier si l'utilisateur a des projets
        $projectCount = $this->projectRepository->countByOwner($user);
        if ($projectCount > 0) {
            return $this->json([
                'success' => false,
                'error' => 'Impossible de supprimer cet utilisateur car il possède des projets. Supprimez d\'abord ses projets ou transférez-les.'
            ], 400);
        }

        // Vérifier qu'on ne supprime pas le dernier admin
        if (in_array('ROLE_ADMIN', $user->getRoles()) || in_array('ROLE_SUPER_ADMIN', $user->getRoles())) {
            $adminCount = $this->userRepository->createQueryBuilder('u')
                ->select('COUNT(u.id)')
                ->where('u.roles LIKE :admin OR u.roles LIKE :super_admin')
                ->setParameter('admin', '%ROLE_ADMIN%')
                ->setParameter('super_admin', '%ROLE_SUPER_ADMIN%')
                ->getQuery()
                ->getSingleScalarResult();

            if ($adminCount <= 1) {
                return $this->json([
                    'success' => false,
                    'error' => 'Impossible de supprimer le dernier administrateur'
                ], 400);
            }
        }

        try {
            $userName = $user->getFullName();
            $this->entityManager->remove($user);
            $this->entityManager->flush();

            return $this->json([
                'success' => true,
                'message' => sprintf('Utilisateur "%s" supprimé avec succès', $userName)
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => 'Erreur lors de la suppression : ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/{id}/toggle-status', name: 'toggle_status', methods: ['POST'])]
    public function toggleStatus(int $id): JsonResponse
    {
        $user = $this->userRepository->find($id);

        if (!$user) {
            return $this->json(['error' => 'Utilisateur non trouvé'], 404);
        }

        $user->setIsActive(!$user->isActive());
        $user->setUpdatedAt(new \DateTime());

        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'message' => $user->isActive() ? 'Utilisateur activé' : 'Utilisateur désactivé',
            'is_active' => $user->isActive()
        ]);
    }

    #[Route('/{id}/reset-limits', name: 'reset_limits', methods: ['POST'])]
    public function resetLimits(int $id): JsonResponse
    {
        $user = $this->userRepository->find($id);

        if (!$user) {
            return $this->json(['error' => 'Utilisateur non trouvé'], 404);
        }

        try {
            // Reset des compteurs utilisateur
            $user->resetMonthlyErrors();
            $user->setUpdatedAt(new \DateTime());

            // Reset des compteurs de ses projets
            $projects = $this->projectRepository->findByOwner($user);
            foreach ($projects as $project) {
                $project->setCurrentMonthErrors(0);
                $project->setMonthlyCounterResetAt(new \DateTime('first day of next month'));
            }

            $this->entityManager->flush();

            return $this->json([
                'success' => true,
                'message' => 'Limites réinitialisées avec succès'
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => 'Erreur lors de la réinitialisation : ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/{id}/extend-plan', name: 'extend_plan', methods: ['POST'])]
    public function extendPlan(int $id, Request $request): JsonResponse
    {
        $user = $this->userRepository->find($id);

        if (!$user) {
            return $this->json(['error' => 'Utilisateur non trouvé'], 404);
        }

        $data = json_decode($request->getContent(), true);
        $months = (int) ($data['months'] ?? 1);

        if ($months < 1 || $months > 12) {
            return $this->json(['error' => 'Nombre de mois invalide (1-12)'], 400);
        }

        try {
            $currentExpiry = $user->getPlanExpiresAt() ?: new \DateTime();

            // Si le plan est déjà expiré, partir d'aujourd'hui
            if ($currentExpiry < new \DateTime()) {
                $currentExpiry = new \DateTime();
            }

            $newExpiry = (clone $currentExpiry)->add(new \DateInterval("P{$months}M"));
            $user->setPlanExpiresAt($newExpiry);
            $user->setUpdatedAt(new \DateTime());

            $this->entityManager->flush();

            return $this->json([
                'success' => true,
                'message' => sprintf('Plan étendu de %d mois', $months),
                'new_expiry' => $newExpiry->format('d/m/Y H:i')
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => 'Erreur lors de l\'extension : ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/bulk-actions', name: 'bulk_actions', methods: ['POST'])]
    public function bulkActions(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $action = $data['action'] ?? '';
        $userIds = $data['user_ids'] ?? [];

        if (empty($userIds) || !is_array($userIds)) {
            return $this->json(['error' => 'Aucun utilisateur sélectionné'], 400);
        }

        $users = $this->userRepository->findBy(['id' => $userIds]);
        if (empty($users)) {
            return $this->json(['error' => 'Utilisateurs non trouvés'], 404);
        }

        try {
            $count = 0;
            switch ($action) {
                case 'activate':
                    foreach ($users as $user) {
                        $user->setIsActive(true);
                        $user->setUpdatedAt(new \DateTime());
                        $count++;
                    }
                    $message = sprintf('%d utilisateur(s) activé(s)', $count);
                    break;

                case 'deactivate':
                    foreach ($users as $user) {
                        $user->setIsActive(false);
                        $user->setUpdatedAt(new \DateTime());
                        $count++;
                    }
                    $message = sprintf('%d utilisateur(s) désactivé(s)', $count);
                    break;

                case 'reset_limits':
                    foreach ($users as $user) {
                        $user->resetMonthlyErrors();
                        $user->setUpdatedAt(new \DateTime());
                        $count++;
                    }
                    $message = sprintf('Limites réinitialisées pour %d utilisateur(s)', $count);
                    break;

                default:
                    return $this->json(['error' => 'Action non reconnue'], 400);
            }

            $this->entityManager->flush();

            return $this->json([
                'success' => true,
                'message' => $message,
                'count' => $count
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => 'Erreur lors de l\'action groupée : ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/export', name: 'export')]
    public function export(Request $request): Response
    {
        $format = $request->query->get('format', 'csv');

        $users = $this->userRepository->createQueryBuilder('u')
            ->leftJoin('u.plan', 'p')
            ->addSelect('p')
            ->orderBy('u.lastName', 'ASC')
            ->addOrderBy('u.firstName', 'ASC')
            ->getQuery()
            ->getResult();

        if ($format === 'json') {
            $data = [];
            foreach ($users as $user) {
                $data[] = [
                    'id' => $user->getId(),
                    'nom_complet' => $user->getFullName(),
                    'email' => $user->getEmail(),
                    'entreprise' => $user->getCompany(),
                    'plan' => $user->getPlan()?->getName(),
                    'actif' => $user->isActive(),
                    'verifie' => $user->isVerified(),
                    'projets' => $user->getCurrentProjectsCount(),
                    'erreurs_mensuelles' => $user->getCurrentMonthlyErrors(),
                    'inscription' => $user->getCreatedAt()?->format('Y-m-d H:i:s'),
                    'expiration_plan' => $user->getPlanExpiresAt()?->format('Y-m-d H:i:s')
                ];
            }

            $response = new Response(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $response->headers->set('Content-Type', 'application/json');
            $response->headers->set('Content-Disposition', 'attachment; filename="utilisateurs-' . date('Y-m-d') . '.json"');

            return $response;
        }

        // Export CSV par défaut
        $csv = "Nom complet;Email;Entreprise;Plan;Actif;Vérifié;Projets;Erreurs mensuelles;Inscription;Expiration plan\n";

        foreach ($users as $user) {
            $csv .= sprintf(
                "%s;%s;%s;%s;%s;%s;%d;%d;%s;%s\n",
                $user->getFullName(),
                $user->getEmail(),
                $user->getCompany() ?: '',
                $user->getPlan()?->getName() ?: '',
                $user->isActive() ? 'Oui' : 'Non',
                $user->isVerified() ? 'Oui' : 'Non',
                $user->getCurrentProjectsCount(),
                $user->getCurrentMonthlyErrors(),
                $user->getCreatedAt()?->format('d/m/Y H:i') ?: '',
                $user->getPlanExpiresAt()?->format('d/m/Y H:i') ?: ''
            );
        }

        $response = new Response($csv);
        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="utilisateurs-' . date('Y-m-d') . '.csv"');

        return $response;
    }
}
