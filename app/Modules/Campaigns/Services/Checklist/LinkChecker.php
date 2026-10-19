<?php

namespace App\Modules\Campaigns\Services\Checklist;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * docs/43-brevo-parity-plan.md §43.4.2 item 6 — "links valid". Extracts the
 * http(s) links of a design and probes them (HEAD, then GET fallback).
 * Results are cached per campaign content hash.
 */
class LinkChecker
{
    public const MAX_URLS = 100;
    public const TIMEOUT_SECONDS = 5;
    public const CACHE_TTL_HOURS = 24;

    public static function cacheKey(string $campaignUuid, ?string $html): string
    {
        return "campaign_checklist.links.{$campaignUuid}.".sha1((string) $html);
    }

    /**
     * @return list<string>
     */
    public function extractUrls(?string $html): array
    {
        if ($html === null || $html === '') {
            return [];
        }

        preg_match_all('/<a\b[^>]*?\bhref\s*=\s*(?:"([^"]*)"|\'([^\']*)\')/i', $html, $m, PREG_SET_ORDER);
        $urls = [];
        foreach ($m as $match) {
            $url = trim(html_entity_decode($match[1] !== '' ? $match[1] : ($match[2] ?? '')));
            if (! preg_match('#^https?://#i', $url) || str_contains($url, '{{') || str_contains($url, '{%')) {
                continue;
            }
            $urls[$url] = true;
        }

        return array_slice(array_keys($urls), 0, self::MAX_URLS);
    }

    /**
     * @return int|null HTTP status, or 0 when unreachable
     */
    public function probe(string $url): int
    {
        try {
            $status = Http::timeout(self::TIMEOUT_SECONDS)->head($url)->status();
            if ($status < 400) {
                return $status;
            }
        } catch (Throwable) {
            // fall through to GET
        }

        try {
            return Http::timeout(self::TIMEOUT_SECONDS)->get($url)->status();
        } catch (Throwable) {
            return 0;
        }
    }

    /**
     * @return array{checked: int, broken: list<array{url: string, status: int}>}
     */
    public function check(?string $html): array
    {
        $broken = [];
        $urls = $this->extractUrls($html);
        foreach ($urls as $url) {
            $status = $this->probe($url);
            if ($status === 0 || $status >= 400) {
                $broken[] = ['url' => $url, 'status' => $status];
            }
        }

        return ['checked' => count($urls), 'broken' => $broken];
    }

    /**
     * @return array{checked: int, broken: list<array{url: string, status: int}>}|null
     */
    public function cached(string $campaignUuid, ?string $html): ?array
    {
        $value = Cache::get(self::cacheKey($campaignUuid, $html));

        return is_array($value) ? $value : null;
    }
}
