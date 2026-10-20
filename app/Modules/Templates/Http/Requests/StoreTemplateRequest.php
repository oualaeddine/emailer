<?php

namespace App\Modules\Templates\Http\Requests;

use App\Domain\Enums\PermissionName;
use Illuminate\Foundation\Http\FormRequest;

/**
 * docs/29-api-specification.md — POST /api/v1/templates.
 */
class StoreTemplateRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:150'],
            'category' => ['nullable', 'string', 'max:50'],
            'html_content' => ['required', 'string'],
            'editor_type' => ['sometimes', 'in:dnd,html,rich_text'],
            'scope' => ['sometimes', 'in:marketing,transactional'],
            'folder_id' => ['sometimes', 'nullable', 'string', 'exists:template_folders,uuid'],
            'design_json' => ['sometimes', 'nullable', 'array'],
            'subject' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
