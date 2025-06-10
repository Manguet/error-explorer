<?php

namespace App\Controller;

use App\Entity\Team;
use App\Entity\TeamMember;
use App\Entity\User;
use App\Form\TeamInviteType;
use App\Form\TeamMemberType;
use App\Form\TeamType;
use App\Repository\TeamMemberRepository;
use App\Repository\TeamRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/dashboard/teams')]
#[IsGranted('ROLE_USER')]
class TeamController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private TeamRepository $teamRepository,
        private TeamMemberRepository $teamMemberRepository,
        private UserRepository $userRepository
    ) {
    }

    #[Route('', name: 'team_index', methods: ['GET'])]
    public function index(): Response
    {
        $user = $this->getUser();
        $teams = $this->teamRepository->findByUser($user);
        $ownedTeams = $this->teamRepository->findOwnedByUser($user);
        
        return $this->render('dashboard/teams/index.html.twig', [
            'teams' => $teams,
            'owned_teams' => $ownedTeams,
        ]);
    }

    #[Route('/new', name: 'team_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $team = new Team();
        $form = $this->createForm(TeamType::class, $team);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $team->setOwner($this->getUser());
            
            $this->entityManager->persist($team);
            $this->entityManager->flush();

            $this->addFlash('success', 'L\'équipe a été créée avec succès.');
            
            return $this->redirectToRoute('team_show', ['slug' => $team->getSlug()]);
        }

        return $this->render('dashboard/teams/new.html.twig', [
            'team' => $team,
            'form' => $form,
        ]);
    }

    #[Route('/{slug}', name: 'team_show', methods: ['GET'])]
    public function show(string $slug): Response
    {
        $team = $this->teamRepository->findBySlug($slug);
        
        if (!$team) {
            throw $this->createNotFoundException('Équipe non trouvée.');
        }

        $user = $this->getUser();
        
        if (!$team->isMember($user)) {
            throw $this->createAccessDeniedException('Vous n\'avez pas accès à cette équipe.');
        }

        $members = $this->teamMemberRepository->findActiveByTeam($team);
        $stats = $this->teamRepository->getTeamStats($team);
        $memberStats = $this->teamMemberRepository->getTeamMemberStats($team);
        
        return $this->render('dashboard/teams/show.html.twig', [
            'team' => $team,
            'members' => $members,
            'stats' => $stats,
            'member_stats' => $memberStats,
            'user_role' => $team->getMemberRole($user),
        ]);
    }

    #[Route('/{slug}/edit', name: 'team_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, string $slug): Response
    {
        $team = $this->teamRepository->findBySlug($slug);
        
        if (!$team) {
            throw $this->createNotFoundException('Équipe non trouvée.');
        }

        $user = $this->getUser();
        
        if (!$team->hasPermission($user, 'manage_team')) {
            throw $this->createAccessDeniedException('Vous n\'avez pas les permissions pour modifier cette équipe.');
        }

        $form = $this->createForm(TeamType::class, $team);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();

            $this->addFlash('success', 'L\'équipe a été modifiée avec succès.');
            
            return $this->redirectToRoute('team_show', ['slug' => $team->getSlug()]);
        }

        return $this->render('dashboard/teams/edit.html.twig', [
            'team' => $team,
            'form' => $form,
        ]);
    }

    #[Route('/{slug}/members', name: 'team_members', methods: ['GET'])]
    public function members(string $slug): Response
    {
        $team = $this->teamRepository->findBySlug($slug);
        
        if (!$team) {
            throw $this->createNotFoundException('Équipe non trouvée.');
        }

        $user = $this->getUser();
        
        if (!$team->isMember($user)) {
            throw $this->createAccessDeniedException('Vous n\'avez pas accès à cette équipe.');
        }

        $members = $this->teamMemberRepository->findActiveByTeam($team);
        $recentlyActive = $this->teamMemberRepository->findRecentlyActiveByTeam($team);
        $inactive = $this->teamMemberRepository->findInactiveByTeam($team);
        
        return $this->render('dashboard/teams/members.html.twig', [
            'team' => $team,
            'members' => $members,
            'recently_active' => $recentlyActive,
            'inactive' => $inactive,
            'user_role' => $team->getMemberRole($user),
        ]);
    }

    #[Route('/{slug}/invite', name: 'team_invite', methods: ['GET', 'POST'])]
    public function invite(Request $request, string $slug): Response
    {
        $team = $this->teamRepository->findBySlug($slug);
        
        if (!$team) {
            throw $this->createNotFoundException('Équipe non trouvée.');
        }

        $user = $this->getUser();
        
        if (!$team->hasPermission($user, 'manage_members')) {
            throw $this->createAccessDeniedException('Vous n\'avez pas les permissions pour inviter des membres.');
        }

        if (!$team->canAddMember()) {
            $this->addFlash('error', 'L\'équipe a atteint son nombre maximum de membres.');
            return $this->redirectToRoute('team_members', ['slug' => $slug]);
        }

        $form = $this->createForm(TeamInviteType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $email = $data['email'];
            $role = $data['role'];
            
            // Check if user exists
            $invitedUser = $this->userRepository->findOneBy(['email' => $email]);
            
            if (!$invitedUser) {
                $this->addFlash('error', 'Aucun utilisateur trouvé avec cette adresse email.');
                return $this->render('dashboard/teams/invite.html.twig', [
                    'team' => $team,
                    'form' => $form,
                ]);
            }

            // Check if user is already a member
            $existingMembership = $this->teamMemberRepository->findByTeamAndUser($team, $invitedUser);
            
            if ($existingMembership) {
                $this->addFlash('error', 'Cet utilisateur est déjà membre de l\'équipe.');
                return $this->render('dashboard/teams/invite.html.twig', [
                    'team' => $team,
                    'form' => $form,
                ]);
            }

            // Create team membership
            $teamMember = new TeamMember();
            $teamMember->setTeam($team);
            $teamMember->setUser($invitedUser);
            $teamMember->setRole($role);
            $teamMember->setInvitedBy($user);

            $this->entityManager->persist($teamMember);
            $this->entityManager->flush();

            $this->addFlash('success', sprintf('%s a été ajouté à l\'équipe avec le rôle %s.', 
                $invitedUser->getFullName(), 
                TeamMember::ROLES[$role]
            ));

            return $this->redirectToRoute('team_members', ['slug' => $slug]);
        }

        return $this->render('dashboard/teams/invite.html.twig', [
            'team' => $team,
            'form' => $form,
        ]);
    }

    #[Route('/{slug}/members/{memberId}/edit', name: 'team_member_edit', methods: ['GET', 'POST'])]
    public function editMember(Request $request, string $slug, int $memberId): Response
    {
        $team = $this->teamRepository->findBySlug($slug);
        
        if (!$team) {
            throw $this->createNotFoundException('Équipe non trouvée.');
        }

        $member = $this->teamMemberRepository->find($memberId);
        
        if (!$member || $member->getTeam() !== $team) {
            throw $this->createNotFoundException('Membre non trouvé.');
        }

        $user = $this->getUser();
        
        if (!$team->hasPermission($user, 'manage_members')) {
            throw $this->createAccessDeniedException('Vous n\'avez pas les permissions pour modifier les membres.');
        }

        // Prevent editing owner role
        if ($member->getRole() === TeamMember::ROLE_OWNER) {
            $this->addFlash('error', 'Le rôle du propriétaire ne peut pas être modifié.');
            return $this->redirectToRoute('team_members', ['slug' => $slug]);
        }

        $form = $this->createForm(TeamMemberType::class, $member, ['is_edit' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();

            $this->addFlash('success', 'Le rôle du membre a été modifié avec succès.');
            
            return $this->redirectToRoute('team_members', ['slug' => $slug]);
        }

        return $this->render('dashboard/teams/edit_member.html.twig', [
            'team' => $team,
            'member' => $member,
            'form' => $form,
        ]);
    }

    #[Route('/{slug}/members/{memberId}/remove', name: 'team_member_remove', methods: ['POST'])]
    public function removeMember(Request $request, string $slug, int $memberId): Response
    {
        $team = $this->teamRepository->findBySlug($slug);
        
        if (!$team) {
            throw $this->createNotFoundException('Équipe non trouvée.');
        }

        $member = $this->teamMemberRepository->find($memberId);
        
        if (!$member || $member->getTeam() !== $team) {
            throw $this->createNotFoundException('Membre non trouvé.');
        }

        $user = $this->getUser();
        
        // Allow users to remove themselves or admins to remove others
        if ($member->getUser() !== $user && !$team->hasPermission($user, 'manage_members')) {
            throw $this->createAccessDeniedException('Vous n\'avez pas les permissions pour retirer ce membre.');
        }

        // Prevent removing owner
        if ($member->getRole() === TeamMember::ROLE_OWNER) {
            $this->addFlash('error', 'Le propriétaire ne peut pas être retiré de l\'équipe.');
            return $this->redirectToRoute('team_members', ['slug' => $slug]);
        }

        $memberName = $member->getUser()->getFullName();
        
        $this->entityManager->remove($member);
        $this->entityManager->flush();

        if ($member->getUser() === $user) {
            $this->addFlash('success', 'Vous avez quitté l\'équipe.');
            return $this->redirectToRoute('team_index');
        } else {
            $this->addFlash('success', sprintf('%s a été retiré de l\'équipe.', $memberName));
            return $this->redirectToRoute('team_members', ['slug' => $slug]);
        }
    }

    #[Route('/{slug}/delete', name: 'team_delete', methods: ['POST'])]
    public function delete(Request $request, string $slug): Response
    {
        $team = $this->teamRepository->findBySlug($slug);
        
        if (!$team) {
            throw $this->createNotFoundException('Équipe non trouvée.');
        }

        $user = $this->getUser();
        
        if ($team->getOwner() !== $user) {
            throw $this->createAccessDeniedException('Seul le propriétaire peut supprimer l\'équipe.');
        }

        if ($this->isCsrfTokenValid('delete'.$team->getId(), $request->request->get('_token'))) {
            $teamName = $team->getName();
            
            $this->entityManager->remove($team);
            $this->entityManager->flush();

            $this->addFlash('success', sprintf('L\'équipe "%s" a été supprimée.', $teamName));
        }

        return $this->redirectToRoute('team_index');
    }
}