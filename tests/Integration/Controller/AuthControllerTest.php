<?php

namespace App\Tests\Integration\Controller;

use App\Entity\Plan;
use App\Entity\User;
use App\Repository\PlanRepository;
use App\Repository\UserRepository;
use App\Service\EmailService;
use App\Tests\DatabaseTestCase;
use App\Tests\TestFixtures;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Tests d'intégration pour AuthController
 */
class AuthControllerTest extends DatabaseTestCase
{
    use TestFixtures;

    private $client;
    private UserRepository $userRepository;
    private PlanRepository $planRepository;

    protected function setUp(): void
    {
        // D'abord initialiser la base de données
        parent::setUp();

        // Ensuite créer le client
        $this->client = static::createClient();

        $this->userRepository = $this->entityManager->getRepository(User::class);
        $this->planRepository = $this->entityManager->getRepository(Plan::class);
    }

    private function cleanDatabase(): void
    {
        // Supprimer tous les utilisateurs de test
        $users = $this->userRepository->findAll();
        foreach ($users as $user) {
            $this->entityManager->remove($user);
        }
        $this->entityManager->flush();
    }

    /**
     * Test d'affichage de la page de connexion
     */
    public function testLoginPageDisplay(): void
    {
        $this->client->request('GET', '/login');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form[action="/login"]');
        self::assertSelectorExists('input[name="email"]');
        self::assertSelectorExists('input[name="password"]');
    }

    /**
     * Test de redirection si l'utilisateur est déjà connecté
     */
    public function testLoginRedirectIfAlreadyAuthenticated(): void
    {
        $user = $this->createTestUser();
        $this->client->loginUser($user);

        $this->client->request('GET', '/login');

        self::assertResponseRedirects('/dashboard');
    }

    /**
     * Test d'affichage de la page d'inscription
     */
    public function testRegisterPageDisplay(): void
    {
        $this->client->request('GET', '/register');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form.register-form');
        self::assertSelectorExists('input[name="registration_form[firstName]"]');
        self::assertSelectorExists('input[name="registration_form[email]"]');
    }

    /**
     * Test d'inscription réussie
     */
    public function testSuccessfulRegistration(): void
    {
        $freePlan = $this->createTestPlan('Free', 0);
        $this->persistAndFlush($freePlan);

        $this->client->request('POST', '/register', [
            'registration_form' => [
                'firstName' => 'John',
                'lastName' => 'Doe',
                'email' => 'john.doe@example.com',
                'company' => 'Test Company',
                'plainPassword' => [
                    'first' => 'Password123!',
                    'second' => 'Password123!'
                ],
                'plan' => $freePlan->getId(),
                'acceptTerms' => true,
                '_token' => $this->generateCsrfToken('registration_form')
            ]
        ]);

        // Vérifier que l'utilisateur a été créé
        $user = $this->userRepository->findOneBy(['email' => 'john.doe@example.com']);
        $this->assertInstanceOf(User::class, $user);
        $this->assertSame('John', $user->getFirstName());
    }

    /**
     * Test d'inscription avec email déjà existant
     */
    public function testRegistrationWithExistingEmail(): void
    {
        $this->createTestUser('existing@example.com');
        $freePlan = $this->createTestPlan('Free', 0);

        $this->client->request('POST', '/register', [
            'registration_form' => [
                'firstName' => 'John',
                'lastName' => 'Doe',
                'email' => 'existing@example.com',
                'plainPassword' => [
                    'first' => 'Password123!',
                    'second' => 'Password123!'
                ],
                'plan' => $freePlan->getId(),
                'acceptTerms' => true,
                '_token' => $this->generateCsrfToken('registration_form')
            ]
        ]);

        self::assertResponseRedirects('/register');

        // Suivre la redirection et vérifier le message d'erreur
        $crawler = $this->client->followRedirect();
        $this->assertStringContainsString('Un compte avec cet email existe déjà', $crawler->text());
    }

    /**
     * Test de validation du formulaire d'inscription
     */
    public function testRegistrationFormValidation(): void
    {
        $this->client->request('POST', '/register', [
            'registration_form' => [
                'firstName' => '', // Vide
                'lastName' => 'Doe',
                'email' => 'invalid-email', // Format invalide
                'plainPassword' => [
                    'first' => '123', // Trop court
                    'second' => '456'  // Différent
                ],
                'acceptTerms' => false, // Non coché
                '_token' => $this->generateCsrfToken('registration_form')
            ]
        ]);

        self::assertResponseIsSuccessful(); // Retourne le formulaire avec erreurs
        $crawler = $this->client->getCrawler();

        // Vérifier la présence d'erreurs de validation
        $this->assertGreaterThan(0, $crawler->filter('.form-error, .invalid-feedback')->count());
    }

