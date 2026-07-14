<?php

namespace App\Modules\Recipients\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * docs/04-database-design.md §4.1 — internal id never leaked; `uuid`
 * serialized as `id`.
 *
 * @mixin \App\Modules\Recipients\Models\Recipient
 */
class RecipientResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'email' => $this->email,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'company_name' => $this->company_name,
            'source' => $this->source,
            'status' => $this->status,
            'custom_fields' => $this->custom_fields,
            'last_contacted_at' => $this->last_contacted_at?->toIso8601String(),
            'tags' => TagResource::collection($this->whenLoaded('tags')),
            'notes' => RecipientNoteResource::collection($this->whenLoaded('notes')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
