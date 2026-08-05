<?php

namespace App\Modules\Tracking\Services;

use App\Modules\Tracking\Models\Message;
use App\Modules\Tracking\Support\TrackingUrlGenerator;

/**
 * docs/38-rendering-pipeline.md §38.2 stage 5 ("Tracking injection") —
 * pixel + link-rewrite. Per §38.3's ordering rule, this stage must run
 * after signature injection (stage 4, so signature-embedded links are also
 * tracked) and before CSS inlining (stage 6, so the inliner's rules apply
 * to the tracking-injected `<img>`/rewritten `<a href>` too). It never runs
 * in preview contexts (§38.6).
 *
 * PIPELINE WIRING NOTE (see final report): this service is intentionally
 * built and tested as a standalone HTML-in/HTML-out unit and is NOT called
 * from App\Modules\DeliveryEngine\Jobs\SendEmailJob or
 * App\Modules\DeliveryEngine\Services\ComposerSendService — both files are
 * outside this work package's `app/Modules/Tracking/**` file-ownership
 * boundary (docs/42-parallel-execution-plan.md §42.6/§42.11) and must not
 * be edited here. Splicing stage 5 into the actual `SendEmailJob` send
 * sequence (between stage 4 signature injection and stage 6 CSS inlining)
 * is deferred to whoever owns `SendEmailJob.php` next.
 *
 * SCOPE NOTE — no `click_links`/`message_events` tables: docs/21 §21.2 and
 * §21.4 describe a `click_links` row per rewritten link (for per-link
 * analytics) and a `message_events` audit trail. Neither exists yet
 * (checked database/migrations/ — no such migrations). This work package's
 * task list (items 1-6) only requires per-message aggregate
 * open_count/click_count semantics, which the existing `messages` columns
 * already cover, so no new table/migration was added. Instead, the
 * original destination URL travels inside the *signed* click-redirect
 * query string itself (see TrackingUrlGenerator), which satisfies
 * §21.6's anti-tamper requirement without a lookup table. Per-link
 * breakdown analytics (§21.4) and the bot-event audit trail (§21.5) would
 * need `click_links`/`message_events` — left as a follow-up once
 * Campaigns (WP-20)'s `campaigns` table exists to hang campaign-level
 * analytics off of.
 *
 * Campaign-level `tracking_enabled` toggle (§21.6): `campaigns` doesn't
 * exist yet (WP-20, concurrent) and `messages` has no such column today,
 * so this renderer is unconditionally "tracking on" — the call site
 * (whoever wires this into the send pipeline) is expected to simply not
 * invoke `render()` at all for a message whose campaign has tracking
 * disabled, once that toggle exists.
 */
class TrackingRenderer
{
    public function __construct(private readonly TrackingUrlGenerator $urls)
    {
    }

    /**
     * Rewrites `<a href="...">` links first, then injects the open pixel —
     * order between those two doesn't matter functionally (they touch
     * disjoint parts of the markup), but link-rewriting first means the
     * injected pixel `<img>` is never itself accidentally re-matched by
     * the link-rewrite pass.
     */
    public function render(Message $message, string $html): string
    {
        $html = $this->rewriteLinks($message, $html);

        return $this->injectPixel($message, $html);
    }

    private function rewriteLinks(Message $message, string $html): string
    {
        $rewritten = preg_replace_callback(
            '/<a\b[^>]*>/i',
            function (array $tagMatch) use ($message): string {
                $rewrittenTag = preg_replace_callback(
                    '/\bhref\s*=\s*(["\'])(.*?)\1/i',
                    function (array $hrefMatch) use ($message): string {
                        $quote = $hrefMatch[1];
                        $href = trim(html_entity_decode($hrefMatch[2], ENT_QUOTES));

                        if (! $this->isTrackableHref($href)) {
                            return $hrefMatch[0];
                        }

                        $trackingUrl = $this->urls->signedClickUrl($message, $href);

                        return 'href='.$quote.htmlspecialchars($trackingUrl, ENT_QUOTES).$quote;
                    },
                    $tagMatch[0],
                    1,
                );

                return $rewrittenTag ?? $tagMatch[0];
            },
            $html,
        );

        return $rewritten ?? $html;
    }

    /**
     * Anchors that shouldn't be redirected through the click tracker:
     * empty/placeholder hrefs, in-page anchors, and non-http(s) URI
     * schemes (mailto/tel/javascript) which are meaningless to "click
     * through" via an HTTP redirect and would otherwise be mangled.
     */
    private function isTrackableHref(string $href): bool
    {
        if ($href === '' || str_starts_with($href, '#')) {
            return false;
        }

        foreach (['mailto:', 'tel:', 'javascript:'] as $scheme) {
            if (stripos($href, $scheme) === 0) {
                return false;
            }
        }

        return true;
    }

    /**
     * docs/21-open-click-tracking.md §21.1 — the pixel is injected into
     * every outbound message's rendered HTML at send time. Placed just
     * before the closing `</body>` when present (so it renders after the
     * visible content, per common convention and to avoid disturbing
     * layout tables placed at the very top of the document); appended to
     * the end of the markup otherwise, since composer-authored HTML is not
     * guaranteed to be a full document with a `<body>` tag.
     */
    private function injectPixel(Message $message, string $html): string
    {
        $pixelUrl = $this->urls->signedOpenUrl($message);
        $pixel = sprintf(
            '<img src="%s" width="1" height="1" alt="" style="display:block;width:1px;height:1px;border:0;outline:none;" />',
            htmlspecialchars($pixelUrl, ENT_QUOTES),
        );

        if (preg_match('/<\/body>/i', $html) === 1) {
            return preg_replace('/<\/body>/i', $pixel.'</body>', $html, 1) ?? ($html.$pixel);
        }

        return $html.$pixel;
    }
}
