<?php

namespace App\Modules\Reporting\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * `GET /campaigns/compare?ids=uuid,uuid` (2–5 campaigns).
 */
class CompareCampaignsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:2', 'max:5'],
            'ids.*' => ['required', 'uuid', 'distinct'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $ids = $this->input('ids');
        if (is_string($ids)) {
            $this->merge(['ids' => array_values(array_filter(array_map('trim', explode(',', $ids)), fn (string $s): bool => $s !== ''))]);
        }
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'ids.min' => 'Sélectionnez au moins 2 campagnes à comparer.',
            'ids.max' => 'Vous pouvez comparer 5 campagnes au maximum.',
            'ids.required' => 'Sélectionnez au moins 2 campagnes à comparer.',
        ];
    }
}
