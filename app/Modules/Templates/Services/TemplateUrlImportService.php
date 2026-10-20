<?php

namespace App\Modules\Templates\Services;

use App\Support\Network\PublicUrlGuard;
use App\Support\Network\UnsafeUrlException;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Throwable;

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
        private readonly PublicUrlGuard $guard,
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

            $tooBig = false;
            try {
                $options = $this->guard->curlOptions($resolved) + [
                    'stream' => true,
                    'on_headers' => function ($r) use (&$tooBig): void {
                        if ((int) $r->getHeaderLine('Content-Length') > self::MAX_BYTES) {
                            $tooBig = true;

                            throw new \RuntimeException('too large');
                        }
                    },
                ];
                $response = Http::timeout(self::TIMEOUT_SECONDS)
                    ->withOptions($options)
                    ->accept('text/html,application/xhtml+xml')
                    ->get($current);
            } catch (Throwable) {
                $this->fail($tooBig
                    ? 'Le contenu dépasse la taille maximale de 2 Mo.'
                    : "Impossible de récupérer l'URL (délai dépassé ou serveur injoignable).");
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

        if ((int) $response->header('Content-Length') > self::MAX_BYTES) {
            $this->fail('Le contenu dépasse la taille maximale de 2 Mo.');
        }
        $stream = $response->toPsrResponse()->getBody();
        $body = '';
        while (! $stream->eof()) {
            $body .= $stream->read(65536);
            if (strlen($body) > self::MAX_BYTES) {
                $this->fail('Le contenu dépasse la taille maximale de 2 Mo.');
            }
        }

        $clean = $this->sanitizer->clean($body);

        return ['html' => $clean['html'], 'title' => $clean['title'], 'assets' => [], 'warnings' => $clean['warnings']];
    }

    /**
     * @return array{host: string, port: int, ip: string}
     */
    private function assertSafe(string $url): array
    {
        try {
            return $this->guard->resolve($url);
        } catch (UnsafeUrlException $e) {
            $this->fail(match ($e->reason) {
                UnsafeUrlException::UNRESOLVED => "Le nom d'hôte est introuvable.",
                UnsafeUrlException::PRIVATE => "Cette adresse n'est pas autorisée (réseau privé ou local).",
                default => 'URL invalide : seuls les liens http(s) sont acceptés.',
            });
        }
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
