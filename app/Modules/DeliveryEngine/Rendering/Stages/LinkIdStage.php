<?php

namespace App\Modules\DeliveryEngine\Rendering\Stages;

use App\Modules\DeliveryEngine\Rendering\Contracts\RenderingStage;
use App\Modules\DeliveryEngine\Rendering\RenderContext;
use App\Modules\DeliveryEngine\Rendering\RenderedEmail;
use App\Modules\DeliveryEngine\Rendering\Support\Links;

/**
 * docs/43 §43.6.3 — `data-link-id` on every `<a href>` the tracking stage
 * will rewrite. Id = first 16 hex chars of sha1(authored href with the UTM
 * parameters applied), computed BEFORE merge tags (first pipeline stage):
 * a link whose href contains a merge tag keeps one stable id for every
 * recipient, so the click map and per-link counters group it as one link.
 * For a link without merge tags the id is sha1 of exactly the `url` the
 * click tracker receives. The stage runs a second time after the signature
 * stage for links that only exist from then on. Anchors that already carry
 * an id, system links and `{{ mirror }}` / `{{ unsubscribe }}` /
 * `{{ update_profile }}` hrefs are left alone.
 */
class LinkIdStage implements RenderingStage
{
    public const ATTRIBUTE = 'data-link-id';

    public function __construct(private readonly UtmRewriteStage $utm) {}

    public function phase(): string
    {
        return self::PHASE_PERSONALISE;
    }

    public function process(RenderedEmail $email, RenderContext $context): RenderedEmail
    {
        if (! $context->trackingEnabled()) {
            return $email;
        }

        return $email->withHtml(Links::mapAnchors($email->html, function (string $tag, ?string $href) use ($context): string {
            if (! Links::isTrackable($href) || Links::isSystemMergeTag((string) $href) || Links::hasAttribute($tag, self::ATTRIBUTE)) {
                return $tag;
            }

            return Links::addAttribute($tag, self::ATTRIBUTE, Links::linkId($this->utm->rewriteHref((string) $href, $context)));
        }));
    }
}
