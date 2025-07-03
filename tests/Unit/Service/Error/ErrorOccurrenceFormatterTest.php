<?php

namespace App\Tests\Unit\Service\Error;

use App\Service\Error\ErrorOccurrenceFormatter;
use PHPUnit\Framework\TestCase;

class ErrorOccurrenceFormatterTest extends TestCase
{
    private ErrorOccurrenceFormatter $formatter;

    protected function setUp(): void
    {
        $this->formatter = new ErrorOccurrenceFormatter();
    }

    public function testFormatMemoryUsage(): void
    {
        // Test null
        $this->assertNull($this->formatter->formatMemoryUsage(null));
        
        // Test bytes
        $this->assertEquals('512 B', $this->formatter->formatMemoryUsage(512));
        
        // Test KB
        $this->assertEquals('1 KB', $this->formatter->formatMemoryUsage(1024));
        $this->assertEquals('1.5 KB', $this->formatter->formatMemoryUsage(1536));
        
        // Test MB
        $this->assertEquals('1 MB', $this->formatter->formatMemoryUsage(1024 * 1024));
        $this->assertEquals('2.5 MB', $this->formatter->formatMemoryUsage(2.5 * 1024 * 1024));
        
        // Test GB
        $this->assertEquals('1 GB', $this->formatter->formatMemoryUsage(1024 * 1024 * 1024));
        
        // Test TB
        $this->assertEquals('1 TB', $this->formatter->formatMemoryUsage(1024 * 1024 * 1024 * 1024));
    }

    public function testFormatExecutionTime(): void
    {
        // Test null
        $this->assertNull($this->formatter->formatExecutionTime(null));
        
        // Test milliseconds (less than 1 second)
        $this->assertEquals('500 ms', $this->formatter->formatExecutionTime(0.5));
        $this->assertEquals('123 ms', $this->formatter->formatExecutionTime(0.123));
        
        // Test seconds
        $this->assertEquals('1.5 s', $this->formatter->formatExecutionTime(1.5));
        $this->assertEquals('30.25 s', $this->formatter->formatExecutionTime(30.25));
        
        // Test minutes
        $this->assertEquals('1 min 30 s', $this->formatter->formatExecutionTime(90));
        $this->assertEquals('2 min 15 s', $this->formatter->formatExecutionTime(135.5));
    }

    public function testFormatStackTrace(): void
    {
        $stackTrace = "Line 1\nLine 2\nLine 3\nLine 4\nLine 5\nLine 6";
        
        // Test with line numbers
        $result = $this->formatter->formatStackTrace($stackTrace, 3, true);
        $expected = " 1: Line 1\n 2: Line 2\n 3: Line 3";
        $this->assertEquals($expected, $result);
        
        // Test without line numbers
        $result = $this->formatter->formatStackTrace($stackTrace, 3, false);
        $expected = "Line 1\nLine 2\nLine 3";
        $this->assertEquals($expected, $result);
        
        // Test empty stack trace
        $this->assertEquals('', $this->formatter->formatStackTrace(''));
    }

    public function testGetShortStackTrace(): void
    {
        $stackTrace = "Line 1\nLine 2\nLine 3\nLine 4\nLine 5\nLine 6";
        
        // Test default lines (5)
        $result = $this->formatter->getShortStackTrace($stackTrace);
        $expected = "Line 1\nLine 2\nLine 3\nLine 4\nLine 5";
        $this->assertEquals($expected, $result);
        
        // Test custom lines
        $result = $this->formatter->getShortStackTrace($stackTrace, 2);
        $expected = "Line 1\nLine 2";
        $this->assertEquals($expected, $result);
        
        // Test empty stack trace
        $this->assertEquals('', $this->formatter->getShortStackTrace(''));
    }

