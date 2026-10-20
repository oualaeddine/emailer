<?php

namespace App\Modules\Templates\Http\Resources;

use App\Modules\Templates\Services\CompiledMjml;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * docs/45-brevo-api-contracts.md §45.6.1 — `MjmlValidationResult`.
 *
 * @property-read CompiledMjml $resource
 */
class MjmlValidationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'valid' => $this->resource->isValid(),
            'html' => $this->resource->html,
            'errors' => $this->resource->errors,
            'size_bytes' => $this->resource->sizeBytes(),
        ];
    }
}
