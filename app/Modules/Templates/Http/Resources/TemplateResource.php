<?php

namespace App\Modules\Templates\Http\Resources;

use App\Modules\Templates\Services\TemplateService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * docs/45 §45.4.4 — `TemplateV2`. Legacy keys unchanged; `html_content` is
 * null in v2 (paginated) list responses via {@see TemplateListResource}.
 *
 * @mixin \App\Modules\Templates\Models\Template
 */
class TemplateResource extends JsonResource
{
    protected bool $omitHtml = false;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $creator = $this->relationLoaded('createdBy') ? $this->createdBy : $this->createdBy()->first();

        return [
            'id' => $this->uuid,
            'name' => $this->name,
            'category' => $this->category,
            'thumbnail_path' => $this->thumbnail_path,
            'html_content' => $this->omitHtml ? null : $this->html_content,
            'is_archived' => $this->is_archived,
            'usage_count' => app(TemplateService::class)->usageCount($this->resource),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'editor_type' => $this->editor_type ?? 'html',
            'scope' => $this->scope ?? 'marketing',
            'folder_id' => $this->folder_id !== null ? ($this->relationLoaded('folder') ? $this->folder?->uuid : $this->folder()->value('uuid')) : null,
            'is_gallery' => (bool) $this->is_gallery,
            'gallery_category' => $this->gallery_category,
            'thumbnail_url' => $this->thumbnail_path ? Storage::disk('public')->url($this->thumbnail_path) : null,
            'subject' => $this->subject,
            'created_by' => $creator !== null ? ['id' => $creator->uuid, 'name' => $creator->name] : null,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
