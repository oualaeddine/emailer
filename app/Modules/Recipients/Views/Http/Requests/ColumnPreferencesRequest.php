<?php

namespace App\Modules\Recipients\Views\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ColumnPreferencesRequest extends FormRequest
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
            'page' => ['required', 'in:contacts'],
            'columns' => ['required', 'array', 'max:60'],
        ];
    }
}
