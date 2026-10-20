<?php

namespace App\Modules\Templates\Sections\Http\Resources;

use App\Modules\Templates\Sections\Models\SavedSection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * docs/45-brevo-api-contracts.md §45.7.1 `SavedSection`.
 *
 * @mixin SavedSection
 */
class SavedSectionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $creator = $this->relationLoaded('createdBy') ? $this->createdBy : null;

        return [
            'id' => $this->uuid,
            'name' => $this->name,
            'mjml' => $this->mjml,
            'component' => (object) ($this->component ?? []),
            'thumbnail_url' => $this->thumbnail_path !== null ? Storage::disk('public')->url($this->thumbnail_path) : null,
            'created_by' => $creator !== null ? ['id' => $creator->uuid, 'name' => $creator->name] : null,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
