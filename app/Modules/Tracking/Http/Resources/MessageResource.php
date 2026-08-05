<?php

namespace App\Modules\Tracking\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\Tracking\Models\Message
 */
class MessageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'recipient_email' => $this->recipient->email,
            'subject' => $this->subject,
            'status' => $this->status,
            'smtp_account' => $this->smtpAccount?->name,
            'queued_at' => $this->queued_at?->toIso8601String(),
            'sent_at' => $this->sent_at?->toIso8601String(),
            'delivered_at' => $this->delivered_at?->toIso8601String(),
            'opened_at' => $this->opened_at?->toIso8601String(),
            'clicked_at' => $this->clicked_at?->toIso8601String(),
            'bounced_at' => $this->bounced_at?->toIso8601String(),
            'failed_at' => $this->failed_at?->toIso8601String(),
            'open_count' => $this->open_count,
            'click_count' => $this->click_count,
            'last_provider_response' => $this->last_provider_response,
        ];
    }
}
