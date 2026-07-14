<?php

namespace App\Modules\Composer\Services;

use App\Modules\Recipients\Models\Recipient;

/**
 * docs/11-email-composer.md §11.4 — Merge Variables.
 * Syntax: `{{recipient.first_name}}`, `{{recipient.custom_fields.xxx}}`,
 * system variables `{{unsubscribe_url}}`/`{{current_date}}`, with fallback
 * default text `{{recipient.first_name|there}}`.
 */
class MergeVariableResolver
{
    public function resolve(string $content, Recipient $recipient, ?string $unsubscribeUrl = null): string
    {
        return (string) preg_replace_callback(
            '/\{\{\s*([a-zA-Z0-9_.]+)(?:\|([^}]*))?\s*\}\}/',
            function (array $matches) use ($recipient, $unsubscribeUrl): string {
                $path = $matches[1];
                $fallback = $matches[2] ?? '';

                $value = $this->resolvePath($path, $recipient, $unsubscribeUrl);

                return $value !== null && $value !== '' ? $value : $fallback;
            },
            $content,
        );
    }

    private function resolvePath(string $path, Recipient $recipient, ?string $unsubscribeUrl): ?string
    {
        if ($path === 'unsubscribe_url') {
            return $unsubscribeUrl;
        }

        if ($path === 'current_date') {
            return now()->translatedFormat('d/m/Y');
        }

        if (str_starts_with($path, 'recipient.custom_fields.')) {
            $key = substr($path, strlen('recipient.custom_fields.'));

            return isset($recipient->custom_fields[$key]) ? (string) $recipient->custom_fields[$key] : null;
        }

        return match ($path) {
            'recipient.first_name' => $recipient->first_name,
            'recipient.last_name' => $recipient->last_name,
            'recipient.email' => $recipient->email,
            'recipient.company_name' => $recipient->company_name,
            default => null,
        };
    }

    /**
     * @return list<string>
     */
    public function extractVariableNames(string $content): array
    {
        preg_match_all('/\{\{\s*([a-zA-Z0-9_.]+)(?:\|[^}]*)?\s*\}\}/', $content, $matches);

        return array_values(array_unique($matches[1]));
    }
}
