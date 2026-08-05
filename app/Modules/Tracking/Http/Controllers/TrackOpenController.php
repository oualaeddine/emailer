<?php

namespace App\Modules\Tracking\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Tracking\Models\Message;
use App\Modules\Tracking\Services\TrackingEventRecorder;
use App\Modules\Tracking\Support\BotUserAgentDetector;
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
    ) {
    }

    public function open(Request $request, Message $message): Response
    {
        if (! $this->botDetector->isBot($request->userAgent())) {
            $this->recorder->recordOpen($message);
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
