<?php

namespace App\Modules\DeliveryEngine\Rendering\Stages;

use App\Modules\DeliveryEngine\Rendering\Contracts\RenderingStage;
use App\Modules\DeliveryEngine\Rendering\RenderContext;
use App\Modules\DeliveryEngine\Rendering\RenderedEmail;

/**
 * Campaign emails must always carry an opt-out link. When the authored
 * design has none, a small standard footer with `{{ unsubscribe_url }}` is
 * appended before `</body>` (or at the end of a fragment). Runs before the
 * merge-tag stage so the tag resolves to the recipient's own link. Wrapped
 * in a `data-unsubscribe-footer` block so a second pass never adds it twice.
 */
class UnsubscribeFooterStage implements RenderingStage
{
    public const FOOTER = '<div data-unsubscribe-footer="1" style="margin-top:24px;padding:16px;text-align:center;'
        .'font-family:Arial,Helvetica,sans-serif;font-size:12px;line-height:18px;color:#6b6b6b;">'
        .'Vous ne souhaitez plus recevoir nos e-mails ? '
        .'<a href="{{ unsubscribe_url }}" style="color:#6b6b6b;text-decoration:underline;">Se désinscrire</a>'
        .'</div>';

    public function phase(): string
    {
        return self::PHASE_PERSONALISE;
    }

    /**
     * True when the HTML already holds an unsubscribe merge tag or an
     * unsubscribe URL (also used by the pre-send checklist).
     */
    public static function hasUnsubscribeLink(string $html): bool
    {
        return preg_match('/\{\{\s*(unsubscribe|unsubscribe_url)\s*\}\}/i', $html) === 1
            || preg_match('#href\s*=\s*["\'][^"\']*/unsubscribe/#i', $html) === 1;
    }

    public function process(RenderedEmail $email, RenderContext $context): RenderedEmail
    {
        if ($context->campaign === null
            || trim($email->html) === ''
            || self::hasUnsubscribeLink($email->html)
            || preg_match('/\sdata-unsubscribe-footer\s*=/i', $email->html) === 1) {
            return $email;
        }

        if (preg_match('/<\/body>/i', $email->html) === 1) {
            return $email->withHtml((string) preg_replace('/<\/body>/i', self::FOOTER.'</body>', $email->html, 1));
        }

        return $email->withHtml($email->html.self::FOOTER);
    }
}
