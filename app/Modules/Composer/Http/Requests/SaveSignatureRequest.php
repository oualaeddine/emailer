<?php

namespace App\Modules\Composer\Http\Requests;

use App\Domain\Enums\PermissionName;
use Illuminate\Foundation\Http\FormRequest;

/**
 * docs/29-api-specification.md §29.3 — POST/PATCH /api/v1/signatures.
 */
class SaveSignatureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission(PermissionName::ComposerCompose->value) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'html_content' => ['required', 'string'],
            'is_default' => ['sometimes', 'boolean'],
        ];
    }
}
