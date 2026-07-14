<?php

namespace App\Modules\Recipients\Http\Requests;

use App\Domain\Enums\PermissionName;
use App\Domain\Enums\RecipientSource;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

/**
 * docs/29-api-specification.md §29.4 — POST /api/v1/recipients.
 */
class StoreRecipientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission(PermissionName::RecipientsManage->value) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:255'],
            'first_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'source' => ['required', new Enum(RecipientSource::class)],
            'custom_fields' => ['sometimes', 'array'],
        ];
    }
}
