<?php

namespace App\Controller\Dashboard;

use App\Entity\User;
use App\Form\Profile\ProfileEditFormType;
use App\Form\Profile\ChangePasswordFormType;
use App\Form\Profile\ProfilePreferencesFormType;
use App\Form\Profile\DeleteAccountFormType;
use App\Repository\UserRepository;
use App\Service\Email\EmailService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/dashboard/profile', name: 'dashboard_profile_')]
#[IsGranted('ROLE_USER')]
class ProfileController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly EmailService $emailService,
        private readonly UserRepository $userRepository
    ) {}

    #[Route('/', name: 'index')]
    public function index(): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        
        // Calculer les statistiques du profil
        $profileStats = [
            'projects_count' => $user->getCurrentProjectsCount(),
            'monthly_errors' => $user->getCurrentMonthlyErrors(),
            'monthly_ai_suggestions' => $user->getCurrentMonthlyAiSuggestions(),
            'teams_count' => $user->getOwnedTeams()->count() + $user->getTeamMemberships()->count(),
            'account_age_days' => $user->getCreatedAt()->diff(new \DateTime())->days,
            'login_count' => $user->getLoginCount(),
            'last_login' => $user->getLastLoginAt(),
            'verification_status' => $user->isVerified()
        ];

        return $this->render('dashboard/profile/index.html.twig', [
            'user' => $user,
            'profile_stats' => $profileStats
        ]);
    }

    #[Route('/edit', name: 'edit')]
    public function edit(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        
        $form = $this->createForm(ProfileEditFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();
            
            $this->addFlash('success', 'Votre profil a été mis à jour avec succès.');
            
            return $this->redirectToRoute('dashboard_profile_index');
        }

        return $this->render('dashboard/profile/edit.html.twig', [
            'form' => $form->createView(),
            'user' => $user
        ]);
    }

    #[Route('/password', name: 'password')]
    public function changePassword(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        
        $form = $this->createForm(ChangePasswordFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            
            // Vérifier l'ancien mot de passe
            if (!$this->passwordHasher->isPasswordValid($user, $data['current_password'])) {
                $this->addFlash('error', 'Le mot de passe actuel est incorrect.');
                return $this->redirectToRoute('dashboard_profile_password');
            }
            
            // Hasher et définir le nouveau mot de passe
            $hashedPassword = $this->passwordHasher->hashPassword($user, $data['new_password']);
            $user->setPassword($hashedPassword);
            
            $this->entityManager->flush();
            
            // Envoyer un email de confirmation
            $this->emailService->sendPasswordChangedEmail($user);
            
            $this->addFlash('success', 'Votre mot de passe a été modifié avec succès.');
            
            return $this->redirectToRoute('dashboard_profile_index');
        }

        return $this->render('dashboard/profile/password.html.twig', [
            'form' => $form->createView(),
            'user' => $user
        ]);
    }

    #[Route('/preferences', name: 'preferences')]
    public function preferences(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        
        $form = $this->createForm(ProfilePreferencesFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();
            
            $this->addFlash('success', 'Vos préférences ont été mises à jour avec succès.');
            
            return $this->redirectToRoute('dashboard_profile_preferences');
        }

        return $this->render('dashboard/profile/preferences.html.twig', [
            'form' => $form->createView(),
            'user' => $user
        ]);
    }

    #[Route('/delete', name: 'delete')]
    public function delete(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        
        $form = $this->createForm(DeleteAccountFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            
            // Vérifier le mot de passe
            if (!$this->passwordHasher->isPasswordValid($user, $data['password'])) {
                $this->addFlash('error', 'Le mot de passe est incorrect.');
                return $this->redirectToRoute('dashboard_profile_delete');
            }
            
            // Vérifier la phrase de confirmation
            if ($data['confirmation'] !== 'SUPPRIMER MON COMPTE') {
                $this->addFlash('error', 'La phrase de confirmation est incorrecte.');
                return $this->redirectToRoute('dashboard_profile_delete');
            }
            
            // Supprimer le compte
            $this->entityManager->remove($user);
            $this->entityManager->flush();
            
            // Déconnecter l'utilisateur
            $this->container->get('security.token_storage')->setToken(null);
            $request->getSession()->invalidate();
            
            $this->addFlash('success', 'Votre compte a été supprimé définitivement.');
            
            return $this->redirectToRoute('homepage');
        }

        return $this->render('dashboard/profile/delete.html.twig', [
            'form' => $form->createView(),
            'user' => $user
        ]);
    }
}