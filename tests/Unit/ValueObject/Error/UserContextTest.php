<?php

namespace App\Tests\Unit\ValueObject\Error;

use App\ValueObject\Error\UserContext;
use PHPUnit\Framework\TestCase;

class UserContextTest extends TestCase
{
    public function testFromArrayWithAllFields(): void
    {
        $data = [
            'id' => 123,
            'email' => 'john@example.com',
            'username' => 'johndoe',
            'name' => 'John Doe',
            'ip' => '192.168.1.1',
            'custom_field' => 'custom_value',
            'role' => 'admin'
        ];

        $context = UserContext::fromArray($data);

        $this->assertEquals('123', $context->id);
        $this->assertEquals('john@example.com', $context->email);
        $this->assertEquals('johndoe', $context->username);
        $this->assertEquals('John Doe', $context->name);
        $this->assertEquals('192.168.1.1', $context->ip);
        $this->assertEquals(['custom_field' => 'custom_value', 'role' => 'admin'], $context->extra);
    }

    public function testIsValid(): void
    {
        $validWithId = new UserContext(id: '123');
        $this->assertTrue($validWithId->isValid());

        $validWithEmail = new UserContext(email: 'john@example.com');
        $this->assertTrue($validWithEmail->isValid());

        $validWithUsername = new UserContext(username: 'johndoe');
        $this->assertTrue($validWithUsername->isValid());

        $invalid = new UserContext();
        $this->assertFalse($invalid->isValid());
    }

    public function testGetDisplayName(): void
    {
        $withName = new UserContext(name: 'John Doe');
        $this->assertEquals('John Doe', $withName->getDisplayName());

        $withUsername = new UserContext(username: 'johndoe');
        $this->assertEquals('johndoe', $withUsername->getDisplayName());

        $withEmail = new UserContext(email: 'john@example.com');
        $this->assertEquals('john@example.com', $withEmail->getDisplayName());

        $withId = new UserContext(id: '123');
        $this->assertEquals('User #123', $withId->getDisplayName());

        $anonymous = new UserContext();
        $this->assertEquals('Anonymous User', $anonymous->getDisplayName());
    }

    public function testGetUniqueIdentifier(): void
    {
        $context = new UserContext(
            id: '123',
            email: 'john@example.com',
            username: 'johndoe'
        );
        $this->assertEquals('123', $context->getUniqueIdentifier());

        $context2 = new UserContext(
            email: 'john@example.com',
            username: 'johndoe'
        );
        $this->assertEquals('john@example.com', $context2->getUniqueIdentifier());

        $context3 = new UserContext(username: 'johndoe');
        $this->assertEquals('johndoe', $context3->getUniqueIdentifier());

        $context4 = new UserContext();
        $this->assertNull($context4->getUniqueIdentifier());
    }

    public function testHasValidEmail(): void
    {
        $validEmail = new UserContext(email: 'john@example.com');
        $this->assertTrue($validEmail->hasValidEmail());

        $invalidEmail = new UserContext(email: 'not-an-email');
        $this->assertFalse($invalidEmail->hasValidEmail());

        $noEmail = new UserContext();
        $this->assertFalse($noEmail->hasValidEmail());
    }

    public function testGetMaskedEmail(): void
    {
        $context = new UserContext(email: 'john@example.com');
        $this->assertEquals('j**n@example.com', $context->getMaskedEmail());

        $shortEmail = new UserContext(email: 'ab@example.com');
        $this->assertEquals('**@example.com', $shortEmail->getMaskedEmail());

        $invalidEmail = new UserContext(email: 'not-an-email');
        $this->assertEquals('[EMAIL INVALID]', $invalidEmail->getMaskedEmail());

        $noEmail = new UserContext();
        $this->assertNull($noEmail->getMaskedEmail());
    }

    public function testHasPrivateIp(): void
    {
        $privateIp = new UserContext(ip: '192.168.1.1');
        $this->assertTrue($privateIp->hasPrivateIp());

        $publicIp = new UserContext(ip: '8.8.8.8');
        $this->assertFalse($publicIp->hasPrivateIp());

        $noIp = new UserContext();
        $this->assertFalse($noIp->hasPrivateIp());
    }

    public function testGetMaskedIp(): void
    {
        $ipv4 = new UserContext(ip: '192.168.1.100');
        $this->assertEquals('192.168.1.xxx', $ipv4->getMaskedIp());

        $ipv6 = new UserContext(ip: '2001:0db8:85a3:0000:0000:8a2e:0370:7334');
        $this->assertEquals('2001:0db8:85a3:0000:xxxx:xxxx:xxxx:xxxx', $ipv6->getMaskedIp());

        $invalidIp = new UserContext(ip: 'not-an-ip');
        $this->assertEquals('[IP INVALID]', $invalidIp->getMaskedIp());

        $noIp = new UserContext();
        $this->assertNull($noIp->getMaskedIp());
    }

    public function testGetSafeExtra(): void
    {
        $extra = [
            'role' => 'admin',
            'password' => 'secret123',
            'api_key' => 'secret-key',
            'preferences' => ['theme' => 'dark']
        ];

        $context = new UserContext(extra: $extra);
        $safeExtra = $context->getSafeExtra();

        $this->assertEquals('admin', $safeExtra['role']);
        $this->assertEquals('[FILTERED]', $safeExtra['password']);
        $this->assertEquals('[FILTERED]', $safeExtra['api_key']);
        $this->assertEquals(['theme' => 'dark'], $safeExtra['preferences']);
    }

    public function testToSafeArray(): void
    {
        $context = new UserContext(
            id: '123',
            email: 'john@example.com',
            username: 'johndoe',
            ip: '192.168.1.1',
            extra: ['role' => 'admin', 'password' => 'secret']
        );

        $safeArray = $context->toSafeArray();

        $this->assertEquals('123', $safeArray['id']);
        $this->assertEquals('j**n@example.com', $safeArray['email']);
        $this->assertEquals('johndoe', $safeArray['username']);
        $this->assertEquals('192.168.1.xxx', $safeArray['ip']);
        $this->assertEquals('admin', $safeArray['extra']['role']);
        $this->assertEquals('[FILTERED]', $safeArray['extra']['password']);
    }

    public function testGetSummary(): void
    {
        $fullContext = new UserContext(
            id: '123',
            name: 'John Doe',
            ip: '192.168.1.1',
            extra: ['role' => 'admin']
        );
        $summary = $fullContext->getSummary();
        $this->assertStringContainsString('John Doe', $summary);
        $this->assertStringContainsString('ID: 123', $summary);
        $this->assertStringContainsString('IP: 192.168.1.xxx', $summary);
        $this->assertStringContainsString('1 extra field(s)', $summary);

        $anonymous = new UserContext();
        $this->assertEquals('Anonymous User', $anonymous->getSummary());
    }

    public function testToArray(): void
    {
        $context = new UserContext(
            id: '123',
            email: 'john@example.com',
            extra: ['role' => 'admin']
        );

        $array = $context->toArray();

        $this->assertEquals('123', $array['id']);
        $this->assertEquals('john@example.com', $array['email']);
        $this->assertEquals('admin', $array['role']);
        $this->assertArrayNotHasKey('username', $array); // null values filtered
    }
}