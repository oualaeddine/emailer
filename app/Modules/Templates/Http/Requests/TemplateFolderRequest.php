<?php

namespace App\Modules\Templates\Http\Requests;

use App\Domain\Enums\PermissionName;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** docs/45 §45.3 `FolderInput`. */
class TemplateFolderRequest extends FormRequest
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
            'name' => [
                'required', 'string', 'min:1', 'max:100',
                Rule::unique('template_folders', 'name')->ignore($this->route('id'), 'uuid'),
            ],
        ];
    }
}
