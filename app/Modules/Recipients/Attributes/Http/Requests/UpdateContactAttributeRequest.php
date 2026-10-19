<?php

namespace App\Modules\Recipients\Attributes\Http\Requests;

use App\Modules\Recipients\Attributes\Policies\ContactAttributePolicy;
use Illuminate\Foundation\Http\FormRequest;

/** docs/45 §45.5.1 — PATCH /contact-attributes/{id}; name and type are immutable. */
class UpdateContactAttributeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && app(ContactAttributePolicy::class)->manage($this->user());
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['prohibited'],
            'type' => ['prohibited'],
            'label' => ['sometimes', 'nullable', 'string', 'max:100'],
            'options' => ['sometimes', 'array', 'min:1', 'max:500'],
            'options.*.value' => ['required', 'string', 'max:100', 'distinct'],
            'options.*.label' => ['required', 'string', 'max:100'],
        ];
    }
}
