<?php

namespace App\Modules\Campaigns\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * docs/15-campaign-management.md §15.8 — Campaign Detail Tabs (Overview +
 * Content fields; Recipients/Analytics are served by their own endpoints).
 *
 * @mixin \App\Modules\Campaigns\Models\Campaign
 */
class CampaignResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'name' => $this->name,
            'status' => $this->status,

            'template_id' => $this->template?->uuid,
            'subject' => $this->subject,
            'html_body' => $this->html_body,

            'recipient_list_id' => $this->recipientList?->uuid,
            'segment_id' => $this->segment?->uuid,

            'smtp_strategy' => $this->smtp_strategy,
            'single_smtp_account_id' => $this->singleSmtpAccount?->uuid,

            'send_mode' => $this->send_mode,
            'scheduled_at' => $this->scheduled_at?->toIso8601String(),
            'recurrence_rule' => $this->recurrence_rule,
            'business_hours_only' => $this->business_hours_only,
            'business_hours_start' => $this->business_hours_start,
            'business_hours_end' => $this->business_hours_end,
            'business_days' => $this->business_days,

            'approval_required' => $this->approval_required,
            'approved_by' => $this->approvedBy?->uuid,
            'approved_at' => $this->approved_at?->toIso8601String(),

            'tracking_enabled' => $this->tracking_enabled,
            'cloned_from_campaign_id' => $this->clonedFrom?->uuid,

            'created_by' => $this->createdBy?->uuid,
            'sent_at' => $this->sent_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
