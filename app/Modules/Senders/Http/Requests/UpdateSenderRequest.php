<?php

namespace App\Modules\Senders\Http\Requests;

use App\Domain\Enums\PermissionName;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSenderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission(PermissionName::SendersManage->value) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'reply_to' => ['sometimes', 'nullable', 'email', 'max:254'],
            'email' => ['prohibited'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['email.prohibited' => 'L\'adresse e-mail d\'un expéditeur ne peut pas être modifiée.'];
    }
}
