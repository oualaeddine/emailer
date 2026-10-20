<?php

namespace App\Modules\DeliveryEngine\Rendering\Stages;

use App\Modules\DeliveryEngine\Rendering\Contracts\RenderingStage;
use App\Modules\DeliveryEngine\Rendering\RenderContext;
use App\Modules\DeliveryEngine\Rendering\RenderedEmail;

/**
 * docs/38 §38.2 stage 4 — the draft's selected `signatures.html_content`,
 * appended before `</body>` (or at the end of a fragment) so that the
 * tracking stage also covers signature links (§38.3). Wrapped in a
 * `data-signature` block so a second pass never appends it twice.
 */
class SignatureStage implements RenderingStage
{
    public function phase(): string
    {
        return self::PHASE_PERSONALISE;
    }

    public function process(RenderedEmail $email, RenderContext $context): RenderedEmail
    {
        $signature = $context->draft?->signature;
        $content = trim((string) ($signature?->html_content ?? ''));

        if ($content === '' || preg_match('/\sdata-signature\s*=/i', $email->html) === 1) {
            return $email;
        }

        $block = '<div class="email-signature" data-signature="1">'.$content.'</div>';

        if (preg_match('/<\/body>/i', $email->html) === 1) {
            return $email->withHtml((string) preg_replace('/<\/body>/i', $block.'</body>', $email->html, 1));
        }

        return $email->withHtml($email->html.$block);
    }
}
