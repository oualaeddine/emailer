<?php

namespace App\Modules\Campaigns\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * docs/45-brevo-api-contracts.md §45.4.2 `CampaignDesignCopyInput` (template picker).
 */
class CampaignDesignCopyRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $type = $this->input('source_type');

        return [
            'source_type' => ['required', Rule::in(['template', 'gallery', 'campaign', 'blank'])],
            'source_id' => [
                $type === 'blank' ? 'nullable' : 'required',
                'string',
                ...match ($type) {
                    'template', 'gallery' => ['exists:templates,uuid'],
                    'campaign' => ['exists:campaigns,uuid'],
                    default => [],
                },
            ],
        ];
    }
}
