<?php

namespace App\Modules\Campaigns\AbTesting\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * docs/45-brevo-api-contracts.md §45.7.2 `AbTestPickWinnerInput` `{ variant_id: uuid }`.
 */
class PickAbWinnerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // policy checked in the controller
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'variant_id' => ['required', 'uuid'],
        ];
    }
}
