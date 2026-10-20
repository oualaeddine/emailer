<?php

namespace App\Modules\Webhooks\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Modules\Webhooks\Models\ApiWebhook */
class WebhookResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'url' => $this->url,
            'description' => $this->description,
            'type' => $this->type,
            'events' => $this->events,
            'is_active' => $this->is_active,
            'secret_hint' => $this->secretHint(),
            'headers' => $this->headers,
            'last_delivery_at' => $this->last_delivery_at?->toIso8601ZuluString(),
            'last_delivery_status' => $this->last_delivery_status,
            'failure_count' => $this->failure_count,
            'created_at' => $this->created_at?->toIso8601ZuluString(),
            'updated_at' => $this->updated_at?->toIso8601ZuluString(),
        ];
    }
}
