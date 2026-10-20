<?php

namespace App\Modules\DeliveryEngine\Rendering\Stages;

use App\Modules\DeliveryEngine\Rendering\Contracts\RenderingStage;
use App\Modules\DeliveryEngine\Rendering\RenderContext;
use App\Modules\DeliveryEngine\Rendering\RenderedEmail;
use App\Modules\DeliveryEngine\Rendering\Support\Links;

/**
 * docs/45 §45.6.3 — UTM rewrite: when the campaign has `utm_enabled`, the
 * configured `utm_*` values are appended to every http(s) link that has no
 * `utm_*` parameter yet. Never applied to mailto:/tel:/anchors, nor to
 * unsubscribe / preference / mirror / tracking links. An empty
 * `utm_campaign` defaults to the campaign name (Brevo `utmCampaignValue`).
 */
class UtmRewriteStage implements RenderingStage
{
    private const KEYS = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term', 'utm_id'];

    public function phase(): string
    {
        return self::PHASE_PERSONALISE;
    }

    public function process(RenderedEmail $email, RenderContext $context): RenderedEmail
    {
        $params = $this->params($context);

        if ($params === []) {
            return $email;
        }

        return $email->withHtml(Links::mapAnchors($email->html, function (string $tag, ?string $href) use ($params): string {
            if ($href === null) {
                return $tag;
            }

            $rewritten = $this->apply($href, $params);

            return $rewritten === $href ? $tag : Links::replaceHref($tag, $rewritten);
        }));
    }

    /**
     * The href as this stage rewrites it (unchanged when no UTM applies) —
     * used by {@see LinkIdStage} to compute the link id of an authored href.
     */
    public function rewriteHref(string $href, RenderContext $context): string
    {
        return $this->apply($href, $this->params($context));
    }

    /**
     * @param  array<string, string>  $params
     */
    private function apply(string $href, array $params): string
    {
        if ($params === [] || ! Links::isHttp($href) || ! Links::isTrackable($href) || $this->hasUtm($href)) {
            return $href;
        }

        return $this->append($href, $params);
    }

    /**
     * @return array<string, string>
     */
    private function params(RenderContext $context): array
    {
        $campaign = $context->campaign;

        if ($campaign === null || ! (bool) $campaign->utm_enabled) {
            return [];
        }

        $params = [];

        foreach (self::KEYS as $key) {
            $value = trim((string) ($campaign->getAttribute($key) ?? ''));

            if ($value === '' && $key === 'utm_campaign') {
                $value = trim((string) ($campaign->name ?? ''));
            }

            if ($value !== '') {
                $params[$key] = $value;
            }
        }

        return $params;
    }

    private function hasUtm(string $href): bool
    {
        $query = (string) parse_url($href, PHP_URL_QUERY);

        return preg_match('/(?:^|&)utm_[a-z]+=/i', $query) === 1;
    }

    /**
     * @param  array<string, string>  $params
     */
    private function append(string $href, array $params): string
    {
        $fragment = '';
        $hashAt = strpos($href, '#');

        if ($hashAt !== false) {
            $fragment = substr($href, $hashAt);
            $href = substr($href, 0, $hashAt);
        }

        $separator = str_contains($href, '?') ? (str_ends_with($href, '?') || str_ends_with($href, '&') ? '' : '&') : '?';

        return $href.$separator.http_build_query($params, '', '&', PHP_QUERY_RFC3986).$fragment;
    }
}
