<?php

namespace App\Modules\Templates\Http\Requests;

use App\Domain\Enums\PermissionName;
use Illuminate\Foundation\Http\FormRequest;

/**
 * docs/45 §45.4.4 — POST /templates/{id}/duplicate.
 */
class DuplicateTemplateRequest extends FormRequest
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
            'name' => ['sometimes', 'nullable', 'string', 'max:150'],
            'folder_id' => ['sometimes', 'nullable', 'string'],
            'scope' => ['sometimes', 'nullable', 'in:marketing,transactional'],
        ];
    }
}
