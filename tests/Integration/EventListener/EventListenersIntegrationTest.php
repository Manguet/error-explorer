<?php

namespace App\Tests\Integration\EventListener;

use App\EventListener\LoginEventListener;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class EventListenersIntegrationTest extends KernelTestCase
{
    protected function setUp(): void
    {
        self::bootKernel();
    }

    public function testLoginEventListenerIsRegistered(): void
    {
        $container = static::getContainer();

        $this->assertTrue($container->has(LoginEventListener::class));

        $listener = $container->get(LoginEventListener::class);
        $this->assertInstanceOf(LoginEventListener::class, $listener);
    }
}
