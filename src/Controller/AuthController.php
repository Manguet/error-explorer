<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationFormType;
use App\Repository\PlanRepository;
use App\Repository\UserRepository;
use App\Service\EmailService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class AuthController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly PlanRepository $planRepository,
        private readonly UserRepository $userRepository,
        private readonly EmailService $emailService,
        private readonly LoggerInterface $logger
    ) {}

    #[Route('/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        // Si déjà connecté, rediriger
        if ($this->getUser()) {
            return $this->redirectToRoute('dashboard_index');
        }

        // Récupérer l'erreur de connexion s'il y en a une
        $error = $authenticationUtils->getLastAuthenticationError();

        // Dernier nom d'utilisateur entré par l'utilisateur
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('auth/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
        ]);
    }

    #[Route('/logout', name: 'app_logout')]
    public function logout(): void
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }

    #[Route('/register', name: 'app_register')]
    public function register(Request $request): Response
    {
        // Si déjà connecté, rediriger
        if ($this->getUser()) {
            return $this->redirectToRoute('dashboard_index');
        }

        // Créer une nouvelle instance utilisateur
        $user = new User();

        // Pré-sélectionner un plan basé sur l'URL
        $selectedPlanSlug = $request->query->get('plan', 'free');
        $selectedPlan = $this->planRepository->findBySlug($selectedPlanSlug);

        // Si le plan n'existe pas, utiliser le plan gratuit
        if (!$selectedPlan) {
            $selectedPlan = $this->planRepository->findFreePlan();
        }

        // Pré-remplir le plan sélectionné
        if ($selectedPlan) {
            $user->setPlan($selectedPlan);
        }

        // Créer le formulaire
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            return $this->handleRegistration($form, $user);
        }

        // Récupérer tous les plans pour l'affichage
        $allPlans = $this->planRepository->findActivePlans();

        return $this->render('auth/register.html.twig', [
            'registrationForm' => $form->createView(),
            'selected_plan' => $selectedPlan,
            'all_plans' => $allPlans,
        ]);
    }

    private function handleRegistration($form, User $user): Response
    {
        // Vérifier si l'email existe déjà
        $existingUser = $this->userRepository->findOneBy(['email' => $user->getEmail()]);
        if ($existingUser) {
            $this->addFlash('error', 'Un compte avec cet email existe déjà');
            return $this->redirectToRoute('app_register');
        }

        // Hasher le mot de passe
        $plainPassword = $form->get('plainPassword')->getData();
        $hashedPassword = $this->passwordHasher->hashPassword($user, $plainPassword);
        $user->setPassword($hashedPassword);

        // Définir les dates de création et modification
        $now = new \DateTimeImmutable();
        $user->setCreatedAt($now);
        $user->setUpdatedAt($now);

        // Définir un statut par défaut
        $user->setIsActive(true);
        $user->setIsVerified(false);

        // Générer un token de vérification d'email
        $user->generateEmailVerificationToken();

        // Pour les plans payants, définir une expiration d'un mois
        $plan = $user->getPlan();
        if ($plan && !$plan->isFree()) {
            $user->setPlanExpiresAt(new \DateTimeImmutable('+1 month'));
        }

        try {
            $this->entityManager->persist($user);
            $this->entityManager->flush();

            // Envoyer l'email de vérification
            $this->emailService->sendEmailVerification($user);

            // Message de succès
            $this->addFlash('success', sprintf(
                'Bienvenue %s ! Un email de vérification a été envoyé à %s.',
                $user->getFirstName(),
                $user->getEmail()
            ));

            // Log pour debug
            $this->logger->info('Nouvel utilisateur inscrit', [
                'user_id' => $user->getId(),
                'email' => $user->getEmail(),
                'plan' => $plan?->getName(),
            ]);

            // Rediriger vers la page de vérification d'email
            return $this->redirectToRoute('app_verify_email_pending');

        } catch (\Exception $e) {
            // Log de l'erreur
            $this->logger->error('Erreur lors de l\'inscription', [
                'error' => $e->getMessage(),
                'email' => $user->getEmail(),
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors de la création de votre compte. Veuillez réessayer.');

            return $this->redirectToRoute('app_register');
        }
    }

    #[Route('/forgot-password', name: 'app_forgot_password')]
    public function forgotPassword(Request $request): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('dashboard_index');
        }

        if ($request->isMethod('POST')) {
            $email = trim($request->request->get('email', ''));

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->addFlash('error', 'Veuillez entrer un email valide');
                return $this->render('auth/forgot_password.html.twig');
            }

            $user = $this->userRepository->findOneBy(['email' => $email]);

            // Toujours afficher le même message pour éviter l'énumération d'emails
            $this->addFlash('success', 'Si un compte avec cet email existe, vous recevrez un lien de réinitialisation sous peu.');

            if ($user) {
                try {
                    // Générer un nouveau token
                    $user->generatePasswordResetToken();
                    $this->entityManager->flush();

                    // Envoyer l'email de réinitialisation
                    $this->emailService->sendPasswordReset($user);

                    // Log pour debug
                    $this->logger->info('Demande de réinitialisation de mot de passe', [
                        'user_id' => $user->getId(),
                        'email' => $user->getEmail(),
                    ]);

                } catch (\Exception $e) {
                    $this->logger->error('Erreur lors de l\'envoi de l\'email de réinitialisation', [
                        'error' => $e->getMessage(),
                        'email' => $user->getEmail(),
                    ]);
                }
            }

            return $this->redirectToRoute('app_login');
        }

        return $this->render('auth/forgot_password.html.twig');
    }

    #[Route('/reset-password/{token}', name: 'app_reset_password')]
    public function resetPassword(Request $request, string $token): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('dashboard_index');
        }

        // Trouver l'utilisateur avec ce token
        $user = $this->userRepository->findOneBy(['passwordResetToken' => $token]);

        if (!$user || !$user->isPasswordResetTokenValid()) {
            $this->addFlash('error', 'Ce lien de réinitialisation est invalide ou a expiré.');
            return $this->redirectToRoute('app_forgot_password');
        }

        if ($request->isMethod('POST')) {
            $newPassword = $request->request->get('password');
            $confirmPassword = $request->request->get('confirm_password');

            // Validation
            if (strlen($newPassword) < 8) {
                $this->addFlash('error', 'Le mot de passe doit contenir au moins 8 caractères.');
                return $this->render('auth/reset_password.html.twig', ['token' => $token]);
            }

            if ($newPassword !== $confirmPassword) {
                $this->addFlash('error', 'Les mots de passe ne correspondent pas.');
                return $this->render('auth/reset_password.html.twig', ['token' => $token]);
            }

            try {
                // Hasher et sauvegarder le nouveau mot de passe
                $hashedPassword = $this->passwordHasher->hashPassword($user, $newPassword);
                $user->setPassword($hashedPassword);
                $user->clearPasswordResetToken();
                $user->setUpdatedAt(new \DateTimeImmutable());

                $this->entityManager->flush();

                $this->addFlash('success', 'Votre mot de passe a été mis à jour avec succès. Vous pouvez maintenant vous connecter.');

                $this->logger->info('Mot de passe réinitialisé', [
                    'user_id' => $user->getId(),
                    'email' => $user->getEmail(),
                ]);

                return $this->redirectToRoute('app_login');

            } catch (\Exception $e) {
                $this->logger->error('Erreur lors de la réinitialisation du mot de passe', [
                    'error' => $e->getMessage(),
                    'user_id' => $user->getId(),
                ]);

                $this->addFlash('error', 'Une erreur est survenue. Veuillez réessayer.');
            }
        }

        return $this->render('auth/reset_password.html.twig', [
            'token' => $token,
            'user' => $user
        ]);
    }

    #[Route('/verify-email', name: 'app_verify_email_pending')]
    public function verifyEmailPending(): Response
    {
        return $this->render('auth/verify_email.html.twig', [
            'verification_status' => 'pending'
        ]);
    }

    #[Route('/verify-email/{token}', name: 'app_verify_email')]
    public function verifyEmail(string $token): Response
    {
        $user = $this->userRepository->findOneBy(['emailVerificationToken' => $token]);

        if (!$user) {
            return $this->render('auth/verify_email.html.twig', [
                'verification_status' => 'invalid'
            ]);
        }

        if ($user->isVerified()) {
            $this->addFlash('info', 'Votre email est déjà vérifié.');
            return $this->redirectToRoute('dashboard_index');
        }

        try {
            // Marquer l'email comme vérifié
            $user->markEmailAsVerified();
            $this->entityManager->flush();

            $this->addFlash('success', 'Votre email a été vérifié avec succès !');

            $this->logger->info('Email vérifié', [
                'user_id' => $user->getId(),
                'email' => $user->getEmail(),
            ]);

            return $this->render('auth/verify_email.html.twig', [
                'verification_status' => 'success'
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Erreur lors de la vérification d\'email', [
                'error' => $e->getMessage(),
                'user_id' => $user->getId(),
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors de la vérification.');

            return $this->render('auth/verify_email.html.twig', [
                'verification_status' => 'error'
            ]);
        }
    }

    #[Route('/resend-verification', name: 'app_resend_verification', methods: ['POST'])]
    public function resendVerification(Request $request): Response
    {
        $email = trim($request->request->get('email', ''));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->addFlash('error', 'Veuillez entrer un email valide.');
            return $this->redirectToRoute('app_verify_email_pending');
        }

        $user = $this->userRepository->findOneBy(['email' => $email]);

        // Message générique pour éviter l'énumération
        $this->addFlash('success', 'Si un compte non vérifié existe avec cet email, un nouveau lien de vérification a été envoyé.');

        if ($user && !$user->isVerified()) {
            try {
                // Générer un nouveau token
                $user->generateEmailVerificationToken();
                $this->entityManager->flush();

                // Renvoyer l'email
                $this->emailService->sendEmailVerification($user);

                $this->logger->info('Email de vérification renvoyé', [
                    'user_id' => $user->getId(),
                    'email' => $user->getEmail(),
                ]);

            } catch (\Exception $e) {
                $this->logger->error('Erreur lors du renvoi de l\'email de vérification', [
                    'error' => $e->getMessage(),
                    'email' => $email,
                ]);
            }
        }

        return $this->redirectToRoute('app_verify_email_pending');
    }

    /**
     * Route AJAX pour valider l'email en temps réel
     */
    #[Route('/api/validate-email', name: 'api_validate_email', methods: ['POST'])]
    public function validateEmail(Request $request): Response
    {
        $email = trim($request->getContent());

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->json([
                'valid' => false,
                'message' => 'Format d\'email invalide'
            ]);
        }

        $existingUser = $this->userRepository->findOneBy(['email' => $email]);

        return $this->json([
            'valid' => !$existingUser,
            'message' => $existingUser ? 'Un compte avec cet email existe déjà' : 'Email disponible'
        ]);
    }
}
