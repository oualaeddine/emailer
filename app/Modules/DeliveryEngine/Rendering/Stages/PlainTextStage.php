<?php

namespace App\Modules\DeliveryEngine\Rendering\Stages;

use App\Modules\DeliveryEngine\Rendering\Contracts\RenderingStage;
use App\Modules\DeliveryEngine\Rendering\RenderContext;
use App\Modules\DeliveryEngine\Rendering\RenderedEmail;
use App\Modules\DeliveryEngine\Rendering\Support\SystemUrls;

/**
 * docs/38 §38.2 stage 7 / §38.4 — plain-text alternative derived from the
 * final HTML: tags stripped, link URLs kept visible (`Texte [https://…]`),
 * paragraph / list structure preserved, and the unsubscribe URL appended
 * when the body does not already show one. Deterministic, so rendering
 * twice yields the same text.
 */
class PlainTextStage implements RenderingStage
{
    public function __construct(private readonly SystemUrls $urls) {}

    public function phase(): string
    {
        return self::PHASE_DELIVERY;
    }

    public function process(RenderedEmail $email, RenderContext $context): RenderedEmail
    {
        $text = self::fromHtml($email->html);

        if (! $context->preview && ! str_contains($text, url('unsubscribe').'/')) {
            $unsubscribe = $this->urls->unsubscribe($context);

            if ($unsubscribe !== '' && $unsubscribe !== '#') {
                $text = rtrim($text."\n\n".'Se désinscrire : '.$unsubscribe);
            }
        }

        return $email->withText($text);
    }

    public static function fromHtml(string $html): string
    {
        $html = (string) preg_replace('#<!--.*?-->#s', '', $html);
        $html = (string) preg_replace('#<(head|style|script|title)\b[^>]*>.*?</\1\s*>#is', '', $html);
        $html = (string) preg_replace('#<img\b[^>]*>#i', '', $html);
        $html = (string) preg_replace('/\s+/u', ' ', $html);

        $html = (string) preg_replace_callback('#<a\b([^>]*)>(.*?)</a\s*>#is', function (array $m): string {
            $label = trim(self::decode(strip_tags($m[2])));
            $href = preg_match('/(?<![\w-])href\s*=\s*(["\'])(.*?)\1/is', $m[1], $h) === 1
                ? trim(self::decode($h[2]))
                : '';

            if ($href === '' || str_starts_with($href, '#') || stripos($href, 'javascript:') === 0) {
                return htmlspecialchars($label, ENT_QUOTES);
            }

            $shown = stripos($href, 'mailto:') === 0 ? substr($href, 7) : $href;

            if ($label === '' || $label === $shown || $label === $href) {
                return ' '.htmlspecialchars($shown, ENT_QUOTES).' ';
            }

            // Re-escaped so the final strip_tags()/decode pass restores it verbatim.
            return htmlspecialchars($label.' ['.$shown.']', ENT_QUOTES);
        }, $html);

        $html = (string) preg_replace('#<br\s*/?>#i', "\n", $html);
        $html = (string) preg_replace('#<hr\b[^>]*>#i', "\n----------\n", $html);
        $html = (string) preg_replace('#<li\b[^>]*>#i', "\n- ", $html);
        $html = (string) preg_replace('#</(td|th)\s*>#i', ' ', $html);
        $html = (string) preg_replace('#</?(p|div|h[1-6]|table|tr|ul|ol|blockquote|section|article|header|footer|center)\b[^>]*>#i', "\n\n", $html);

        $text = self::decode(strip_tags($html));
        $text = str_replace("\u{00A0}", ' ', $text);

        $lines = array_map(fn (string $line): string => trim((string) preg_replace('/[ \t]+/', ' ', $line)), explode("\n", $text));
        $text = (string) preg_replace("/\n{3,}/", "\n\n", implode("\n", $lines));

        return trim($text);
    }

    private static function decode(string $value): string
    {
        return html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
