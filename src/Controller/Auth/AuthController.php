<?php

namespace App\Controller\Auth;

use App\Entity\User;
use App\Form\Register\RegistrationFormType;
use App\Repository\PlanRepository;
use App\Repository\UserRepository;
use App\Service\EmailService;
use App\Service\Logs\MonologService;
use App\Service\Logs\NotificationService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use LogicException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class AuthController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly PlanRepository $planRepository,
        private readonly UserRepository $userRepository,
        private readonly EmailService $emailService,
        private readonly MonologService $monolog,
        private readonly RequestStack $requestStack,
        private readonly NotificationService $notificationService
    ) {}

    #[Route('/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('dashboard_index');
        }

        $error = $authenticationUtils->getLastAuthenticationError();
        $lastUsername = $authenticationUtils->getLastUsername();

        if ($error) {
            $this->monolog->loginAttempt($lastUsername, false, $this->getClientIp());
        }

        return $this->render('auth/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
        ]);
    }

    #[Route('/logout', name: 'app_logout')]
    public function logout(): void
    {
        throw new LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }

    #[Route('/register', name: 'app_register')]
    public function register(Request $request): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('dashboard_index');
        }

        $user = new User();

        $selectedPlanSlug = $request->query->get('plan', 'free');
        $selectedPlan = $this->planRepository->findBySlug($selectedPlanSlug);

        if (!$selectedPlan) {
            $selectedPlan = $this->planRepository->findFreePlan();
        }

        if ($selectedPlan) {
            $user->setPlan($selectedPlan);
        }

        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->monolog->securityEvent('registration_attempt', [
                'email' => $user->getEmail(),
                'plan' => $selectedPlan?->getName(),
                'ip' => $this->getClientIp()
            ]);

            return $this->handleRegistration($form, $user);
        }

        $allPlans = $this->planRepository->findActivePlans();

        return $this->render('auth/register.html.twig', [
            'registrationForm' => $form->createView(),
            'selected_plan' => $selectedPlan,
            'all_plans' => $allPlans,
        ]);
    }

    private function handleRegistration($form, User $user): Response
    {
        $existingUser = $this->userRepository->findOneBy(['email' => $user->getEmail()]);
        if ($existingUser) {
            $this->monolog->securityEvent('registration_duplicate_email', [
                'email' => $user->getEmail(),
                'ip' => $this->getClientIp()
            ]);

            $this->addFlash('error', 'Un compte avec cet email existe déjà');
            return $this->redirectToRoute('app_register');
        }

        $plainPassword = $form->get('plainPassword')->getData();
        $hashedPassword = $this->passwordHasher->hashPassword($user, $plainPassword);
        $user->setPassword($hashedPassword);

        $now = new DateTimeImmutable();
        $user->setCreatedAt($now);
        $user->setUpdatedAt($now);
        $user->setIsActive(true);
        $user->setIsVerified(false);
        $user->generateEmailVerificationToken();

        $plan = $user->getPlan();
        if ($plan && !$plan->isFree()) {
            $user->setPlanExpiresAt(new DateTimeImmutable('+1 month'));
        }

        try {
            $this->entityManager->persist($user);
            $this->entityManager->flush();

            $this->emailService->sendEmailVerification($user);

            $this->addFlash('success', sprintf(
                'Bienvenue %s ! Un email de vérification a été envoyé à %s.',
                $user->getFirstName(),
                $user->getEmail()
            ));

            $this->notificationService->createNotification(
                title: 'Nouvel utilisateur inscrit',
                description: "{$user->getFirstName()} {$user->getLastName()} ({$user->getEmail()}) vient de s'inscrire avec le plan {$plan?->getName()}.",
                audiences: [NotificationService::AUDIENCE_ADMIN],
                data: [
                    'user_id' => $user->getId(),
                    'email' => $user->getEmail(),
                    'name' => $user->getFirstName() . ' ' . $user->getLastName(),
                    'plan' => $plan?->getName(),
                    'plan_id' => $plan?->getId(),
                    'ip' => $this->getClientIp(),
                    'company' => $user->getCompany(),
                    'registered_at' => $user->getCreatedAt()?->format('Y-m-d H:i:s')
                ],
                type: NotificationService::TYPE_USER_REGISTERED
            );

            return $this->redirectToRoute('app_verify_email_pending');

        } catch (Exception $e) {
            $this->notificationService->createNotification(
                title: 'Erreur lors d\'une inscription',
                description: "Erreur technique lors de l'inscription de {$user->getEmail()}: {$e->getMessage()}",
                audiences: [NotificationService::AUDIENCE_ADMIN],
                data: [
                    'email' => $user->getEmail(),
                    'plan' => $plan?->getName(),
                    'error' => $e->getMessage(),
                    'ip' => $this->getClientIp(),
                    'trace' => $e->getTraceAsString()
                ],
                type: 'system_error',
                priority: NotificationService::PRIORITY_HIGH
            );

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

            $this->monolog->securityEvent('password_reset_requested', [
                'email' => $email,
                'user_exists' => $user !== null,
                'ip' => $this->getClientIp()
            ]);

            if (!$user) {
                $this->notificationService->createNotification(
                    title: 'Tentative de réinitialisation sur email inexistant',
                    description: "Quelqu'un a tenté de réinitialiser le mot de passe pour {$email} (compte inexistant).",
                    audiences: [NotificationService::AUDIENCE_ADMIN],
                    expireAfter: '+24 hours',
                    data: [
                        'email' => $email,
                        'ip' => $this->getClientIp(),
                        'user_agent' => $request->headers->get('User-Agent')
                    ],
                    type: 'security_alert',
                    priority: NotificationService::PRIORITY_LOW
                );
            }

            $this->addFlash('success', 'Si un compte avec cet email existe, vous recevrez un lien de réinitialisation sous peu.');

            if ($user) {
                try {
                    $user->generatePasswordResetToken();
                    $this->entityManager->flush();

                    $this->emailService->sendPasswordReset($user);

                    $this->monolog->businessEvent('password_reset_email_sent', [
                        'user_id' => $user->getId(),
                        'email' => $user->getEmail(),
                        'ip' => $this->getClientIp()
                    ]);

                } catch (Exception $e) {
                    $this->notificationService->createNotification(
                        title: 'Erreur envoi email de réinitialisation',
                        description: "Impossible d'envoyer l'email de réinitialisation à {$user->getEmail()}: {$e->getMessage()}",
                        audiences: [NotificationService::AUDIENCE_ADMIN],
                        data: [
                            'user_id' => $user->getId(),
                            'email' => $user->getEmail(),
                            'error' => $e->getMessage()
                        ],
                        type: 'system_error',
                        priority: NotificationService::PRIORITY_HIGH
                    );
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

        $user = $this->userRepository->findOneBy(['passwordResetToken' => $token]);

        if (!$user || !$user->isPasswordResetTokenValid()) {

            $this->notificationService->createNotification(
                title: 'Tentative de réinitialisation avec token invalide',
                description: "Quelqu'un a tenté d'utiliser un token de réinitialisation invalide ou expiré.",
                audiences: [NotificationService::AUDIENCE_ADMIN],
                data: [
                    'token' => substr($token, 0, 10) . '...',
                    'ip' => $this->getClientIp(),
                    'user_found' => $user !== null,
                    'token_valid' => $user?->isPasswordResetTokenValid()
                ],
                type: 'security_alert'
            );

            $this->addFlash('error', 'Ce lien de réinitialisation est invalide ou a expiré.');
            return $this->redirectToRoute('app_forgot_password');
        }

        if ($request->isMethod('POST')) {
            $newPassword = $request->request->get('password');
            $confirmPassword = $request->request->get('confirm_password');

            if (strlen($newPassword) < 8) {
                $this->addFlash('error', 'Le mot de passe doit contenir au moins 8 caractères.');
                return $this->render('auth/reset_password.html.twig', ['token' => $token]);
            }

            if ($newPassword !== $confirmPassword) {
                $this->addFlash('error', 'Les mots de passe ne correspondent pas.');
                return $this->render('auth/reset_password.html.twig', ['token' => $token]);
            }

            try {
                $hashedPassword = $this->passwordHasher->hashPassword($user, $newPassword);
                $user->setPassword($hashedPassword);
                $user->clearPasswordResetToken();
                $user->setUpdatedAt(new DateTimeImmutable());

                $this->entityManager->flush();

                $this->addFlash('success', 'Votre mot de passe a été mis à jour avec succès. Vous pouvez maintenant vous connecter.');

                $this->monolog->securityEvent('password_reset_success', [
                    'user_id' => $user->getId(),
                    'email' => $user->getEmail(),
                    'ip' => $this->getClientIp()
                ]);

                return $this->redirectToRoute('app_login');

            } catch (Exception $e) {
                $this->monolog->capture(
                    'Erreur lors de la réinitialisation du mot de passe: ' . $e->getMessage(),
                    MonologService::SECURITY,
                    MonologService::ERROR,
                    [
                        'user_id' => $user->getId(),
                        'email' => $user->getEmail(),
                        'ip' => $this->getClientIp(),
                        'exception' => $e->getTraceAsString()
                    ]
                );

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
            $this->notificationService->createNotification(
                title: 'Tentative de vérification avec token invalide',
                description: 'Quelqu\'un a tenté de vérifier un email avec un token invalide.',
                audiences: [NotificationService::AUDIENCE_ADMIN],
                data: [
                    'token' => substr($token, 0, 10) . '...',
                    'ip' => $this->getClientIp()
                ],
                type: 'security_alert',
                priority: NotificationService::PRIORITY_LOW
            );

            return $this->render('auth/verify_email.html.twig', [
                'verification_status' => 'invalid'
            ]);
        }

        if ($user->isVerified()) {
            $this->addFlash('info', 'Votre email est déjà vérifié.');
            return $this->redirectToRoute('dashboard_index');
        }

        try {
            $user->markEmailAsVerified();
            $this->entityManager->flush();

            $this->addFlash('success', 'Votre email a été vérifié avec succès !');

            $this->monolog->securityEvent('email_verified', [
                'user_id' => $user->getId(),
                'email' => $user->getEmail(),
                'ip' => $this->getClientIp()
            ]);

            $this->monolog->businessEvent('user_email_verified', [
                'user_id' => $user->getId(),
                'email' => $user->getEmail()
            ]);

            return $this->render('auth/verify_email.html.twig', [
                'verification_status' => 'success'
            ]);

        } catch (Exception $e) {

            $this->monolog->capture(
                'Erreur lors de la vérification d\'email: ' . $e->getMessage(),
                MonologService::BUSINESS,
                MonologService::ERROR,
                [
                    'user_id' => $user->getId(),
                    'email' => $user->getEmail(),
                    'ip' => $this->getClientIp(),
                    'exception' => $e->getTraceAsString()
                ]
            );

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

        $this->monolog->securityEvent('email_verification_resend_requested', [
            'email' => $email,
            'user_exists' => $user !== null,
            'user_verified' => $user ? $user->isVerified() : null,
            'ip' => $this->getClientIp()
        ]);

        $this->addFlash('success', 'Si un compte non vérifié existe avec cet email, un nouveau lien de vérification a été envoyé.');

        if ($user && !$user->isVerified()) {
            try {
                $user->generateEmailVerificationToken();
                $this->entityManager->flush();

                $this->emailService->sendEmailVerification($user);

                $this->monolog->businessEvent('email_verification_resent', [
                    'user_id' => $user->getId(),
                    'email' => $user->getEmail(),
                    'ip' => $this->getClientIp()
                ]);

            } catch (Exception $e) {
                $this->monolog->capture(
                    'Erreur lors du renvoi de l\'email de vérification: ' . $e->getMessage(),
                    MonologService::BUSINESS,
                    MonologService::ERROR,
                    [
                        'email' => $email,
                        'user_id' => $user->getId(),
                        'ip' => $this->getClientIp(),
                        'exception' => $e->getTraceAsString()
                    ]
                );
            }
        }

        return $this->redirectToRoute('app_verify_email_pending');
    }

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

        if ($existingUser) {
            $this->monolog->securityEvent('email_validation_duplicate', [
                'email' => $email,
                'ip' => $this->getClientIp()
            ]);
        }

        return $this->json([
            'valid' => !$existingUser,
            'message' => $existingUser ? 'Un compte avec cet email existe déjà' : 'Email disponible'
        ]);
    }

    private function getClientIp(): string
    {
        $request = $this->requestStack->getCurrentRequest();

        if (!$request) {
            return 'unknown';
        }

        $headers = [
            'HTTP_CF_CONNECTING_IP',     // Cloudflare
            'HTTP_CLIENT_IP',            // Proxy
            'HTTP_X_FORWARDED_FOR',      // Load balancer/proxy
            'HTTP_X_FORWARDED',          // Proxy
            'HTTP_X_CLUSTER_CLIENT_IP',  // Cluster
            'HTTP_FORWARDED_FOR',        // Proxy
            'HTTP_FORWARDED',            // Proxy
            'REMOTE_ADDR'                // Standard
        ];

        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ips = explode(',', $_SERVER[$header]);
                $ip = trim($ips[0]);

                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }

        return $request->getClientIp() ?? 'unknown';
    }
}
