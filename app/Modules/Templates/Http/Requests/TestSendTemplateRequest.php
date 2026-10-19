<?php

namespace App\Modules\Templates\Http\Requests;

use App\Domain\Enums\PermissionName;
use Illuminate\Foundation\Http\FormRequest;

/**
 * docs/45 §45.4.4 — POST /templates/{id}/test-send (1–5 addresses).
 */
class TestSendTemplateRequest extends FormRequest
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
            'emails' => ['required', 'array', 'min:1', 'max:5'],
            'emails.*' => ['required', 'email:rfc', 'max:191'],
            'contact_id' => ['sometimes', 'nullable', 'uuid'],
            'subject' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'emails.min' => 'Indiquez au moins une adresse e-mail.',
            'emails.max' => 'Vous pouvez envoyer un test à 5 adresses au maximum.',
        ];
    }
}
