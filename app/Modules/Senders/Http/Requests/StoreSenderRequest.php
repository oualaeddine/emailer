<?php

namespace App\Modules\Senders\Http\Requests;

use App\Domain\Enums\PermissionName;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSenderRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:254', Rule::unique('senders', 'email')->whereNull('deleted_at')],
            'reply_to' => ['nullable', 'email', 'max:254'],
            'is_default' => ['sometimes', 'boolean'],
        ];
    }
}
