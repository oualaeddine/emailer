<?php

namespace App\Modules\Recipients\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * docs/45-brevo-api-contracts.md §45.5.5 — TimelineEntry. Wraps the array
 * produced by {@see \App\Modules\Recipients\Services\RecipientTimelineService}.
 *
 * @property array<string, mixed> $resource
 */
class RecipientTimelineEntryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return $this->resource;
    }
}
