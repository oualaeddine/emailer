<?php

namespace App\Modules\Tracking\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Tracking\Models\Message;
use App\Modules\Tracking\Services\TrackingEventRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * docs/21-open-click-tracking.md §21.2 — `ClickRedirectController`
 * equivalent: `GET /t/c/{message}?url=...`, public/unauthenticated, gated
 * by the `signed` route middleware (routes/web.php), which rejects a
 * tampered or expired link — including a tampered `url` query parameter,
 * since Laravel's signature covers the whole query string — with a 403
 * before this controller ever runs (§21.6's open-redirect/anti-tamper
 * requirement).
 *
 * §21.2 also mentions "click validation is trusted more than opens
 * generally" — clicks are still bot-flagged for automated link-scanning
 * security tools in the doc's full design, but that distinction requires
 * the `message_events.metadata.is_likely_bot` audit trail this WP doesn't
 * build (see TrackingRenderer's docblock); every valid, signed click hit
 * is recorded here.
 */
class TrackClickController extends Controller
{
    public function __construct(private readonly TrackingEventRecorder $recorder)
    {
    }

    public function redirect(Request $request, Message $message): RedirectResponse
    {
        $target = $this->sanitizeTargetUrl((string) $request->query('url', ''));

        $this->recorder->recordClick($message);

        return redirect()->away($target);
    }

    /**
     * The `signed` middleware already guarantees `url` is exactly what we
     * originally issued (docs/21-open-click-tracking.md §21.6 — "require
     * it to be present in the signed payload so it can't be tampered").
     * This is a defense-in-depth scheme check on top of that: only
     * `http`/`https` destinations are ever redirected to, so a
     * `javascript:`/`data:` URI could never reach here even if it were
     * somehow present in a legitimately-signed payload (e.g. a future bug
     * in TrackingRenderer's own scheme filtering).
     */
    private function sanitizeTargetUrl(string $url): string
    {
        $url = trim($url);
        $scheme = parse_url($url, PHP_URL_SCHEME);

        if ($url === '' || ! in_array(strtolower((string) $scheme), ['http', 'https'], true)) {
            throw new BadRequestHttpException('Invalid tracking redirect target.');
        }

        return $url;
    }
}
