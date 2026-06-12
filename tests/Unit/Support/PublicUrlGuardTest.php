<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\PublicUrlGuard;
use PHPUnit\Framework\TestCase;

class PublicUrlGuardTest extends TestCase
{
    /**
     * @return list<array{0: string}>
     */
    public static function blockedUrls(): array
    {
        return [
            ['http://127.0.0.1/'],
            ['http://127.0.0.1:8080/internal'],
            ['http://localhost/'],            // resolves to loopback
            ['http://169.254.169.254/latest/meta-data/'], // cloud metadata
            ['http://10.0.0.5/'],
            ['http://172.16.0.1/'],
            ['http://192.168.1.1/'],
            ['http://[::1]/'],
            ['ftp://example.com/'],           // wrong scheme
            ['file:///etc/passwd'],
            ['http://0.0.0.0/'],
            ['not-a-url'],
        ];
    }

    /**
     * @dataProvider blockedUrls
     */
    public function test_internal_and_invalid_urls_are_blocked(string $url): void
    {
        $this->assertFalse(PublicUrlGuard::isPublicUrl($url), "should block: {$url}");
    }

    /**
     * @return list<array{0: string}>
     */
    public static function allowedUrls(): array
    {
        return [
            ['http://8.8.8.8/hook'],
            ['https://93.184.216.34/'],  // example.com's public IP literal
        ];
    }

    /**
     * @dataProvider allowedUrls
     */
    public function test_public_addresses_are_allowed(string $url): void
    {
        $this->assertTrue(PublicUrlGuard::isPublicUrl($url), "should allow: {$url}");
    }
}
