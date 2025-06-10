<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\PlanRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Security\Http\Authentication\UserAuthenticatorInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class AuthController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly PlanRepository $planRepository,
        private readonly UserRepository $userRepository,
        private readonly ValidatorInterface $validator,
        private readonly UserAuthenticatorInterface $userAuthenticator
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

        $selectedPlanSlug = $request->query->get('plan', 'free');
        $selectedPlan = $this->planRepository->findBySlug($selectedPlanSlug);

        // Si le plan n'existe pas, utiliser le plan gratuit
        if (!$selectedPlan) {
            $selectedPlan = $this->planRepository->findFreePlan();
        }

        if ($request->isMethod('POST')) {
            return $this->handleRegistration($request, $selectedPlan);
        }

        // Récupérer tous les plans pour le sélecteur
        $allPlans = $this->planRepository->findActivePlans();

        return $this->render('auth/register.html.twig', [
            'selected_plan' => $selectedPlan,
            'all_plans' => $allPlans
        ]);
    }

    private function handleRegistration(Request $request, $selectedPlan): Response
    {
        $firstName = trim($request->request->get('first_name', ''));
        $lastName = trim($request->request->get('last_name', ''));
        $email = trim($request->request->get('email', ''));
        $password = $request->request->get('password', '');
        $confirmPassword = $request->request->get('confirm_password', '');
        $company = trim($request->request->get('company', ''));
        $planId = $request->request->get('plan_id');
        $acceptTerms = $request->request->get('accept_terms');

        // Validation
        $errors = [];

        if (empty($firstName)) {
            $errors[] = 'Le prénom est requis';
        }

        if (empty($lastName)) {
            $errors[] = 'Le nom est requis';
        }

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Un email valide est requis';
        }

        if (empty($password) || strlen($password) < 8) {
            $errors[] = 'Le mot de passe doit contenir au moins 8 caractères';
        }

        if ($password !== $confirmPassword) {
            $errors[] = 'Les mots de passe ne correspondent pas';
        }

        if (!$acceptTerms) {
            $errors[] = 'Vous devez accepter les conditions d\'utilisation';
        }

        // Vérifier si l'email existe déjà
        $existingUser = $this->userRepository->findOneBy(['email' => $email]);
        if ($existingUser) {
            $errors[] = 'Un compte avec cet email existe déjà';
        }

        // Vérifier le plan sélectionné
        $plan = $this->planRepository->find($planId);
        if (!$plan || !$plan->isActive()) {
            $errors[] = 'Plan sélectionné invalide';
        }

        if (!empty($errors)) {
            foreach ($errors as $error) {
                $this->addFlash('error', $error);
            }

            $allPlans = $this->planRepository->findActivePlans();
            return $this->render('auth/register.html.twig', [
                'selected_plan' => $selectedPlan,
                'all_plans' => $allPlans,
                'form_data' => [
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'email' => $email,
                    'company' => $company
                ]
            ]);
        }

        // Créer l'utilisateur
        $user = new User();
        $user->setFirstName($firstName)
            ->setLastName($lastName)
            ->setEmail($email)
            ->setCompany($company ?: null)
            ->setPlan($plan);

        // Hasher le mot de passe
        $hashedPassword = $this->passwordHasher->hashPassword($user, $password);
        $user->setPassword($hashedPassword);

        // Pour les plans payants, définir une expiration d'un mois
        if (!$plan->isFree()) {
            $user->setPlanExpiresAt(new \DateTime('+1 month'));
        }

        try {
            $this->entityManager->persist($user);
            $this->entityManager->flush();

            // Connecter automatiquement l'utilisateur
            // Note: L'auto-login sera géré par Symfony après l'inscription

            $this->addFlash('success', 'Votre compte a été créé avec succès !');

            // Si plan payant, rediriger vers le checkout
            if ($plan->getPriceMonthly() > 0) {
                return $this->redirectToRoute('billing_checkout', [
                    'planId' => $plan->getId()
                ]);
            }

            // Sinon, rediriger vers le dashboard
            return $this->redirectToRoute('dashboard_index');

        } catch (\Exception $e) {
            $this->addFlash('error', 'Une erreur est survenue lors de la création de votre compte');

            $allPlans = $this->planRepository->findActivePlans();
            return $this->render('auth/register.html.twig', [
                'selected_plan' => $selectedPlan,
                'all_plans' => $allPlans,
                'form_data' => [
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'email' => $email,
                    'company' => $company
                ]
            ]);
        }
    }

    #[Route('/forgot-password', name: 'app_forgot_password')]
    public function forgotPassword(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $email = trim($request->request->get('email', ''));

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->addFlash('error', 'Veuillez entrer un email valide');
                return $this->render('auth/forgot_password.html.twig');
            }

            $user = $this->userRepository->findOneBy(['email' => $email]);

            // Toujours afficher le même message pour éviter l'énumération d'emails
            $this->addFlash('success', 'Si un compte avec cet email existe, vous recevrez un lien de réinitialisation');

            if ($user) {
                // TODO: Implémenter l'envoi d'email de réinitialisation
                // $this->sendPasswordResetEmail($user);
            }

            return $this->redirectToRoute('app_login');
        }

        return $this->render('auth/forgot_password.html.twig');
    }

    #[Route('/verify-email', name: 'app_verify_email')]
    public function verifyEmail(): Response
    {
        // TODO: Implémenter la vérification d'email
        return $this->render('auth/verify_email.html.twig');
    }
}
