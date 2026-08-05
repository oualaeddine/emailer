<?php

namespace App\Modules\Queues\Http\Controllers;

use App\Domain\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Modules\Queues\Http\Resources\QueueStatsResource;
use App\Modules\Queues\Services\QueueStatsService;
use Illuminate\Support\Facades\Gate;

/**
 * docs/29-api-specification.md — GET /api/v1/queues/metrics, `queues.view`.
 * docs/24-queue-management.md §24.5/§24.6/§24.7 — in-app Queue Monitoring
 * snapshot, restricted to Administrator and Marketing Manager
 * (read-only, for campaign ETA visibility without full infra access).
 */
class QueueStatsController extends Controller
{
    public function __construct(private readonly QueueStatsService $stats)
    {
    }

    public function index(): QueueStatsResource
    {
        Gate::authorize(PermissionName::QueuesView->value);

        return new QueueStatsResource($this->stats->snapshot());
    }
}
