<?php

namespace App\Modules\DeliveryEngine\Scheduling\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * docs/45-brevo-api-contracts.md §45.7.3 — `BestTimePreview`.
 *
 * @property array{contacts_with_history: int, contacts_fallback: int, fallback_hour: int, distribution: list<array{hour: int, count: int}>, window_hours: int} $resource
 */
class BestTimePreviewResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'contacts_with_history' => (int) $this->resource['contacts_with_history'],
            'contacts_fallback' => (int) $this->resource['contacts_fallback'],
            'fallback_hour' => (int) $this->resource['fallback_hour'],
            'distribution' => array_values($this->resource['distribution']),
            'window_hours' => (int) $this->resource['window_hours'],
        ];
    }
}
