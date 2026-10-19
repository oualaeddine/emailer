<?php

namespace App\Modules\Senders\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Modules\Senders\Models\SendingDomain */
class SendingDomainResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'domain' => $this->domain,
            'status' => $this->status,
            'is_authenticated' => $this->isAuthenticated(),
            'dkim_selector' => $this->dkim_selector,
            'has_private_key' => $this->dkim_private_key !== null,
            'records' => $this->records ?? [],
            'senders_count' => $this->senders_count ?? $this->senders()->count(),
            'last_checked_at' => $this->last_checked_at?->toIso8601ZuluString(),
            'created_at' => $this->created_at?->toIso8601ZuluString(),
        ];
    }
}
