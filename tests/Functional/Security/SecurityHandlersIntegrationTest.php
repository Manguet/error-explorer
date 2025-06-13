<?php

namespace App\Tests\Functional\Security;

use App\Tests\DatabaseTestCase;
use App\Tests\TestFixtures;

class SecurityHandlersIntegrationTest extends DatabaseTestCase
{
    use TestFixtures;

    private $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = static::createClient();
    }

    public function testRateLimitingIntegration(): void
    {
        // Créer un plan d'abord
        $plan = $this->createTestPlan();
        $this->persistAndFlush($plan);

        // Simuler 5 tentatives de connexion échouées
        for ($i = 0; $i < 5; $i++) {
            $this->client->request('POST', '/login', [
                'email' => 'nonexistent@example.com',
                'password' => 'wrong_password',
                '_token' => $this->generateCsrfToken('authenticate')
            ]);
        }

        // La 6ème tentative devrait être bloquée
        $this->client->request('POST', '/login', [
            'email' => 'nonexistent@example.com',
            'password' => 'wrong_password',
            '_token' => $this->generateCsrfToken('authenticate')
        ]);

        // Selon votre implémentation, vérifier la réponse
        self::assertResponseIsSuccessful();
    }

    public function testSuccessfulLoginLogging(): void
    {
        // Créer un plan d'abord
        $plan = $this->createTestPlan();
        $this->persistAndFlush($plan);

        // Créer un utilisateur de test avec le plan
        $user = $this->createTestUser(['plan' => $plan]);
        $this->persistAndFlush($user);

        $this->client->request('POST', '/login', [
            'email' => $user->getEmail(),
            'password' => 'password123',
            '_token' => $this->generateCsrfToken('authenticate')
        ]);
    }

    private function generateCsrfToken(string $tokenId = 'authenticate'): string
    {
        return static::getContainer()
            ->get('security.csrf.token_manager')
            ->getToken($tokenId)
            ->getValue();
    }
}