    public function testFormatIpAddress(): void
    {
        // Test null
        $this->assertNull($this->formatter->formatIpAddress(null));
        
        // Test without masking
        $this->assertEquals('192.168.1.100', $this->formatter->formatIpAddress('192.168.1.100', false));
        
        // Test IPv4 with masking
        $this->assertEquals('192.168.1.xxx', $this->formatter->formatIpAddress('192.168.1.100', true));
        
        // Test IPv6 with masking
        $result = $this->formatter->formatIpAddress('2001:0db8:85a3:0000:0000:8a2e:0370:7334', true);
        $this->assertEquals('2001:0db8:85a3:0000:xxxx:xxxx:xxxx:xxxx', $result);
    }

    public function testFormatUserAgent(): void
    {
        // Test null
        $this->assertNull($this->formatter->formatUserAgent(null));
        
        // Test Chrome
        $chromeUA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36';
        $this->assertStringContainsString('Chrome', $this->formatter->formatUserAgent($chromeUA));
        
        // Test Firefox
        $firefoxUA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:89.0) Gecko/20100101 Firefox/89.0';
        $this->assertStringContainsString('Firefox', $this->formatter->formatUserAgent($firefoxUA));
        
        // Test long user agent (should be truncated)
        $longUA = str_repeat('a', 100);
        $result = $this->formatter->formatUserAgent($longUA);
        $this->assertStringEndsWith('...', $result);
        $this->assertLessThanOrEqual(53, strlen($result)); // 50 + "..."
    }

    public function testFormatUrl(): void
    {
        // Test null
        $this->assertNull($this->formatter->formatUrl(null));
        
        // Test short URL (no truncation)
        $shortUrl = 'https://example.com/page';
        $this->assertEquals($shortUrl, $this->formatter->formatUrl($shortUrl));
        
        // Test long URL (with truncation)
        $longUrl = 'https://example.com/' . str_repeat('very-long-path/', 10) . 'page.html';
        $result = $this->formatter->formatUrl($longUrl, 50);
        $this->assertStringContainsString('...', $result);
        $this->assertLessThanOrEqual(50, strlen($result));
        
        // Test sensitive parameter hiding
        $urlWithParams = 'https://example.com/page?token=secret123&password=admin&normal=value';
        $result = $this->formatter->formatUrl($urlWithParams);
        $this->assertStringContainsString('token=***', $result);
        $this->assertStringContainsString('password=***', $result);
        $this->assertStringContainsString('normal=value', $result);
    }

    public function testFormatRelativeTime(): void
    {
        $now = new \DateTime();
        
        // Test "just now"
        $result = $this->formatter->formatRelativeTime($now);
        $this->assertEquals('À l\'instant', $result);
        
        // Test minutes ago
        $minutesAgo = (clone $now)->modify('-5 minutes');
        $result = $this->formatter->formatRelativeTime($minutesAgo);
        $this->assertEquals('il y a 5 minutes', $result);
        
        // Test hours ago
        $hoursAgo = (clone $now)->modify('-3 hours');
        $result = $this->formatter->formatRelativeTime($hoursAgo);
        $this->assertEquals('il y a 3 heures', $result);
        
        // Test days ago
        $daysAgo = (clone $now)->modify('-2 days');
        $result = $this->formatter->formatRelativeTime($daysAgo);
        $this->assertEquals('il y a 2 jours', $result);
    }

    public function testFormatContextArray(): void
    {
        // Test empty array
        $this->assertEquals('', $this->formatter->formatContextArray([]));
        
        // Test simple array
        $context = ['key1' => 'value1', 'key2' => 'value2'];
        $result = $this->formatter->formatContextArray($context);
        $this->assertStringContainsString('key1: \'value1\'', $result);
        $this->assertStringContainsString('key2: \'value2\'', $result);
        
        // Test nested array
        $context = [
            'user' => ['id' => 123, 'name' => 'John'],
            'request' => ['method' => 'POST']
        ];
        $result = $this->formatter->formatContextArray($context);
        $this->assertStringContainsString('user: [', $result);
        $this->assertStringContainsString('id: 123', $result);
        $this->assertStringContainsString('name: \'John\'', $result);
    }
}