<?php

namespace App\Modules\Recipients\Views\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ContactViewRequest extends FormRequest
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
        $req = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'name' => [$req, 'string', 'min:1', 'max:100'],
            'is_shared' => ['sometimes', 'boolean'],
            'filters' => ['sometimes', 'nullable', 'array'],
            'list_id' => ['sometimes', 'nullable', 'string', 'max:64'],
            'segment_id' => ['sometimes', 'nullable', 'string', 'max:64'],
            'columns' => ['sometimes', 'array', 'max:60'],
            'sort' => ['sometimes', 'nullable', 'string', 'max:100'],
        ];
    }
}
