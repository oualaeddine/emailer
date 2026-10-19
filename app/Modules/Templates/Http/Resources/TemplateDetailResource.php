<?php

namespace App\Modules\Templates\Http\Resources;

use Illuminate\Http\Request;

/**
 * docs/45 §45.4.4 — `TemplateV2Detail`.
 *
 * @mixin \App\Modules\Templates\Models\Template
 */
class TemplateDetailResource extends TemplateResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'html_content' => $this->html_content,
            'design_json' => $this->design_json,
            'versions_count' => $this->versions()->count(),
        ];
    }
}
