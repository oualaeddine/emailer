<?php

namespace App\Modules\Templates\Http\Resources;

use App\Modules\Templates\Services\TemplateService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\Templates\Models\Template
 */
class TemplateResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'name' => $this->name,
            'category' => $this->category,
            'thumbnail_path' => $this->thumbnail_path,
            'html_content' => $this->html_content,
            'is_archived' => $this->is_archived,
            'usage_count' => app(TemplateService::class)->usageCount($this->resource),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
