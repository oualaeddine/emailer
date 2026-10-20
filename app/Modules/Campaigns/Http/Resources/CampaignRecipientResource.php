<?php

namespace App\Modules\Campaigns\Http\Resources;

use App\Modules\Campaigns\Models\CampaignRecipient;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * `GET /campaigns/{id}/recipients` row (docs/45 §45.4.2: rows of
 * `campaign_recipients`). Keeps the legacy `MessageResource` keys (filled
 * from the linked message, null while the row is pending/skipped) so the
 * Recipients tab keeps working, plus the row's own state.
 *
 * @mixin CampaignRecipient
 */
class CampaignRecipientResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $message = $this->message;

        return [
            'id' => $message?->uuid,
            'recipient_id' => $this->recipient?->uuid,
            'recipient_email' => $this->recipient?->email,
            'recipient_status' => $this->status,
            'skip_reason' => $this->skip_reason,
            'scheduled_for' => $this->scheduled_for?->toIso8601String(),
            'subject' => $message?->subject,
            'status' => $message?->status ?? $this->status,
            'smtp_account' => $message?->smtpAccount?->name,
            'queued_at' => $message?->queued_at?->toIso8601String(),
            'sent_at' => $message?->sent_at?->toIso8601String(),
            'delivered_at' => $message?->delivered_at?->toIso8601String(),
            'opened_at' => $message?->opened_at?->toIso8601String(),
            'clicked_at' => $message?->clicked_at?->toIso8601String(),
            'bounced_at' => $message?->bounced_at?->toIso8601String(),
            'failed_at' => $message?->failed_at?->toIso8601String(),
            'open_count' => (int) ($message?->open_count ?? 0),
            'click_count' => (int) ($message?->click_count ?? 0),
            'last_provider_response' => $message?->last_provider_response,
        ];
    }
}
