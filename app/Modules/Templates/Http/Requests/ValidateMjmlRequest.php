<?php

namespace App\Modules\Templates\Http\Requests;

use App\Domain\Enums\PermissionName;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

/**
 * docs/45-brevo-api-contracts.md §45.6.1 — POST /api/v1/templates/mjml/validate.
 * `templates.manage` | `campaigns.create`; `mjml` ≤ 1 MB. `html` (optional) is
 * the browser-compiled HTML, used only when the server compiler is
 * unavailable and `editor.trust_client_html` is on.
 */
class ValidateMjmlRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && (
            $user->hasPermission(PermissionName::TemplatesManage->value)
            || $user->hasPermission(PermissionName::CampaignsCreate->value)
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $maxBytes = (int) config('editor.max_mjml_bytes', 1024 * 1024);

        return [
            'mjml' => [
                'required',
                'string',
                function (string $attribute, mixed $value, Closure $fail) use ($maxBytes): void {
                    if (is_string($value) && strlen($value) > $maxBytes) {
                        $fail('Le code MJML ne doit pas dépasser 1 Mo.');
                    }
                },
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (is_string($value) && preg_match('/^\s*<mjml[\s>]/i', $value) !== 1) {
                        $fail('Le code MJML doit commencer par la balise <mjml>.');
                    }
                },
            ],
            'html' => ['sometimes', 'nullable', 'string', 'max:4194304'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'mjml.required' => 'Le code MJML est obligatoire.',
            'mjml.string' => 'Le code MJML doit être une chaîne de caractères.',
        ];
    }
}
