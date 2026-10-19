<?php

namespace App\Modules\Recipients\Http\Requests;

use App\Domain\Enums\PermissionName;
use App\Domain\Enums\RecipientStatus;
use App\Modules\Recipients\Attributes\Services\ContactAttributeService;
use App\Modules\Recipients\Models\Recipient;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

/**
 * docs/29-api-specification.md §29.4 — PATCH /api/v1/recipients/{uuid}.
 */
class UpdateRecipientRequest extends FormRequest
{
    /** @var list<string> `custom_fields` keys sent as null (removed on merge). */
    private array $clearedCustomFields = [];

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
            // docs/45 §45.5.1 — NAME => value, written by ContactAttributeService (ContactAttributesUpdated).
            'attributes' => ['sometimes', 'array'],
            'ext_id' => ['sometimes', 'nullable', 'string', 'max:100', Rule::unique('recipients', 'ext_id')->ignore($this->recipientId())],
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

        $this->clearedCustomFields = array_values(array_map('strval', array_keys(
            array_filter($this->input('custom_fields'), fn ($v) => $v === null),
        )));
        $cast = app(ContactAttributeService::class)->castCustomFields($this->input('custom_fields'));
        $this->merge(['custom_fields' => $cast]);
        $this->validator->setData(array_merge($this->validator->getData(), ['custom_fields' => $cast]));
    }

    /**
     * `custom_fields` keys the client explicitly cleared (sent as null).
     *
     * @return list<string>
     */
    public function clearedCustomFields(): array
    {
        return $this->clearedCustomFields;
    }

    private function recipientId(): ?int
    {
        $recipient = $this->route('recipient');

        return $recipient instanceof Recipient ? $recipient->id : null;
    }
}
