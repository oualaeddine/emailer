<?php

namespace App\Modules\Recipients\Lists\Http\Requests;

use App\Domain\Enums\PermissionName;
use Illuminate\Foundation\Http\FormRequest;

/** docs/45 §45.5.6 `ContactListInput` (store + partial update). */
class ListInputRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission(PermissionName::RecipientsManage->value) ?? false;
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['rules.prohibited' => 'Utilisez les segments pour les règles dynamiques.'];
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'name' => [$required, 'string', 'min:1', 'max:150'],
            'description' => ['sometimes', 'nullable', 'string', 'max:500'],
            'folder_id' => ['sometimes', 'nullable', 'uuid'],
            'rules' => ['prohibited'],
        ];
    }
}
