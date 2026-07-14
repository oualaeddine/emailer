<?php

namespace App\Modules\Recipients\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\Recipients\Models\Segment
 */
class SegmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'rules' => $this->rules,
            'last_evaluated_at' => $this->last_evaluated_at?->toIso8601String(),
            'cached_count' => $this->cached_count,
        ];
    }
}
