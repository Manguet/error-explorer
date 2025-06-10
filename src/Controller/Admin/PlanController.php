<?php

namespace App\Controller\Admin;

use App\DataTable\Type\PlanDataTableType;
use App\Entity\Plan;
use App\Form\PlanType;
use App\Repository\PlanRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Omines\DataTablesBundle\DataTableFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin', name: 'admin_')]
#[IsGranted('ROLE_ADMIN')]
class PlanController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository,
        private readonly PlanRepository $planRepository
    ) {}

    #[Route('/plans', name: 'plans_index')]
    public function plansIndex(Request $request, DataTableFactory $dataTableFactory): Response
    {
        $table = $dataTableFactory->createFromType(PlanDataTableType::class)->handleRequest($request);

        if ($table->isCallback()) {
            return $table->getResponse();
        }

        $plans = $this->planRepository->findBy([], ['sortOrder' => 'ASC']);
        $plansStats = $this->planRepository->getPlansStats();

        return $this->render('admin/plans/index.html.twig', [
            'plans' => $plans,
            'stats' => $plansStats,
            'datatable' => $table
        ]);
    }

    #[Route('/plans/create', name: 'plans_create')]
    public function plansCreate(Request $request): Response
    {
        $plan = new Plan();
        $form = $this->createForm(PlanType::class, $plan);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $slug = $this->planRepository->generateUniqueSlug($plan->getName());
                $plan->setSlug($slug);

                $this->entityManager->persist($plan);
                $this->entityManager->flush();

                $this->addFlash('success', 'Plan créé avec succès !');

                return $this->redirectToRoute('admin_plans_index');

            } catch (\Exception $e) {
                $this->addFlash('error', 'Erreur lors de la création du plan : ' . $e->getMessage());
            }
        }

        return $this->render('admin/plans/create.html.twig', [
            'form' => $form->createView(),
            'plan' => $plan,
            'is_edit' => false
        ]);
    }

    #[Route('/plans/{id}/edit', name: 'plans_edit', requirements: ['id' => '\d+'])]
    public function plansEdit(int $id, Request $request): Response
    {
        $plan = $this->planRepository->find($id);

        if (!$plan) {
            $this->addFlash('error', 'Plan non trouvé');
            return $this->redirectToRoute('admin_plans_index');
        }

        $form = $this->createForm(PlanType::class, $plan);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $slug = $this->planRepository->generateUniqueSlug($plan->getName());
                $plan->setSlug($slug);

                $this->entityManager->persist($plan);
                $this->entityManager->flush();

                $this->addFlash('success', 'Plan modifié avec succès !');

                return $this->redirectToRoute('admin_plans_index');

            } catch (\Exception $e) {
                $this->addFlash('error', 'Erreur lors de la modification du plan : ' . $e->getMessage());
            }
        }

        return $this->render('admin/plans/create.html.twig', [
            'form' => $form->createView(),
            'plan' => $plan,
            'is_edit' => true
        ]);
    }

    #[Route('/plans/{id}/delete', name: 'plans_delete')]
    public function plansDelete(int $id): JsonResponse
    {
        $plan = $this->planRepository->find($id);

        if (!$plan) {
            return $this->json([
                'success' => false,
                'error' => 'Plan non trouvé'
            ], 404);
        }

        $usersWithPlan = $this->userRepository->findBy(['plan' => $plan]);
        if (count($usersWithPlan) > 0) {
            return $this->json([
                'success' => false,
                'error' => 'Impossible de supprimer ce plan car il est utilisé par des utilisateurs'
            ], 400);
        }

        try {
            $this->entityManager->remove($plan);
            $this->entityManager->flush();
        } catch (\Exception) {
            return $this->json([
                'success' => false,
                'error' => 'Erreur lors de la suppression du plan'
            ], 500);
        }

        return $this->json([
            'success' => true,
            'message' => 'Plan supprimé avec succès'
        ]);
    }


    #[Route('/plans/{id}/toggle-status', name: 'plans_toggle_status', methods: ['POST'])]
    public function togglePlanStatus(int $id): JsonResponse
    {
        $plan = $this->planRepository->find($id);

        if (!$plan) {
            return $this->json(['error' => 'Plan non trouvé'], 404);
        }

        $plan->setIsActive(!$plan->isActive());
        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'message' => $plan->isActive() ? 'Plan activé' : 'Plan désactivé',
            'is_active' => $plan->isActive()
        ]);
    }
}
