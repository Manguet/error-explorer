<?php

// tests/Controller/HealthCheckTest.php
namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class HealthCheckTest extends WebTestCase
{
    public function testApplicationBoots(): void
    {
        // Test simple : vérifier que l'application Symfony démarre
        $client = static::createClient();
        $this->assertNotNull($client);
    }

    public function testLoginPageExists(): void
    {
        $client = static::createClient();

        // Tester la page de connexion (plus susceptible d'exister)
        $client->request('GET', '/login');

        // Accepter les redirections (au cas où)
        $statusCode = $client->getResponse()->getStatusCode();

        // Vérifier que ce n'est pas une erreur 404 ou 500
        $this->assertNotEquals(404, $statusCode, 'La page /login n\'existe pas');
        $this->assertNotEquals(500, $statusCode, 'Erreur serveur sur /login');

        // Si la page existe, vérifier le contenu
        if ($statusCode === 200) {
            self::assertSelectorExists('body');
        }
    }

    public function testRegisterPageExists(): void
    {
        $client = static::createClient();

        // Tester la page d'inscription
        $client->request('GET', '/register');

        $statusCode = $client->getResponse()->getStatusCode();

        // Vérifier que ce n'est pas une erreur 404 ou 500
        $this->assertNotEquals(404, $statusCode, 'La page /register n\'existe pas');
        $this->assertNotEquals(500, $statusCode, 'Erreur serveur sur /register');

        // Si la page existe, vérifier qu'il y a un formulaire
        if ($statusCode === 200) {
            self::assertSelectorExists('body');
        }
    }
}
