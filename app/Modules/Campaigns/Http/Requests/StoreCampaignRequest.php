<?php

namespace App\Modules\Campaigns\Http\Requests;

use App\Domain\Enums\PermissionName;
use App\Modules\Campaigns\Enums\CampaignSendMode;
use App\Modules\Campaigns\Enums\CampaignType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Validator;

/**
 * docs/45-brevo-api-contracts.md §45.4.2 `CampaignCreateInput` — Brevo asks
 * only for the internal name (+ type, folder). The one-list-or-one-segment
 * rule of docs/15 §15.2 is gone (docs/43 §43.4.2); legacy keys
 * `template_id` / `recipient_list_id` / `segment_id` (and the legacy wizard
 * fields) are still accepted, the targets being converted into `include`
 * audience rows (§45.10). Every `CampaignUpdateInput` key is accepted too.
 */
class StoreCampaignRequest extends FormRequest
{
    use ValidatesCampaignInput;

    public function authorize(): bool
    {
        return $this->user()?->hasPermission(PermissionName::CampaignsCreate->value) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'type' => ['sometimes', new Enum(CampaignType::class)],
            'template_id' => ['nullable', 'string', 'exists:templates,uuid'],
            'subject' => ['nullable', 'string', 'max:998'],
            'html_body' => ['nullable', 'string'],

            'recipient_list_id' => ['nullable', 'string', 'exists:recipient_lists,uuid'],
            'segment_id' => ['nullable', 'string', 'exists:segments,uuid'],

            'single_smtp_account_id' => ['nullable', 'string', 'exists:smtp_accounts,uuid'],
            'send_mode' => ['sometimes', Rule::in([CampaignSendMode::Immediate->value, CampaignSendMode::Scheduled->value, CampaignSendMode::Recurring->value])],
            'recurrence_rule' => ['nullable', 'string', 'max:255'],
            'business_hours_only' => ['sometimes', 'boolean'],
            'business_hours_start' => ['nullable', 'date_format:H:i'],
            'business_hours_end' => ['nullable', 'date_format:H:i'],
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
