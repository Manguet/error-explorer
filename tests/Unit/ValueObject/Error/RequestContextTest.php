<?php

namespace App\Tests\Unit\ValueObject\Error;

use App\ValueObject\Error\RequestContext;
use PHPUnit\Framework\TestCase;

class RequestContextTest extends TestCase
{
    public function testFromArrayWithAllFields(): void
    {
        $data = [
            'url' => 'https://example.com/api/test',
            'method' => 'POST',
            'headers' => ['Content-Type' => 'application/json'],
            'query' => ['param' => 'value'],
            'body' => ['key' => 'value'],
            'ip' => '192.168.1.1',
            'user_agent' => 'Mozilla/5.0...',
            'route' => 'api_test',
            'parameters' => ['id' => 123],
            'cookies' => ['session' => 'abc123'],
            'files' => ['upload' => 'file.txt']
        ];

        $context = RequestContext::fromArray($data);

        $this->assertEquals('https://example.com/api/test', $context->url);
        $this->assertEquals('POST', $context->method);
        $this->assertEquals(['Content-Type' => 'application/json'], $context->headers);
        $this->assertEquals(['param' => 'value'], $context->query);
        $this->assertEquals(['key' => 'value'], $context->body);
        $this->assertEquals('192.168.1.1', $context->ip);
        $this->assertEquals('Mozilla/5.0...', $context->userAgent);
        $this->assertEquals('api_test', $context->route);
        $this->assertEquals(['id' => 123], $context->parameters);
        $this->assertEquals(['session' => 'abc123'], $context->cookies);
        $this->assertEquals(['upload' => 'file.txt'], $context->files);
    }

    public function testFromArrayWithMinimalFields(): void
    {
        $data = [
            'url' => 'https://example.com',
            'method' => 'GET'
        ];

        $context = RequestContext::fromArray($data);

        $this->assertEquals('https://example.com', $context->url);
        $this->assertEquals('GET', $context->method);
        $this->assertNull($context->headers);
        $this->assertNull($context->ip);
    }

    public function testToArray(): void
    {
        $context = new RequestContext(
            url: 'https://example.com',
            method: 'POST',
            ip: '192.168.1.1'
        );

        $expected = [
            'url' => 'https://example.com',
            'method' => 'POST',
            'ip' => '192.168.1.1',
            'user_agent' => null
        ];

        $result = $context->toArray();
        
        $this->assertEquals('https://example.com', $result['url']);
        $this->assertEquals('POST', $result['method']);
        $this->assertEquals('192.168.1.1', $result['ip']);
        $this->assertArrayNotHasKey('headers', $result); // null values filtered
    }

    public function testIsValid(): void
    {
        $validContext = new RequestContext(url: 'https://example.com');
        $this->assertTrue($validContext->isValid());

        $validContext2 = new RequestContext(method: 'POST');
        $this->assertTrue($validContext2->isValid());

        $invalidContext = new RequestContext();
        $this->assertFalse($invalidContext->isValid());
    }

    public function testGetDisplayUrl(): void
    {
        $shortUrl = new RequestContext(url: 'https://example.com');
        $this->assertEquals('https://example.com', $shortUrl->getDisplayUrl());

        $longUrl = new RequestContext(url: str_repeat('https://example.com/very-long-path/', 10));
        $displayUrl = $longUrl->getDisplayUrl();
        $this->assertStringEndsWith('...', $displayUrl);
        $this->assertLessThanOrEqual(103, strlen($displayUrl)); // 100 + "..."

        $noUrl = new RequestContext();
        $this->assertNull($noUrl->getDisplayUrl());
    }

    public function testIsSecure(): void
    {
        $httpsContext = new RequestContext(url: 'https://example.com');
        $this->assertTrue($httpsContext->isSecure());

        $httpContext = new RequestContext(url: 'http://example.com');
        $this->assertFalse($httpContext->isSecure());

        $noUrlContext = new RequestContext();
        $this->assertFalse($noUrlContext->isSecure());
    }

    public function testGetMethodUpperCase(): void
    {
        $context = new RequestContext(method: 'post');
        $this->assertEquals('POST', $context->getMethodUpperCase());

        $noMethodContext = new RequestContext();
        $this->assertNull($noMethodContext->getMethodUpperCase());
    }

    public function testHasBody(): void
    {
        $withBody = new RequestContext(body: ['key' => 'value']);
        $this->assertTrue($withBody->hasBody());

        $emptyBody = new RequestContext(body: []);
        $this->assertFalse($emptyBody->hasBody());

        $noBody = new RequestContext();
        $this->assertFalse($noBody->hasBody());
    }

    public function testGetSafeHeaders(): void
    {
        $headers = [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer secret-token',
            'X-API-Key' => 'secret-key',
            'User-Agent' => 'Mozilla/5.0...'
        ];

        $context = new RequestContext(headers: $headers);
        $safeHeaders = $context->getSafeHeaders();

        $this->assertEquals('application/json', $safeHeaders['Content-Type']);
        $this->assertEquals('[FILTERED]', $safeHeaders['Authorization']);
        $this->assertEquals('[FILTERED]', $safeHeaders['X-API-Key']);
        $this->assertEquals('Mozilla/5.0...', $safeHeaders['User-Agent']);
    }

    public function testGetSummary(): void
    {
        $fullContext = new RequestContext(
            url: 'https://example.com/api',
            method: 'POST',
            ip: '192.168.1.1'
        );
        $summary = $fullContext->getSummary();
        $this->assertStringContainsString('POST', $summary);
        $this->assertStringContainsString('https://example.com/api', $summary);
        $this->assertStringContainsString('192.168.1.1', $summary);

        $emptyContext = new RequestContext();
        $this->assertEquals('Unknown request', $emptyContext->getSummary());
    }

    public function testIsPrivateIp(): void
    {
        $privateIp = new RequestContext(ip: '192.168.1.1');
        $this->assertTrue($privateIp->isPrivateIp());

        $publicIp = new RequestContext(ip: '8.8.8.8');
        $this->assertFalse($publicIp->isPrivateIp());

        $noIp = new RequestContext();
        $this->assertFalse($noIp->isPrivateIp());
    }
}