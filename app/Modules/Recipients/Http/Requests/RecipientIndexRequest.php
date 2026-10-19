<?php

namespace App\Modules\Recipients\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * docs/45 §45.5.4 — ContactIndexQuery (GET query string or POST /recipients/search body).
 */
class RecipientIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $merge = [];

        if (is_string($this->input('filters'))) {
            $decoded = json_decode($this->input('filters'), true);
            $merge['filters'] = is_array($decoded) ? $decoded : 'invalid';
        }

        if (is_string($this->input('columns'))) {
            $merge['columns'] = array_values(array_filter(explode(',', $this->input('columns')), fn ($c) => $c !== ''));
        }

        $this->merge($merge);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'pagination' => ['nullable', 'in:cursor,page'],
            'cursor' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'q' => ['nullable', 'string', 'max:255'],
            'scope' => ['nullable', 'in:all,mine'],
            'list_id' => ['nullable', 'string', 'max:64'],
            'segment_id' => ['nullable', 'string', 'max:64'],
            'view_id' => ['nullable', 'string', 'max:64'],
            'filters' => ['nullable', 'array'],
            'columns' => ['nullable', 'array', 'max:60'],
            'columns.*' => ['string', 'max:80'],
            'sort' => ['nullable', 'string', 'max:100'],
            'source' => ['nullable', 'string', 'max:30'],
            'status' => ['nullable', 'string', 'max:20'],
            'tag_id' => ['nullable', 'integer'],
        ];
    }
}
