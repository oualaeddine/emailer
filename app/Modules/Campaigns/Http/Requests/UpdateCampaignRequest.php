<?php

namespace App\Modules\Campaigns\Http\Requests;

use App\Domain\Enums\PermissionName;
use App\Modules\Campaigns\Enums\CampaignSendMode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * docs/45-brevo-api-contracts.md §45.4.2 `CampaignUpdateInput` (PATCH) — every
 * key optional; legacy keys still accepted. The status gate (409) and the
 * own-vs-any check live in the service / policy.
 */
class UpdateCampaignRequest extends FormRequest
{
    use ValidatesCampaignInput;

    public function authorize(): bool
    {
        return $this->user()?->hasAnyPermission(
            PermissionName::CampaignsCreate->value,
            PermissionName::CampaignsSendAny->value,
        ) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:150'],
            'subject' => ['sometimes', 'nullable', 'string', 'max:255'],
            'template_id' => ['sometimes', 'nullable', 'string', 'exists:templates,uuid'],
            'html_body' => ['sometimes', 'nullable', 'string'],

            'recipient_list_id' => ['sometimes', 'nullable', 'string', 'exists:recipient_lists,uuid'],
            'segment_id' => ['sometimes', 'nullable', 'string', 'exists:segments,uuid'],

            'single_smtp_account_id' => ['sometimes', 'nullable', 'string', 'exists:smtp_accounts,uuid'],
            'send_mode' => ['sometimes', Rule::in([CampaignSendMode::Immediate->value, CampaignSendMode::Scheduled->value, CampaignSendMode::Recurring->value])],
            'recurrence_rule' => ['sometimes', 'nullable', 'string', 'max:255'],
            'business_hours_only' => ['sometimes', 'boolean'],
            'business_hours_start' => ['sometimes', 'nullable', 'date_format:H:i'],
            'business_hours_end' => ['sometimes', 'nullable', 'date_format:H:i'],
            'business_days' => ['sometimes', 'array'],
            'business_days.*' => ['string'],
            'approval_required' => ['sometimes', 'boolean'],

            ...$this->setupRules(),
            ...$this->audienceRules(),
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(fn (Validator $v) => $this->validateAudienceShape($v));
    }
}
