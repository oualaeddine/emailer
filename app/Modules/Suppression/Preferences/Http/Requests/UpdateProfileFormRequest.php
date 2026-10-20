<?php

namespace App\Modules\Suppression\Preferences\Http\Requests;

use App\Domain\Enums\PermissionName;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** docs/45 §45.6.6 — POST / PATCH /update-profile-forms. */
class UpdateProfileFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission(PermissionName::SettingsManage->value) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $req = $this->isMethod('POST') ? 'present' : 'sometimes';
        $name = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'name' => [$name, 'string', 'max:100'],
            'fields' => [$req, 'array', 'max:50'],
            'fields.*.attribute' => ['required', 'string', 'max:64'],
            'fields.*.label' => ['required', 'string', 'max:100'],
            'fields.*.required' => ['sometimes', 'boolean'],
            'list_ids' => [$req, 'array', 'max:100'],
            'list_ids.*' => ['uuid', Rule::exists('recipient_lists', 'uuid')],
            'content' => [$req, 'array'],
            'content.title' => ['sometimes', 'string', 'max:200'],
            'content.intro' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'content.submit_label' => ['sometimes', 'string', 'max:100'],
            'content.success_message' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'design' => ['sometimes', 'array'],
            ...DesignRules::rules(),
        ];
    }
}
