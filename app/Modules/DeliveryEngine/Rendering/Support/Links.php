<?php

namespace App\Modules\DeliveryEngine\Rendering\Support;

use App\Modules\Tracking\Services\TrackingRenderer;

/**
 * Anchor helpers shared by the UTM, link-id, tracking and mirror stages.
 *
 * The href normalisation (`trim(html_entity_decode(..., ENT_QUOTES))`) is
 * byte-for-byte the one {@see TrackingRenderer}
 * applies before building the click URL, so `data-link-id` is computed on
 * exactly the URL the click tracker (WP-70) receives as `url`.
 */
final class Links
{
    private const ANCHOR = '/<a\b[^>]*>/i';

    private const HREF = '/(?<![\w-])href\s*=\s*(["\'])(.*?)\1/is';

    /**
     * docs/43 §43.6.3 click-map contract: first 16 hex chars of
     * sha1(original absolute URL).
     */
    public static function linkId(string $url): string
    {
        return substr(sha1($url), 0, 16);
    }

    public static function normalise(string $rawHref): string
    {
        return trim(html_entity_decode($rawHref, ENT_QUOTES));
    }

    /**
     * Calls `$callback(string $tag, ?string $href)` for every opening `<a>`
     * tag (href decoded, null when absent) and splices in its return value.
     *
     * @param  callable(string, ?string): string  $callback
     */
    public static function mapAnchors(string $html, callable $callback): string
    {
        return preg_replace_callback(
            self::ANCHOR,
            function (array $m) use ($callback): string {
                $href = preg_match(self::HREF, $m[0], $h) === 1 ? self::normalise($h[2]) : null;

                return $callback($m[0], $href);
            },
            $html,
        ) ?? $html;
    }

    /** Replaces the href value of one `<a>` tag (value HTML-escaped). */
    public static function replaceHref(string $tag, string $href): string
    {
        return preg_replace_callback(
            self::HREF,
            fn (array $m): string => 'href='.$m[1].htmlspecialchars($href, ENT_QUOTES).$m[1],
            $tag,
            1,
        ) ?? $tag;
    }

    public static function hasAttribute(string $tag, string $attribute): bool
    {
        return preg_match('/\s'.preg_quote($attribute, '/').'\s*=/i', $tag) === 1;
    }

    public static function addAttribute(string $tag, string $attribute, string $value): string
    {
        return (string) preg_replace('/^<a\b/i', '<a '.$attribute.'="'.htmlspecialchars($value, ENT_QUOTES).'"', $tag, 1);
    }

    public static function isHttp(string $href): bool
    {
        return preg_match('#^https?://#i', $href) === 1;
    }

    /**
     * Same predicate as TrackingRenderer::isTrackableHref(), minus the
     * system links (unsubscribe, preferences, mirror) and links that are
     * already click-tracking URLs — those are never wrapped, UTM-tagged or
     * given a `data-link-id`.
     */
    public static function isTrackable(?string $href): bool
    {
        if ($href === null || $href === '' || str_starts_with($href, '#')) {
            return false;
        }

        foreach (['mailto:', 'tel:', 'javascript:'] as $scheme) {
            if (stripos($href, $scheme) === 0) {
                return false;
            }
        }

        return ! self::isSystemLink($href) && ! self::isTrackingUrl($href);
    }

    public static function isSystemLink(string $href): bool
    {
        foreach (['unsubscribe', 'preferences', 'm'] as $prefix) {
            if (str_starts_with($href, url($prefix).'/')) {
                return true;
            }
        }

        return false;
    }

    public static function isTrackingUrl(string $href): bool
    {
        return str_starts_with($href, url('t/c').'/') || str_starts_with($href, url('t/o').'/');
    }
}
