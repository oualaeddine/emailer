<?php

namespace App\Modules\Templates\Sections\Http\Requests;

use App\Modules\Templates\Sections\Models\SavedSection;
use App\Modules\Templates\Services\MjmlCompiler;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

/**
 * docs/45-brevo-api-contracts.md §45.7.1 `SavedSectionInput` — only whole
 * sections can be saved (Brevo rule): the MJML must be one `<mj-section>`
 * or `<mj-wrapper>` (a dynamic-content wrapper may be preceded / followed
 * by its `mj-raw` markers).
 */
class StoreSavedSectionRequest extends FormRequest
{
    public const NOT_A_SECTION = 'Seules les sections entières peuvent être enregistrées.';

    public function authorize(): bool
    {
        return $this->user()?->can('create', SavedSection::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:1', 'max:100'],
            'mjml' => ['required', 'string', 'max:1048576', function (string $attribute, mixed $value, Closure $fail): void {
                if (! is_string($value)) {
                    return;
                }
                if (MjmlCompiler::containsInclude($value)) {
                    $fail(MjmlCompiler::INCLUDE_FORBIDDEN);

                    return;
                }
                if (! self::isWholeSection($value)) {
                    $fail(self::NOT_A_SECTION);
                }
            }],
            'component' => ['required', 'array'],
        ];
    }

    /**
     * `<mj-section>…</mj-section>` or `<mj-wrapper>…</mj-wrapper>`, optionally
     * wrapped by the dynamic-content markers of the EmailBuilder.
     */
    public static function isWholeSection(string $mjml): bool
    {
        $trimmed = trim($mjml);
        $trimmed = preg_replace('#^<mj-raw>\s*<div\s+data-dynamic-rule="[A-Za-z0-9_-]{1,64}"\s*>\s*</mj-raw>(.*)<mj-raw>\s*</div>\s*</mj-raw>$#s', '$1', $trimmed) ?? $trimmed;
        $trimmed = trim($trimmed);

        return preg_match('#^<(mj-section|mj-wrapper)\b[^>]*>.*</\1>$#s', $trimmed) === 1;
    }
}
