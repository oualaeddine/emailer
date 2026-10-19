<?php

namespace App\Modules\Recipients\Http\Requests;

use App\Domain\Enums\PermissionName;
use App\Domain\Enums\RecipientStatus;
use App\Modules\Recipients\Attributes\Services\ContactAttributeService;
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

    /**
     * docs/45 §45.5.1 — `custom_fields` are validated and cast against the contact attribute
     * definitions (422 keyed `attributes.NAME`); undefined keys pass through.
     */
    protected function passedValidation(): void
    {
        if (! is_array($this->input('custom_fields'))) {
            return;
        }

        $cast = app(ContactAttributeService::class)->castCustomFields($this->input('custom_fields'));
        $this->merge(['custom_fields' => $cast]);
        $this->validator->setData(array_merge($this->validator->getData(), ['custom_fields' => $cast]));
    }
}
