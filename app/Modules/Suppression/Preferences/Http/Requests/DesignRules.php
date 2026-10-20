<?php

namespace App\Modules\Suppression\Preferences\Http\Requests;

/** docs/45 §45.6.6 — shared `PageDesign` validation. */
final class DesignRules
{
    /** @return array<string, mixed> */
    public static function rules(): array
    {
        $hex = ['sometimes', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'];

        return [
            'design.logo_url' => ['sometimes', 'nullable', 'string', 'max:500', 'regex:#^(https?://|/)[^\s"\'<>]+$#'],
            'design.primary_color' => $hex,
            'design.background_color' => $hex,
            'design.text_color' => $hex,
            'design.button_text_color' => $hex,
            'design.font_family' => ['sometimes', 'string', 'max:120', 'regex:/^[A-Za-z0-9 ,\'"_-]+$/'],
            'design.border_radius' => ['sometimes', 'integer', 'between:0,32'],
        ];
    }
}
