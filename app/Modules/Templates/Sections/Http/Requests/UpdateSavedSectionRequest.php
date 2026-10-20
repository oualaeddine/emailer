<?php

namespace App\Modules\Templates\Sections\Http\Requests;

use App\Modules\Templates\Sections\Models\SavedSection;
use Illuminate\Foundation\Http\FormRequest;

/** docs/45-brevo-api-contracts.md §45.7.1 — `PATCH /saved-sections/{id}` `{ "name": "…" }`. */
class UpdateSavedSectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $section = $this->route('section');

        return $section instanceof SavedSection && ($this->user()?->can('update', $section) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:1', 'max:100'],
        ];
    }
}
