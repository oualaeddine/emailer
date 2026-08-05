<?php

namespace App\Modules\Suppression\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * docs/04-database-design.md §4.1 — internal id never leaked; `uuid`
 * serialized as `id` (mirrors `RecipientResource`).
 *
 * @mixin \App\Modules\Suppression\Models\SuppressionEntry
 */
class SuppressionEntryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'email' => $this->email,
            'reason' => $this->reason,
            'source_message_id' => $this->source_message_id,
            'notes' => $this->notes,
            'added_by' => $this->whenLoaded('addedBy', fn () => $this->addedBy?->name),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
