<?php

namespace App\Modules\Webhooks\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Modules\Webhooks\Models\ApiWebhookDelivery */
class WebhookDeliveryResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'webhook_id' => $this->webhook->uuid,
            'event' => $this->event,
            'status' => $this->status,
            'attempts' => $this->attempts,
            'response_status' => $this->response_status,
            'response_body_excerpt' => $this->response_body_excerpt,
            'duration_ms' => $this->duration_ms,
            'payload' => $this->payload,
            'next_retry_at' => $this->next_retry_at?->toIso8601ZuluString(),
            'created_at' => $this->created_at?->toIso8601ZuluString(),
            'delivered_at' => $this->delivered_at?->toIso8601ZuluString(),
        ];
    }
}
