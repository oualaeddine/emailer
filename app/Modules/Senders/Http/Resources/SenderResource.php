<?php

namespace App\Modules\Senders\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Modules\Senders\Models\Sender */
class SenderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'name' => $this->name,
            'email' => $this->email,
            'domain' => \App\Modules\Senders\Models\Sender::domainOf($this->email),
            'domain_id' => $this->domain?->uuid,
            'domain_status' => $this->domain?->status,
            'is_default' => $this->is_default,
            'is_verified' => $this->verified_at !== null,
            'verification_method' => $this->verification_method,
            'verified_at' => $this->verified_at?->toIso8601ZuluString(),
            'verification_sent_at' => $this->verification_sent_at?->toIso8601ZuluString(),
            'reply_to' => $this->reply_to,
            'created_at' => $this->created_at?->toIso8601ZuluString(),
            'updated_at' => $this->updated_at?->toIso8601ZuluString(),
        ];
    }
}
