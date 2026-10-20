<?php

namespace App\Modules\Tracking\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Tracking\Models\Message;
use App\Modules\Tracking\Services\TrackingEventRecorder;
use App\Modules\Tracking\Support\AppleMppDetector;
use App\Modules\Tracking\Support\BotUserAgentDetector;
use App\Modules\Tracking\Support\TrackingContext;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * docs/21-open-click-tracking.md §21.1 — `TrackingPixelController`
 * equivalent: `GET /t/o/{message}.gif`, public/unauthenticated, gated by
 * the `signed` route middleware (see routes/web.php) which rejects a
 * tampered or expired link with a 403 before this controller ever runs.
 *
 * Always returns the pixel image regardless of whether the hit was
 * counted (a bot-filtered request must look identical to the requester —
 * silently not counting it, not erroring or omitting the image, is the
 * whole point of §21.5's bot filtering).
 *
 * WP-70 (doc 43 §43.6.5, doc 45 §45.6.4): an Apple Mail Privacy Protection
 * proxy fetch is not filtered as a bot — it is counted (viewed / uniqueViews
 * / appleMppOpens) and flagged `is_proxy_open` on its `message_events` row,
 * which also carries the parsed user agent and GeoIP data.
 */
class TrackOpenController extends Controller
{
    /**
     * 1x1 transparent GIF, inline bytes (no file dependency per the task
     * spec) — the smallest valid transparent GIF89a.
     */
    private const PIXEL_GIF_BASE64 = 'R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==';

    public function __construct(
        private readonly TrackingEventRecorder $recorder,
        private readonly BotUserAgentDetector $botDetector,
        private readonly AppleMppDetector $mppDetector,
    ) {}

    public function open(Request $request, Message $message): Response
    {
        $context = TrackingContext::fromRequest($request);

        if ($this->mppDetector->isProxyOpen($context->userAgent, $context->ip)
            || ! $this->botDetector->isBot($context->userAgent)) {
            $this->recorder->recordOpen($message, $context);
        }

        $pixel = base64_decode(self::PIXEL_GIF_BASE64, true) ?: '';

        return response($pixel, 200, [
            'Content-Type' => 'image/gif',
            'Content-Length' => (string) strlen($pixel),
            // docs/21-open-click-tracking.md §21.1 — "Cache-Control:
            // no-store on the tracking endpoint itself" (the endpoint must
            // be hit on every send, even to the same recipient across
            // messages, since each `message_uuid` is unique).
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
            'Pragma' => 'no-cache',
        ]);
    }
}
