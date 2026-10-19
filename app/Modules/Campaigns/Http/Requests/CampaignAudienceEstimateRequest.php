<?php

namespace App\Modules\Campaigns\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * docs/45-brevo-api-contracts.md §45.4.2 — `{ "audience"?: CampaignAudienceInput }`.
 */
class CampaignAudienceEstimateRequest extends FormRequest
{
    use ValidatesCampaignInput;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->audienceRules();
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(fn (Validator $v) => $this->validateAudienceShape($v));
    }
}
