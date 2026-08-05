<?php

namespace App\Modules\Campaigns\Http\Requests;

use App\Domain\Enums\PermissionName;
use App\Modules\Campaigns\Enums\CampaignSendMode;
use App\Modules\Campaigns\Enums\CampaignSmtpStrategy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

/**
 * docs/15-campaign-management.md §15.2 — Campaign Wizard (Basics/Content/
 * Recipients/Sending Configuration steps collapsed into one create call).
 * A campaign targets exactly one of a Recipient List or a Segment — never
 * both, never neither.
 */
class StoreCampaignRequest extends FormRequest
{
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
            'template_id' => ['nullable', 'string', 'exists:templates,uuid'],
            'subject' => ['nullable', 'string', 'max:998'],
            'html_body' => ['nullable', 'string'],

            'recipient_list_id' => ['required_without:segment_id', 'prohibits:segment_id', 'nullable', 'string', 'exists:recipient_lists,uuid'],
            'segment_id' => ['required_without:recipient_list_id', 'nullable', 'string', 'exists:segments,uuid'],

            'smtp_strategy' => ['sometimes', new Enum(CampaignSmtpStrategy::class)],
            'single_smtp_account_id' => ['nullable', 'string', 'exists:smtp_accounts,uuid'],

            'send_mode' => ['sometimes', new Enum(CampaignSendMode::class)],
            'recurrence_rule' => ['nullable', 'string', 'max:255'],
            'business_hours_only' => ['sometimes', 'boolean'],
            'business_hours_start' => ['nullable', 'date_format:H:i'],
            'business_hours_end' => ['nullable', 'date_format:H:i'],
            'business_days' => ['sometimes', 'array'],
            'business_days.*' => ['string'],

            'approval_required' => ['sometimes', 'boolean'],
            'tracking_enabled' => ['sometimes', 'boolean'],
        ];
    }
}
