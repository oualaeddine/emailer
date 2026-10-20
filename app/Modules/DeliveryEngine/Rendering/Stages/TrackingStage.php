<?php

namespace App\Modules\DeliveryEngine\Rendering\Stages;

use App\Modules\DeliveryEngine\Rendering\Contracts\RenderingStage;
use App\Modules\DeliveryEngine\Rendering\RenderContext;
use App\Modules\DeliveryEngine\Rendering\RenderedEmail;
use App\Modules\DeliveryEngine\Rendering\Support\Links;
use App\Modules\Tracking\Services\TrackingRenderer;

/**
 * docs/38 §38.2 stage 5 — open pixel + click rewrite, delegated to the
 * Tracking module's {@see TrackingRenderer} (used read-only). Skipped when
 * the campaign disables tracking (`tracking_enabled = false`) and in
 * previews.
 *
 * Idempotency / exclusions, done around the delegate: any existing open
 * pixel is removed first (the renderer always adds exactly one), and
 * anchors the renderer would otherwise wrap but must not — already tracked
 * click URLs and system links (unsubscribe, preferences, mirror) — are
 * parked behind `#…` placeholders (which the renderer skips) and restored
 * afterwards.
 */
class TrackingStage implements RenderingStage
{
    private const PLACEHOLDER = '#__rendering_keep_%d__';

    public function __construct(private readonly TrackingRenderer $renderer) {}

    public function phase(): string
    {
        return self::PHASE_DELIVERY;
    }

    public function process(RenderedEmail $email, RenderContext $context): RenderedEmail
    {
        if (! $context->trackingEnabled() || $context->message === null) {
            return $email;
        }

        $html = self::stripPixels($email->html);

        /** @var array<string, string> $parked */
        $parked = [];

        $html = Links::mapAnchors($html, function (string $tag, ?string $href) use (&$parked): string {
            if ($href === null || Links::isTrackable($href) || ! $this->rendererWouldTrack($href)) {
                return $tag;
            }

            $placeholder = sprintf(self::PLACEHOLDER, count($parked));
            $parked[$placeholder] = $href;

            return Links::replaceHref($tag, $placeholder);
        });

        $html = $this->renderer->render($context->message, $html);

        foreach ($parked as $placeholder => $href) {
            $html = str_replace(
                'href="'.$placeholder.'"',
                'href="'.htmlspecialchars($href, ENT_QUOTES).'"',
                str_replace("href='".$placeholder."'", "href='".htmlspecialchars($href, ENT_QUOTES)."'", $html),
            );
        }

        return $email->withHtml($html);
    }

    /** Removes every open-tracking pixel `<img>` (any message). */
    public static function stripPixels(string $html): string
    {
        $prefix = preg_quote(url('t/o').'/', '/');

        return (string) preg_replace('/\s*<img\b[^>]*(?<![\w-])src\s*=\s*(["\'])'.$prefix.'[^"\']*\1[^>]*>/i', '', $html);
    }

    /** Mirror of TrackingRenderer::isTrackableHref() (private there). */
    private function rendererWouldTrack(string $href): bool
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
}
