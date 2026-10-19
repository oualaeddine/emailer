<?php

namespace App\Modules\Campaigns\Http\Requests;

use App\Modules\Campaigns\Enums\CampaignEditorType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

/**
 * docs/45-brevo-api-contracts.md §45.4.2 `CampaignDesignInput`.
 * Authorization (`update` policy) runs in the controller.
 */
class CampaignDesignRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'editor_type' => ['required', new Enum(CampaignEditorType::class)],
            'html_body' => ['present', 'nullable', 'string'],
            'design_json' => ['sometimes', 'nullable', 'array'],
        ];
    }
}
