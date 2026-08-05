<?php

namespace App\Modules\Queues\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * docs/29-api-specification.md — GET /api/v1/queues/metrics.
 * docs/24-queue-management.md §24.6 — Advanced Queue Monitoring Dashboard.
 *
 * Wraps the plain array already shaped by
 * `App\Modules\Queues\Services\QueueStatsService::snapshot()` — kept as a
 * Resource (rather than returning the array directly) purely so the
 * response shape is versioned the same way as every other module's API
 * output, and so `horizon_available` is explicit at the response's edge
 * for API consumers deciding whether to render Horizon-only widgets.
 *
 * @mixin array{horizon_available: bool, queues: list<array{name: string, size: int}>, failed_jobs: array{count: int, recent: array}}
 */
class QueueStatsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'horizon_available' => $this->resource['horizon_available'],
            'queues' => $this->resource['queues'],
            'failed_jobs' => $this->resource['failed_jobs'],
        ];
    }
}
