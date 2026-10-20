<?php

namespace App\Modules\DeliveryEngine\Rendering\Support;

use App\Modules\DeliveryEngine\Rendering\Stages\LinkIdStage;
use App\Modules\DeliveryEngine\Rendering\Stages\TrackingStage;

/**
 * Turns stored message HTML into the "voir dans le navigateur" version:
 * no open pixel, no click-tracking redirects (unwrapped back to their
 * original `url`), no `data-link-id` attributes.
 */
final class MirrorHtml
{
    public static function clean(string $html): string
    {
        $html = TrackingStage::stripPixels($html);

        $html = Links::mapAnchors($html, function (string $tag, ?string $href): string {
            if ($href !== null && str_starts_with($href, url('t/c').'/')) {
                parse_str((string) parse_url($href, PHP_URL_QUERY), $query);
                $original = is_string($query['url'] ?? null) ? $query['url'] : '#';
                $tag = Links::replaceHref($tag, $original);
            }

            return (string) preg_replace('/\s'.LinkIdStage::ATTRIBUTE.'\s*=\s*(["\']).*?\1/i', '', $tag);
        });

        return $html;
    }
}
