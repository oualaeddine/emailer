<?php

namespace App\Modules\Tracking\Support;

use App\Modules\Tracking\Models\Message;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;

/**
 * docs/21-open-click-tracking.md §21.1-21.2 — builds the signed pixel/
 * click-redirect URLs injected into outbound HTML by
 * {@see \App\Modules\Tracking\Services\TrackingRenderer}.
 *
 * Mirrors App\Modules\Suppression\Support\UnsubscribeUrlGenerator's
 * established signed-URL pattern (docs/23-suppression-list.md §23.4, WP-11):
 * Laravel's `URL::signedRoute()` verifies the whole URL (including an
 * expiry timestamp) against `APP_KEY` with no persisted token state.
 *
 * `{message}` route parameters bind on `Message::getRouteKeyName()`
 * (`uuid`), which is 1:1 with a single recipient+send
 * (docs/21-open-click-tracking.md §21.3), so the signed URL alone
 * unambiguously identifies both "which message" and "was this URL/token
 * genuinely issued by us" without any additional lookup table.
 *
 * The click-redirect URL folds the original destination `url` into the
 * signed query string itself (rather than a `click_links` lookup row,
 * see TrackingRenderer's docblock for why that table isn't part of this
 * work package) — because the signature covers the full query string,
 * `url` cannot be tampered with independently of invalidating the
 * signature, which is what docs/21-open-click-tracking.md §21.6 requires
 * ("do not allow open-redirect abuse").
 */
class TrackingUrlGenerator
{
    /**
     * docs/21-open-click-tracking.md doesn't specify a link lifetime.
     * 30 days mirrors UnsubscribeUrlGenerator's rationale: comfortably
     * covers mailbox-provider link-scanning/prefetch delays and "I'll
     * read this later" recipient behaviour without staying valid forever.
     */
    private const DEFAULT_TTL_DAYS = 30;

    public function signedOpenUrl(Message $message, ?Carbon $expiresAt = null): string
    {
        return URL::signedRoute(
            'tracking.open',
            ['message' => $message->uuid],
            $expiresAt ?? Carbon::now()->addDays(self::DEFAULT_TTL_DAYS),
        );
    }

    public function signedClickUrl(Message $message, string $targetUrl, ?Carbon $expiresAt = null): string
    {
        return URL::signedRoute(
            'tracking.click',
            ['message' => $message->uuid, 'url' => $targetUrl],
            $expiresAt ?? Carbon::now()->addDays(self::DEFAULT_TTL_DAYS),
        );
    }
}
