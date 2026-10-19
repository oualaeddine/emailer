<?php

namespace App\Modules\Templates\Services;

/**
 * Strips active content from imported HTML (docs/45 §45.4.4: scripts are
 * removed and reported as a warning).
 */
class HtmlSanitizer
{
    public const SCRIPTS_WARNING = 'Les scripts ont été supprimés.';
    public const ACTIVE_CONTENT_WARNING = 'Le contenu actif (gestionnaires d\'événements, liens javascript:, iframes, formulaires) a été supprimé.';

    /**
     * @return array{html: string, title: string|null, warnings: list<string>}
     */
    public function clean(string $html): array
    {
        $warnings = [];

        $stripped = preg_replace('#<script\b[^>]*>.*?</script\s*>#is', '', $html) ?? $html;
        $stripped = preg_replace('#</?script\b[^>]*>#i', '', $stripped) ?? $stripped;
        if ($stripped !== $html) {
            $warnings[] = self::SCRIPTS_WARNING;
        }

        $active = $this->stripActiveContent($stripped);
        if ($active !== $stripped) {
            $warnings[] = self::ACTIVE_CONTENT_WARNING;
            $stripped = $active;
        }

        $title = null;
        if (preg_match('#<title[^>]*>(.*?)</title>#is', $stripped, $m) === 1) {
            $candidate = trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            $title = $candidate !== '' ? $candidate : null;
        }

        return ['html' => $stripped, 'title' => $title, 'warnings' => $warnings];
    }

    private function stripActiveContent(string $html): string
    {
        // Embedding / form / base elements (paired, then any stray tag).
        $html = preg_replace('#<(iframe|object|embed|form)\b[^>]*>.*?</\1\s*>#is', '', $html) ?? $html;
        $html = preg_replace('#</?(?:iframe|object|embed|base|form)\b[^>]*>#i', '', $html) ?? $html;
        // Meta refresh / http-equiv (plain <meta charset> and viewport are kept).
        $html = preg_replace('#<meta\b[^>]*\bhttp-equiv\b[^>]*>#i', '', $html) ?? $html;

        // Inline on* event handlers, whatever the quoting or separator.
        do {
            $before = $html;
            $html = preg_replace('#(<[a-z][^>]*?)[\s/]+on[a-z]+\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]*)#i', '$1', $html) ?? $html;
        } while ($html !== $before);

        // javascript: / vbscript: / data:text/html in URL-bearing attributes (entity & whitespace obfuscation decoded).
        return preg_replace_callback(
            '#\b(href|src|action|formaction|xlink:href|background)\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>]+))#i',
            function (array $m): string {
                $value = $m[2] ?? '' ?: ($m[3] ?? '' ?: ($m[4] ?? ''));
                $normalised = preg_replace('/[\x00-\x20]+/', '', html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '';

                return preg_match('#^(?:javascript|vbscript|data:text/html)#i', $normalised) === 1
                    ? strtolower($m[1]).'="#"'
                    : $m[0];
            },
            $html,
        ) ?? $html;
    }
}
