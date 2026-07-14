<?php

namespace App\Modules\Recipients\Http\Requests;

use App\Domain\Enums\PermissionName;
use App\Domain\Enums\RecipientStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

/**
 * docs/29-api-specification.md §29.4 — PATCH /api/v1/recipients/{uuid}.
 */
class UpdateRecipientRequest extends FormRequest
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
            'first_name' => ['sometimes', 'nullable', 'string', 'max:100'],
            'last_name' => ['sometimes', 'nullable', 'string', 'max:100'],
            'company_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'status' => ['sometimes', new Enum(RecipientStatus::class)],
            'custom_fields' => ['sometimes', 'array'],
            'tag_ids' => ['sometimes', 'array'],
            'tag_ids.*' => ['integer', 'exists:tags,id'],
        ];
    }
}
