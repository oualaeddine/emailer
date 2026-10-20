<?php

namespace App\Modules\Templates\Http\Resources;

use App\Modules\Templates\Services\TemplateFolderService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * docs/45 §45.3 `Folder` (kind `template`).
 *
 * @mixin \App\Modules\Templates\Models\TemplateFolder
 */
class TemplateFolderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'name' => $this->name,
            'kind' => 'template',
            'items_count' => app(TemplateFolderService::class)->itemsCount($this->resource),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
