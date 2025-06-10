<?php

namespace App\Controller\Admin;

use App\Entity\Team;
use App\Entity\TeamMember;
use App\Form\TeamType;
use App\Repository\TeamMemberRepository;
use App\Repository\TeamRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/teams')]
#[IsGranted('ROLE_ADMIN')]
class TeamAdminController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private TeamRepository $teamRepository,
        private TeamMemberRepository $teamMemberRepository
    ) {
    }

    #[Route('', name: 'admin_team_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $search = $request->query->get('search', '');
        
        if ($search) {
            $teams = $this->teamRepository->search($search);
        } else {
            $teams = $this->teamRepository->findAllWithStats();
        }

        $totalTeams = count($this->teamRepository->findAll());
        $activeTeams = count($this->teamRepository->findBy(['isActive' => true]));
        $teamsNeedingAttention = $this->teamRepository->findTeamsNeedingAttention();
        
        return $this->render('admin/teams/index.html.twig', [
            'teams' => $teams,
            'search' => $search,
            'stats' => [
                'total_teams' => $totalTeams,
                'active_teams' => $activeTeams,
                'teams_needing_attention' => count($teamsNeedingAttention),
            ],
        ]);
    }

    #[Route('/{id}', name: 'admin_team_show', methods: ['GET'])]
    public function show(Team $team): Response
    {
        $members = $this->teamMemberRepository->findActiveByTeam($team);
        $stats = $this->teamRepository->getTeamStats($team);
        $memberStats = $this->teamMemberRepository->getTeamMemberStats($team);
        $recentlyActive = $this->teamMemberRepository->findRecentlyActiveByTeam($team);
        $inactive = $this->teamMemberRepository->findInactiveByTeam($team);
        
        return $this->render('admin/teams/show.html.twig', [
            'team' => $team,
            'members' => $members,
            'stats' => $stats,
            'member_stats' => $memberStats,
            'recently_active' => $recentlyActive,
            'inactive' => $inactive,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_team_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Team $team): Response
    {
        $form = $this->createForm(TeamType::class, $team);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();

            $this->addFlash('success', 'L\'équipe a été modifiée avec succès.');
            
            return $this->redirectToRoute('admin_team_show', ['id' => $team->getId()]);
        }

        return $this->render('admin/teams/edit.html.twig', [
            'team' => $team,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/toggle-status', name: 'admin_team_toggle_status', methods: ['POST'])]
    public function toggleStatus(Request $request, Team $team): Response
    {
        if ($this->isCsrfTokenValid('toggle_status'.$team->getId(), $request->request->get('_token'))) {
            $team->setIsActive(!$team->isActive());
            $this->entityManager->flush();

            $status = $team->isActive() ? 'activée' : 'désactivée';
            $this->addFlash('success', sprintf('L\'équipe "%s" a été %s.', $team->getName(), $status));
        }

        return $this->redirectToRoute('admin_team_show', ['id' => $team->getId()]);
    }

    #[Route('/{id}/members/{memberId}/toggle-status', name: 'admin_team_member_toggle_status', methods: ['POST'])]
    public function toggleMemberStatus(Request $request, Team $team, int $memberId): Response
    {
        $member = $this->teamMemberRepository->find($memberId);
        
        if (!$member || $member->getTeam() !== $team) {
            throw $this->createNotFoundException('Membre non trouvé.');
        }

        if ($this->isCsrfTokenValid('toggle_member_status'.$member->getId(), $request->request->get('_token'))) {
            // Don't allow deactivating owner
            if ($member->getRole() === TeamMember::ROLE_OWNER) {
                $this->addFlash('error', 'Le propriétaire ne peut pas être désactivé.');
            } else {
                $member->setIsActive(!$member->isActive());
                $this->entityManager->flush();

                $status = $member->isActive() ? 'activé' : 'désactivé';
                $memberName = $member->getUser()->getFullName();
                $this->addFlash('success', sprintf('Le membre "%s" a été %s.', $memberName, $status));
            }
        }

        return $this->redirectToRoute('admin_team_show', ['id' => $team->getId()]);
    }

    #[Route('/{id}/delete', name: 'admin_team_delete', methods: ['POST'])]
    public function delete(Request $request, Team $team): Response
    {
        if ($this->isCsrfTokenValid('delete'.$team->getId(), $request->request->get('_token'))) {
            $teamName = $team->getName();
            
            $this->entityManager->remove($team);
            $this->entityManager->flush();

            $this->addFlash('success', sprintf('L\'équipe "%s" a été supprimée.', $teamName));
        }

        return $this->redirectToRoute('admin_team_index');
    }

    #[Route('/analytics', name: 'admin_team_analytics', methods: ['GET'])]
    public function analytics(): Response
    {
        $monthlyStats = $this->teamRepository->getMonthlyCreationStats(12);
        $memberStats = $this->teamMemberRepository->getMemberActivityStats(30);
        
        // Transform data for charts
        $teamCreationData = [];
        $memberActivityData = [];
        
        foreach ($monthlyStats as $stat) {
            $monthName = \DateTime::createFromFormat('!m', $stat['month'])->format('M');
            $teamCreationData[] = [
                'month' => $monthName . ' ' . $stat['year'],
                'count' => (int) $stat['count']
            ];
        }
        
        foreach ($memberStats as $stat) {
            $memberActivityData[] = [
                'date' => $stat['activityDate'],
                'active_members' => (int) $stat['activeMembers']
            ];
        }
        
        return $this->render('admin/teams/analytics.html.twig', [
            'team_creation_data' => $teamCreationData,
            'member_activity_data' => $memberActivityData,
        ]);
    }
}