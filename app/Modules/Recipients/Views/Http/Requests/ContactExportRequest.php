<?php

namespace App\Modules\Recipients\Views\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ContactExportRequest extends FormRequest
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
            'columns' => ['sometimes', 'array', 'max:60'],
            'columns.*' => ['string', 'max:80'],
            'format' => ['sometimes', 'in:csv,xlsx'],
        ] + ContactSelectionRules::rules();
    }

    public function after(): array
    {
        return ContactSelectionRules::after($this);
    }
}
