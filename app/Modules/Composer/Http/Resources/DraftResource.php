<?php

namespace App\Modules\Composer\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\Composer\Models\Draft
 */
class DraftResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'subject' => $this->subject,
            'html_body' => $this->html_body,
            'status' => $this->status,
            'signature_id' => $this->signature?->uuid,
            'updated_at' => $this->updated_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
