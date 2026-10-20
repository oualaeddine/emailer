<?php

namespace App\Modules\DeliveryEngine\Rendering\Stages;

use App\Modules\DeliveryEngine\Rendering\Contracts\RenderingStage;
use App\Modules\DeliveryEngine\Rendering\RenderContext;
use App\Modules\DeliveryEngine\Rendering\RenderedEmail;
use App\Modules\DeliveryEngine\Rendering\Support\Links;

/**
 * docs/43 §43.6.3 — `data-link-id` on every `<a href>` the tracking stage
 * will rewrite, injected BEFORE the click-URL rewrite. Id = first 16 hex
 * chars of sha1(original absolute URL, i.e. the exact `url` the click
 * tracker receives). Anchors that already carry one are left alone.
 */
class LinkIdStage implements RenderingStage
{
    public const ATTRIBUTE = 'data-link-id';

    public function phase(): string
    {
        return self::PHASE_PERSONALISE;
    }

    public function process(RenderedEmail $email, RenderContext $context): RenderedEmail
    {
        if (! $context->trackingEnabled()) {
            return $email;
        }

        return $email->withHtml(Links::mapAnchors($email->html, function (string $tag, ?string $href): string {
            if (! Links::isTrackable($href) || Links::hasAttribute($tag, self::ATTRIBUTE)) {
                return $tag;
            }

            return Links::addAttribute($tag, self::ATTRIBUTE, Links::linkId((string) $href));
        }));
    }
}
