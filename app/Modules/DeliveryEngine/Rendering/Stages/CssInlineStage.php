<?php

namespace App\Modules\DeliveryEngine\Rendering\Stages;

use App\Modules\DeliveryEngine\Rendering\Contracts\RenderingStage;
use App\Modules\DeliveryEngine\Rendering\RenderContext;
use App\Modules\DeliveryEngine\Rendering\RenderedEmail;
use Throwable;
use TijsVerkoyen\CssToInlineStyles\Css\Processor;
use TijsVerkoyen\CssToInlineStyles\CssToInlineStyles;

/**
 * docs/38 §38.2 stage 6 — `<style>` rules inlined into `style=""` after the
 * tracking stage, so they also apply to tracking-touched elements (§38.3).
 * Only runs when the HTML carries `<style>` rules (MJML / builder output is
 * already inlined), so plain HTML is never re-serialised. The `<style>`
 * blocks are kept for clients that honour them (media queries).
 */
class CssInlineStage implements RenderingStage
{
    public function phase(): string
    {
        return self::PHASE_DELIVERY;
    }

    public function process(RenderedEmail $email, RenderContext $context): RenderedEmail
    {
        if (stripos($email->html, '<style') === false) {
            return $email;
        }

        $processor = new Processor;

        if ($processor->getRules($processor->getCssFromStyleTags($email->html)) === []) {
            return $email;
        }

        try {
            return $email->withHtml((new CssToInlineStyles)->convert($email->html));
        } catch (Throwable) {
            return $email;
        }
    }
}
