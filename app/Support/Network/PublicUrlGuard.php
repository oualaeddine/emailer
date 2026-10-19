<?php

namespace App\Support\Network;

use App\Modules\Templates\Services\HostResolver;

/**
 * Shared SSRF guard: http(s) only, DNS-resolve the host and refuse private /
 * loopback / link-local / multicast / reserved / IPv6 ULA & mapped ranges.
 * `curlOptions()` pins the vetted IP so the connection cannot be re-bound.
 */
class PublicUrlGuard
{
    public function __construct(private readonly HostResolver $resolver) {}

    /**
     * @return array{host: string, port: int, ip: string}
     *
     * @throws UnsafeUrlException
     */
    public function resolve(string $url): array
    {
        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = (string) ($parts['host'] ?? '');

        if (! in_array($scheme, ['http', 'https'], true) || $host === '' || isset($parts['user']) || isset($parts['pass'])) {
            throw new UnsafeUrlException(UnsafeUrlException::INVALID);
        }

        $host = trim($host, '[]');
        $ips = $this->resolver->resolve($host);
        if ($ips === []) {
            throw new UnsafeUrlException(UnsafeUrlException::UNRESOLVED);
        }
        foreach ($ips as $ip) {
            if (! $this->isPublicIp($ip)) {
                throw new UnsafeUrlException(UnsafeUrlException::PRIVATE);
            }
        }

        return ['host' => $host, 'port' => (int) ($parts['port'] ?? ($scheme === 'https' ? 443 : 80)), 'ip' => $ips[0]];
    }

    /**
     * @return array{host: string, port: int, ip: string}|null null when the URL is not a public http(s) URL
     */
    public function tryResolve(string $url): ?array
    {
        try {
            return $this->resolve($url);
        } catch (UnsafeUrlException) {
            return null;
        }
    }

    /**
     * Guzzle options: no redirects, and the vetted IP pinned via CURLOPT_RESOLVE.
     *
     * @param  array{host: string, port: int, ip: string}  $resolved
     * @return array<string, mixed>
     */
    public function curlOptions(array $resolved): array
    {
        $options = ['allow_redirects' => false];
        if (defined('CURLOPT_RESOLVE')) {
            $ip = str_contains($resolved['ip'], ':') ? '['.$resolved['ip'].']' : $resolved['ip'];
            $options['curl'] = [CURLOPT_RESOLVE => [$resolved['host'].':'.$resolved['port'].':'.$ip]];
        }

        return $options;
    }

    public function isPublicIp(string $ip): bool
    {
        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return false;
        }

        if (stripos($ip, '::ffff:') === 0 && filter_var(substr($ip, 7), FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
            return $this->isPublicIp(substr($ip, 7));
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return false;
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
            $long = ip2long($ip);

            // Multicast / reserved (224.0.0.0+), CGNAT 100.64.0.0/10, unspecified.
            return $long !== false && $long < ip2long('224.0.0.0')
                && ! ($long >= ip2long('100.64.0.0') && $long <= ip2long('100.127.255.255'))
                && $long !== 0;
        }

        $packed = inet_pton($ip);

        // ff00::/8 multicast, fe80::/10 link-local, fc00::/7 unique-local, ::/::1.
        return $packed !== false
            && ord($packed[0]) !== 0xff
            && (ord($packed[0]) & 0xfe) !== 0xfc
            && ! (ord($packed[0]) === 0xfe && (ord($packed[1]) & 0xc0) === 0x80)
            && $packed !== str_repeat("\0", 16)
            && $packed !== str_repeat("\0", 15)."\1";
    }
}
