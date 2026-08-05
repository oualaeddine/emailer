<?php

namespace App\Modules\Tracking\Support;

/**
 * docs/21-open-click-tracking.md §21.5 — "Bot Filtering", user-agent
 * heuristics: "known scanner/bot user-agent patterns ... are ... excluded
 * from headline 'unique opens' metrics".
 *
 * This work package has no `message_events` table (see
 * TrackingRenderer's docblock for why), so there is no "record but flag
 * is_likely_bot=true, still visible in raw data" split to implement —
 * instead a detected bot/prefetcher hit is filtered before any counter is
 * touched at all (open_count/opened_at/status untouched), which is the
 * simplest correct behaviour available without that table.
 *
 * The doc describes this as "configurable list in Settings"; wiring an
 * actual Settings-backed list is out of this WP's scope (no Settings UI
 * touchpoint was part of the WP-21 task list) — the pattern list below is
 * a static, code-level default covering the dominant real-world sources
 * of inflated automated opens and is the integration point for a future
 * Settings-driven override.
 */
class BotUserAgentDetector
{
    /**
     * Case-insensitive substrings matched against the request's
     * `User-Agent` header. Covers:
     * - Apple Mail Privacy Protection's prefetch proxy (fetches every
     *   image at delivery time regardless of whether the recipient ever
     *   opens the message).
     * - Corporate/security email gateway scanners that prefetch links and
     *   images to scan for malware before delivery.
     * - Generic bot/crawler/spider User-Agent conventions.
     */
    private const BOT_UA_PATTERNS = [
        'applemailproxy',
        'apple-mail/',
        'googleimageproxy',
        'yahoo! slurp',
        'proofpoint',
        'mimecast',
        'barracuda',
        'symantec',
        'messagelabs',
        'outlook-ews',
        'facebookexternalhit',
        'bot',
        'crawler',
        'spider',
        'scanner',
        'preview',
    ];

    /**
     * A missing/empty User-Agent is itself treated as suspicious: every
     * real mail client renders the pixel `<img>` via its own HTTP client,
     * which always sends a non-empty User-Agent, whereas bare
     * scanner/prefetch tooling frequently omits it.
     */
    public function isBot(?string $userAgent): bool
    {
        $userAgent = trim((string) $userAgent);

        if ($userAgent === '') {
            return true;
        }

        $haystack = mb_strtolower($userAgent);

        foreach (self::BOT_UA_PATTERNS as $pattern) {
            if (str_contains($haystack, $pattern)) {
                return true;
            }
        }

        return false;
    }
}
