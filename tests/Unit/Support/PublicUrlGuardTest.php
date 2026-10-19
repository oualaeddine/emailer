<?php

namespace Tests\Unit\Support;

use App\Modules\Templates\Services\HostResolver;
use App\Support\Network\PublicUrlGuard;
use App\Support\Network\UnsafeUrlException;
use PHPUnit\Framework\TestCase;

class PublicUrlGuardTest extends TestCase
{
    /** @param array<string, list<string>> $map */
    private function guard(array $map = []): PublicUrlGuard
    {
        return new PublicUrlGuard(new class($map) extends HostResolver
        {
            /** @param array<string, list<string>> $map */
            public function __construct(private array $map) {}

            public function resolve(string $host): array
            {
                return $this->map[$host] ?? (filter_var($host, FILTER_VALIDATE_IP) ? [$host] : []);
            }
        });
    }

    public function test_public_host_is_resolved_and_pinned(): void
    {
        $guard = $this->guard(['example.test' => ['93.184.216.34']]);

        $resolved = $guard->resolve('https://example.test/path');

        $this->assertSame(['host' => 'example.test', 'port' => 443, 'ip' => '93.184.216.34'], $resolved);
        $options = $guard->curlOptions($resolved);
        $this->assertFalse($options['allow_redirects']);
        $this->assertSame(['example.test:443:93.184.216.34'], $options['curl'][CURLOPT_RESOLVE]);
    }

    /** @dataProvider blockedIps */
    public function test_private_ranges_are_refused(string $ip): void
    {
        $this->assertFalse($this->guard()->isPublicIp($ip), $ip);
        $this->assertNull($this->guard(['x.test' => [$ip]])->tryResolve('http://x.test/'));
    }

    /** @return array<string, array{string}> */
    public static function blockedIps(): array
    {
        return array_map(fn ($ip) => [$ip], [
            'loopback' => '127.0.0.1', 'rfc1918a' => '10.1.2.3', 'rfc1918b' => '172.16.0.1', 'rfc1918c' => '192.168.1.1',
            'metadata' => '169.254.169.254', 'cgnat' => '100.64.0.1', 'multicast' => '224.0.0.1', 'zero' => '0.0.0.0',
            'v6 loopback' => '::1', 'v6 ula' => 'fd00::1', 'v6 link-local' => 'fe80::1', 'v6 multicast' => 'ff02::1',
            'v6 mapped private' => '::ffff:10.0.0.1', 'v6 mapped loopback' => '::ffff:127.0.0.1', 'garbage' => 'nope',
        ]);
    }

    public function test_public_ips_are_accepted(): void
    {
        $this->assertTrue($this->guard()->isPublicIp('93.184.216.34'));
        $this->assertTrue($this->guard()->isPublicIp('2606:2800:220:1::1'));
        $this->assertTrue($this->guard()->isPublicIp('::ffff:93.184.216.34'));
    }

    public function test_invalid_urls_and_unresolved_hosts(): void
    {
        $guard = $this->guard();
        foreach (['ftp://example.test/', 'file:///etc/passwd', 'javascript:alert(1)', 'http://user:pw@example.test/', 'not a url'] as $url) {
            try {
                $guard->resolve($url);
                $this->fail("Accepted {$url}");
            } catch (UnsafeUrlException $e) {
                $this->assertSame(UnsafeUrlException::INVALID, $e->reason);
            }
        }
        $this->expectException(UnsafeUrlException::class);
        $guard->resolve('http://unknown.test/');
    }

    public function test_mixed_dns_answer_with_one_private_ip_is_refused(): void
    {
        $this->assertNull($this->guard(['rebind.test' => ['93.184.216.34', '10.0.0.1']])->tryResolve('http://rebind.test/'));
    }
}
