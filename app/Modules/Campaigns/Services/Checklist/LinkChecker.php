<?php

namespace App\Modules\Campaigns\Services\Checklist;

use App\Support\Network\PublicUrlGuard;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
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

    public const BATCH_SIZE = 10;
    public const TOTAL_BUDGET_SECONDS = 60;

    public function __construct(private readonly PublicUrlGuard $guard) {}

    /**
     * @return int HTTP status, or 0 when unreachable or blocked (non-public host)
     */
    public function probe(string $url): int
    {
        $resolved = $this->guard->tryResolve($url);
        if ($resolved === null) {
            return 0;
        }
        $options = $this->guard->curlOptions($resolved);

        try {
            $status = Http::timeout(self::TIMEOUT_SECONDS)->withOptions($options)->head($url)->status();
            if ($status < 400) {
                return $status;
            }
        } catch (Throwable) {
            // fall through to GET
        }

        try {
            return Http::timeout(self::TIMEOUT_SECONDS)->withOptions($options)->get($url)->status();
        } catch (Throwable) {
            return 0;
        }
    }

    /**
     * Probes URLs in parallel (batches of 10, ~60 s total budget). Non-public
     * hosts are never contacted; redirects are not followed (a 3xx is ok).
     *
     * @param  list<string>  $urls
     * @return array<string, int> url => HTTP status, 0 when unreachable / blocked / out of budget
     */
    public function probeMany(array $urls): array
    {
        $deadline = microtime(true) + self::TOTAL_BUDGET_SECONDS;
        $result = [];
        $targets = [];
        foreach (array_values(array_unique($urls)) as $url) {
            $resolved = $this->guard->tryResolve($url);
            $result[$url] = 0;
            if ($resolved !== null) {
                $targets[$url] = $this->guard->curlOptions($resolved);
            }
        }

        foreach (array_chunk($targets, self::BATCH_SIZE, true) as $batch) {
            if (microtime(true) >= $deadline) {
                break;
            }
            $statuses = $this->pool($batch, 'head');
            $retry = array_filter($batch, fn ($o, $url) => $statuses[$url] === 0 || $statuses[$url] >= 400, ARRAY_FILTER_USE_BOTH);
            if ($retry !== []) {
                foreach ($this->pool($retry, 'get') as $url => $status) {
                    $statuses[$url] = $status;
                }
            }
            $result = $statuses + $result;
        }

        return $result;
    }

    /**
     * @param  array<string, array<string, mixed>>  $batch url => guzzle options
     * @return array<string, int>
     */
    private function pool(array $batch, string $method): array
    {
        $urls = array_keys($batch);
        try {
            $responses = Http::pool(function (Pool $pool) use ($urls, $batch, $method) {
                foreach ($urls as $i => $url) {
                    $pool->as((string) $i)->timeout(self::TIMEOUT_SECONDS)->withOptions($batch[$url])->{$method}($url);
                }
            });
        } catch (Throwable) {
            $responses = [];
        }

        $statuses = [];
        foreach ($urls as $i => $url) {
            $r = $responses[(string) $i] ?? null;
            $statuses[$url] = $r instanceof Response ? $r->status() : 0;
        }

        return $statuses;
    }

    /**
     * @return array{checked: int, broken: list<array{url: string, status: int, reason?: string}>}
     */
    public function check(?string $html): array
    {
        $broken = [];
        $urls = $this->extractUrls($html);
        foreach ($this->probeMany($urls) as $url => $status) {
            if ($status === 0 || $status >= 400) {
                $entry = ['url' => $url, 'status' => $status];
                if ($status === 0 && $this->guard->tryResolve($url) === null) {
                    $entry['reason'] = 'blocked';
                }
                $broken[] = $entry;
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
