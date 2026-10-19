<?php

namespace App\Modules\Campaigns\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * docs/45-brevo-api-contracts.md §45.4.2 duplicate / send-to-new-contacts body.
 */
class DuplicateCampaignRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'nullable', 'string', 'max:150'],
            'folder_id' => ['sometimes', 'nullable', 'string', 'exists:campaign_folders,uuid'],
        ];
    }
}
