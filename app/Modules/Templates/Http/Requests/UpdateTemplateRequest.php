<?php

namespace App\Modules\Templates\Http\Requests;

use App\Domain\Enums\PermissionName;
use Illuminate\Foundation\Http\FormRequest;

/**
 * docs/29-api-specification.md — PATCH /api/v1/templates/{uuid}.
 * All fields optional — partial update (docs/12-template-system.md §12.5).
 */
class UpdateTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission(PermissionName::TemplatesManage->value) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:150'],
            'category' => ['sometimes', 'nullable', 'string', 'max:50'],
            'html_content' => ['sometimes', 'string'],
            'change_note' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
