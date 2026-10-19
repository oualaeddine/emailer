<?php

namespace App\Modules\Recipients\Views\Http\Requests;

use App\Modules\Recipients\Views\Services\ContactBulkService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ContactBulkRequest extends FormRequest
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
            'action' => ['required', Rule::in(ContactBulkService::ACTIONS)],
            'params' => ['sometimes', 'array'],
            'params.list_id' => ['nullable', 'string', 'max:64'],
            'params.attribute' => ['nullable', 'string', 'max:50'],
            'params.reason' => ['nullable', 'string', 'max:30'],
        ] + ContactSelectionRules::rules();
    }

    public function after(): array
    {
        return ContactSelectionRules::after($this);
    }
}
