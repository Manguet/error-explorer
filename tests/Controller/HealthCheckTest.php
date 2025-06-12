<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class HealthCheckTest extends WebTestCase
{
    public function testHomepageIsAccessible(): void
    {
        $client = static::createClient();

        $client->request('GET', '/');

        self::assertResponseIsSuccessful();
    }

    public function testLoginPageIsAccessible(): void
    {
        $client = static::createClient();

        $client->request('GET', '/login');

        self::assertResponseIsSuccessful();

        self::assertSelectorTextContains('h1', 'Error Explorer');
    }

    public function testRegisterPageIsAccessible(): void
    {
        $client = static::createClient();

        $client->request('GET', '/register');

        self::assertResponseIsSuccessful();

        self::assertSelectorExists('form');
        self::assertSelectorExists('input[name*="firstName"]');
        self::assertSelectorExists('input[name*="email"]');
    }
}
