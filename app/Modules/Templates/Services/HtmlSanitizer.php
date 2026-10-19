<?php

namespace App\Modules\Templates\Services;

/**
 * Strips active content from imported HTML (docs/45 §45.4.4: scripts are
 * removed and reported as a warning).
 */
class HtmlSanitizer
{
    public const SCRIPTS_WARNING = 'Les scripts ont été supprimés.';

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

        $title = null;
        if (preg_match('#<title[^>]*>(.*?)</title>#is', $stripped, $m) === 1) {
            $candidate = trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            $title = $candidate !== '' ? $candidate : null;
        }

        return ['html' => $stripped, 'title' => $title, 'warnings' => $warnings];
    }
}
