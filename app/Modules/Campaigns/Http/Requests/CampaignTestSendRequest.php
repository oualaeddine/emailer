<?php

namespace App\Modules\Campaigns\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * docs/45-brevo-api-contracts.md §45.4.2 `CampaignTestSendInput` (1–5 emails).
 */
class CampaignTestSendRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'emails' => ['required', 'array', 'min:1', 'max:5'],
            'emails.*' => ['required', 'email', 'max:254'],
            'contact_id' => ['sometimes', 'nullable', 'string', 'exists:recipients,uuid'],
            'variant' => ['sometimes', 'nullable', 'string', 'max:10'],
        ];
    }
}