    /**
     * Test de demande de réinitialisation de mot de passe
     */
    public function testForgotPasswordWithExistingUser(): void
    {
        $user = $this->createTestUser('test@example.com');

        // Mocker le service d'email
        $emailService = $this->createMock(EmailService::class);
        $emailService->expects($this->once())
            ->method('sendPasswordReset')
            ->with($this->equalTo($user));

        static::getContainer()->set(EmailService::class, $emailService);

        $this->client->request('POST', '/forgot-password', [
            'email' => 'test@example.com'
        ]);

        self::assertResponseRedirects('/login');

        // Vérifier que le token a été généré
        $this->entityManager->refresh($user);
        $this->assertNotNull($user->getPasswordResetToken());
    }

    /**
     * Test de demande de réinitialisation avec email inexistant
     */
    public function testForgotPasswordWithNonExistentUser(): void
    {
        $emailService = $this->createMock(EmailService::class);
        $emailService->expects($this->never())
            ->method('sendPasswordReset');

        static::getContainer()->set(EmailService::class, $emailService);

        $this->client->request('POST', '/forgot-password', [
            'email' => 'nonexistent@example.com'
        ]);

        self::assertResponseRedirects('/login');

        // Le message doit être le même pour des raisons de sécurité
        $crawler = $this->client->followRedirect();
        $this->assertStringContainsString('Si un compte avec cet email existe', $crawler->text());
    }

    /**
     * Test de réinitialisation de mot de passe avec token valide
     */
    public function testResetPasswordWithValidToken(): void
    {
        $user = $this->createTestUser();
        $user->generatePasswordResetToken();
        $this->entityManager->flush();

        $token = $user->getPasswordResetToken();

        $this->client->request('POST', "/reset-password/{$token}", [
            'password' => 'NewPassword123!',
            'confirm_password' => 'NewPassword123!'
        ]);

        self::assertResponseRedirects('/login');

        // Vérifier que le mot de passe a été changé
        $this->entityManager->refresh($user);
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $this->assertTrue($hasher->isPasswordValid($user, 'NewPassword123!'));
        $this->assertNull($user->getPasswordResetToken());
    }

    /**
     * Test de réinitialisation avec token invalide
     */
    public function testResetPasswordWithInvalidToken(): void
    {
        $this->client->request('GET', '/reset-password/invalid-token');

        self::assertResponseRedirects('/forgot-password');
    }

    /**
     * Test de vérification d'email avec token valide
     */
    public function testEmailVerificationWithValidToken(): void
    {
        $user = $this->createTestUser();
        $user->setIsVerified(false);
        $user->generateEmailVerificationToken();
        $this->entityManager->flush();

        $token = $user->getEmailVerificationToken();

        $this->client->request('GET', "/verify-email/{$token}");

        self::assertResponseIsSuccessful();

        // Vérifier que l'email a été vérifié
        $this->entityManager->refresh($user);
        $this->assertTrue($user->isVerified());
        $this->assertNull($user->getEmailVerificationToken());
    }

    /**
     * Test de validation d'email via API
     */
    public function testEmailValidationAPI(): void
    {
        // Test avec email valide et disponible
        $this->client->request('POST', '/api/validate-email', [], [], [], 'test@example.com');

        self::assertResponseIsSuccessful();
        $response = json_decode($this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertTrue($response['valid']);
        $this->assertSame('Email disponible', $response['message']);

        // Test avec email déjà existant
        $this->createTestUser('existing@example.com');

        $this->client->request('POST', '/api/validate-email', [], [], [], 'existing@example.com');

        $response = json_decode($this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertFalse($response['valid']);
        $this->assertStringContainsString('existe déjà', $response['message']);
    }

    /**
     * Test de renvoi d'email de vérification
     */
    public function testResendVerificationEmail(): void
    {
        $user = $this->createTestUser('test@example.com');
        $user->setIsVerified(false);
        $this->entityManager->flush();

        $emailService = $this->createMock(EmailService::class);
        $emailService->expects($this->once())
            ->method('sendEmailVerification');

        static::getContainer()->set(EmailService::class, $emailService);

        $this->client->request('POST', '/resend-verification', [
            'email' => 'test@example.com'
        ]);

        self::assertResponseRedirects('/verify-email');
    }

    /**
     * Helpers pour créer des données de test
     */
    private function createTestUser(string $email = 'test@example.com'): User
    {
        $user = new User();
        $user->setEmail($email);
        $user->setFirstName('Test');
        $user->setLastName('User');
        $user->setPassword('hashed_password');
        $user->setIsActive(true);
        $user->setIsVerified(true);
        $user->setCreatedAt(new \DateTimeImmutable());
        $user->setUpdatedAt(new \DateTimeImmutable());

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }

    private function createTestPlan(string $name, float $price): Plan
    {
        $plan = new Plan();
        $plan->setName($name);
        $plan->setSlug(strtolower($name));
        $plan->setPriceMonthly($price);
        $plan->setIsActive(true);
        $plan->setIsBuyable(true);
        $plan->setMaxProjects(5);
        $plan->setMaxMonthlyErrors(1000);

        $this->entityManager->persist($plan);
        $this->entityManager->flush();

        return $plan;
    }

    private function generateCsrfToken(string $tokenId): string
    {
        return static::getContainer()
            ->get('security.csrf.token_manager')
            ->getToken($tokenId)
            ->getValue();
    }
}
