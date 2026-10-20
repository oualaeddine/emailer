<?php

namespace App\Modules\Recipients\Attributes\Http\Requests;

use App\Modules\Recipients\Attributes\Models\ContactAttribute;
use App\Modules\Recipients\Attributes\Policies\ContactAttributePolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** docs/45 §45.5.1 — POST /contact-attributes. */
class StoreContactAttributeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && app(ContactAttributePolicy::class)->manage($this->user());
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('name'))) {
            $this->merge(['name' => strtoupper(trim($this->input('name')))]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $isChoice = in_array($this->input('type'), ['category', 'multiple_choice'], true);

        return [
            'name' => ['required', 'string', 'max:50', 'regex:/^[A-Z][A-Z0-9_]*$/', Rule::unique('contact_attributes', 'name')],
            'label' => ['nullable', 'string', 'max:100'],
            'type' => ['required', Rule::in(ContactAttribute::TYPES)],
            'options' => [$isChoice ? 'required' : Rule::prohibitedIf(true), 'array', 'min:'.($isChoice ? 1 : 0), 'max:500'],
            'options.*.value' => ['required', 'string', 'max:100', 'distinct'],
            'options.*.label' => ['required', 'string', 'max:100'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.regex' => 'Le nom ne peut contenir que des lettres majuscules, chiffres et _ et doit commencer par une lettre.',
            'name.unique' => 'Un attribut portant ce nom existe déjà.',
            'name.max' => 'Le nom ne doit pas dépasser 50 caractères.',
        ];
    }
}
