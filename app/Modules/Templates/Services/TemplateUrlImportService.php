<?php

namespace App\Modules\Templates\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

/**
 * docs/45 §45.4.4 — `POST /templates/import-from-url`. SSRF-guarded fetch:
 * http(s) only, DNS-resolve then refuse private / loopback / link-local /
 * multicast ranges, each of ≤ 3 redirects re-checked, 10 s timeout, ≤ 2 MB,
 * HTML only. Nothing is persisted.
 */
class TemplateUrlImportService
{
    public const MAX_BYTES = 2 * 1024 * 1024;
    public const MAX_REDIRECTS = 3;
    public const TIMEOUT_SECONDS = 10;

    public function __construct(
        private readonly HostResolver $resolver,
        private readonly HtmlSanitizer $sanitizer,
    ) {}

    /**
     * @return array{html: string, title: string|null, assets: list<array{filename: string, url: string}>, warnings: list<string>}
     */
    public function import(string $url): array
    {
        $current = $url;
        $response = null;

        for ($hop = 0; $hop <= self::MAX_REDIRECTS; $hop++) {
            $resolved = $this->assertSafe($current);

            try {
                $options = ['allow_redirects' => false];
                if (defined('CURLOPT_RESOLVE')) {
                    // Pin the vetted IP so the connection cannot be rebound after the check.
                    $options['curl'] = [CURLOPT_RESOLVE => [$resolved['host'].':'.$resolved['port'].':'.$resolved['ip']]];
                }
                $response = Http::timeout(self::TIMEOUT_SECONDS)
                    ->withOptions($options)
                    ->accept('text/html,application/xhtml+xml')
                    ->get($current);
            } catch (ConnectionException) {
                $this->fail("Impossible de récupérer l'URL (délai dépassé ou serveur injoignable).");
            }

            if ($response->redirect()) {
                $location = $response->header('Location');
                if ($location === '') {
                    $this->fail("Redirection invalide.");
                }
                $current = $this->absolutize($current, $location);
                $response = null;

                continue;
            }

            break;
        }

        if ($response === null) {
            $this->fail('Trop de redirections.');
        }
        if (! $response->successful()) {
            $this->fail("L'URL a répondu avec le statut {$response->status()}.");
        }

        $contentType = strtolower($response->header('Content-Type'));
        if (! str_contains($contentType, 'text/html') && ! str_contains($contentType, 'application/xhtml')) {
            $this->fail("Le contenu de l'URL n'est pas du HTML.");
        }

        $declared = (int) $response->header('Content-Length');
        $body = $response->body();
        if ($declared > self::MAX_BYTES || strlen($body) > self::MAX_BYTES) {
            $this->fail('Le contenu dépasse la taille maximale de 2 Mo.');
        }

        $clean = $this->sanitizer->clean($body);

        return ['html' => $clean['html'], 'title' => $clean['title'], 'assets' => [], 'warnings' => $clean['warnings']];
    }

    /**
     * @return array{host: string, port: int, ip: string}
     */
    private function assertSafe(string $url): array
    {
        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = (string) ($parts['host'] ?? '');

        if (! in_array($scheme, ['http', 'https'], true) || $host === '' || isset($parts['user']) || isset($parts['pass'])) {
            $this->fail('URL invalide : seuls les liens http(s) sont acceptés.');
        }

        $host = trim($host, '[]');
        $ips = $this->resolver->resolve($host);
        if ($ips === []) {
            $this->fail("Le nom d'hôte est introuvable.");
        }
        foreach ($ips as $ip) {
            if (! $this->isPublicIp($ip)) {
                $this->fail("Cette adresse n'est pas autorisée (réseau privé ou local).");
            }
        }

        return ['host' => $host, 'port' => (int) ($parts['port'] ?? ($scheme === 'https' ? 443 : 80)), 'ip' => $ips[0]];
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

    private function absolutize(string $base, string $location): string
    {
        if (preg_match('#^[a-z][a-z0-9+.-]*:#i', $location) === 1) {
            return $location;
        }
        $parts = parse_url($base);
        $origin = ($parts['scheme'] ?? 'http').'://'.($parts['host'] ?? '').(isset($parts['port']) ? ':'.$parts['port'] : '');
        if (str_starts_with($location, '//')) {
            return ($parts['scheme'] ?? 'http').':'.$location;
        }
        if (str_starts_with($location, '/')) {
            return $origin.$location;
        }

        return $origin.rtrim(dirname($parts['path'] ?? '/'), '/').'/'.$location;
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['url' => [$message]]);
    }
}
