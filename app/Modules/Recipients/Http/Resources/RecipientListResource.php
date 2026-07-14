<?php

namespace App\Modules\Recipients\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\Recipients\Models\RecipientList
 */
class RecipientListResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'name' => $this->name,
            'description' => $this->description,
            'type' => $this->type,
            'segment' => new SegmentResource($this->whenLoaded('segment')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
