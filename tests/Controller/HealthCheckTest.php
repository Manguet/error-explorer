<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class HealthCheckTest extends WebTestCase
{
    public function testApplicationBoots(): void
    {
        $client = static::createClient();
        $this->assertNotNull($client);
    }

    public function testLoginPageExists(): void
    {
        $client = static::createClient();

        $client->request('GET', '/login');

        $statusCode = $client->getResponse()->getStatusCode();

        $this->assertNotEquals(404, $statusCode, 'La page /login n\'existe pas');
        $this->assertNotEquals(500, $statusCode, 'Erreur serveur sur /login');

        if ($statusCode === 200) {
            self::assertSelectorExists('body');
        }
    }

    public function testRegisterPageExists(): void
    {
        $client = static::createClient();
        $client->request('GET', '/register');

        $statusCode = $client->getResponse()->getStatusCode();

        $this->assertNotEquals(404, $statusCode, 'La page /register n\'existe pas');

        if ($statusCode === 200) {
            self::assertSelectorExists('body');
        }
    }
}
