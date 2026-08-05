<?php

namespace App\Modules\Campaigns\Http\Requests;

use App\Domain\Enums\PermissionName;
use App\Modules\Campaigns\Enums\CampaignSendMode;
use App\Modules\Campaigns\Enums\CampaignSmtpStrategy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

/**
 * docs/15-campaign-management.md §15.8 — "Content" tab: edits only allowed
 * while `draft` (the service layer, not this request, enforces the status
 * gate). This request only checks the base `campaigns.create` permission
 * (mirrors `SaveDraftRequest` checking the base `composer.compose`
 * permission) — the precise own-vs-any `CampaignPolicy::update` check runs
 * in the controller via `$this->authorize()`, exactly the
 * `DraftController::update()` pattern.
 */
class UpdateCampaignRequest extends FormRequest
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
            'name' => ['sometimes', 'string', 'max:150'],
            'template_id' => ['sometimes', 'nullable', 'string', 'exists:templates,uuid'],
            'subject' => ['sometimes', 'nullable', 'string', 'max:998'],
            'html_body' => ['sometimes', 'nullable', 'string'],

            'recipient_list_id' => ['sometimes', 'nullable', 'prohibits:segment_id', 'string', 'exists:recipient_lists,uuid'],
            'segment_id' => ['sometimes', 'nullable', 'prohibits:recipient_list_id', 'string', 'exists:segments,uuid'],

            'smtp_strategy' => ['sometimes', new Enum(CampaignSmtpStrategy::class)],
            'single_smtp_account_id' => ['sometimes', 'nullable', 'string', 'exists:smtp_accounts,uuid'],

            'send_mode' => ['sometimes', new Enum(CampaignSendMode::class)],
            'recurrence_rule' => ['sometimes', 'nullable', 'string', 'max:255'],
            'business_hours_only' => ['sometimes', 'boolean'],
            'business_hours_start' => ['sometimes', 'nullable', 'date_format:H:i'],
            'business_hours_end' => ['sometimes', 'nullable', 'date_format:H:i'],
            'business_days' => ['sometimes', 'array'],
            'business_days.*' => ['string'],

            'approval_required' => ['sometimes', 'boolean'],
            'tracking_enabled' => ['sometimes', 'boolean'],
        ];
    }
}
