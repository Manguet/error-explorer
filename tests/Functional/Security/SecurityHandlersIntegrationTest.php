<?php

namespace App\Tests\Functional\Security;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class SecurityHandlersIntegrationTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    public function testRateLimitingIntegration(): void
    {
        // Simuler 5 tentatives de connexion échouées
        for ($i = 0; $i < 5; $i++) {
            $this->client->request('POST', '/login', [
                'email' => 'nonexistent@example.com',
                'password' => 'wrong_password',
                '_token' => $this->generateCsrfToken()
            ]);
        }

        // La 6ème tentative devrait être bloquée
        $this->client->request('POST', '/login', [
            'email' => 'nonexistent@example.com',
            'password' => 'wrong_password',
            '_token' => $this->generateCsrfToken()
        ]);

        self::assertResponseStatusCodeSame(429); // Too Many Requests
    }

    public function testSuccessfulLoginLogging(): void
    {
        // Créer un utilisateur de test
        $user = $this->createTestUser();

        $this->client->request('POST', '/login', [
            'email' => $user->getEmail(),
            'password' => 'password123',
            '_token' => $this->generateCsrfToken()
        ]);

        self::assertResponseRedirects('/dashboard');
    }

    private function createTestUser(): User
    {
        $entityManager = static::getContainer()->get('doctrine')->getManager();
        $passwordHasher = static::getContainer()->get('security.user_password_hasher');

        $user = new User();
        $user->setEmail('test@example.com');
        $user->setFirstName('Test');
        $user->setLastName('User');
        $user->setPassword($passwordHasher->hashPassword($user, 'password123'));
        $user->setIsActive(true);
        $user->setIsVerified(true);
        $user->setCreatedAt(new \DateTimeImmutable());
        $user->setUpdatedAt(new \DateTimeImmutable());

        $entityManager->persist($user);
        $entityManager->flush();

        return $user;
    }

    private function generateCsrfToken(): string
    {
        return static::getContainer()
            ->get('security.csrf.token_manager')
            ->getToken('authenticate')
            ->getValue();
    }
}
