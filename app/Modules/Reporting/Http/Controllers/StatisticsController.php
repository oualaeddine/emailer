<?php

namespace App\Modules\Reporting\Http\Controllers;

use App\Domain\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Modules\Reporting\Http\Requests\StatisticsQueryRequest;
use App\Modules\Reporting\Services\ReportQueryService;
use App\Modules\Reporting\Services\StatisticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;

/**
 * docs/45 §45.6.5 — global statistics (WP-72; page: WP-73).
 */
class StatisticsController extends Controller
{
    public function __construct(
        private readonly StatisticsService $statistics,
        private readonly ReportQueryService $reports,
    ) {}

    public function summary(StatisticsQueryRequest $request): JsonResponse
    {
        Gate::authorize(PermissionName::ReportingView->value);

        return response()->json(['data' => $this->statistics->summary($request->validated())]);
    }

    public function timeseries(StatisticsQueryRequest $request): JsonResponse
    {
        Gate::authorize(PermissionName::ReportingView->value);

        return response()->json(['data' => $this->statistics->timeseries($request->validated())]);
    }

    public function campaigns(StatisticsQueryRequest $request): mixed
    {
        Gate::authorize(PermissionName::ReportingView->value);

        return JsonResource::collection($this->statistics->campaigns($request->validated(), $request->integer('per_page', 25)));
    }

    public function lists(StatisticsQueryRequest $request): JsonResponse
    {
        Gate::authorize(PermissionName::ReportingView->value);

        return response()->json(['data' => $this->statistics->lists($request->validated())]);
    }

    public function averages(Request $request): JsonResponse
    {
        ReportController::authorizeRead();
        $days = $request->validate(['days' => ['nullable', 'integer', 'min:1', 'max:365']])['days'] ?? 90;

        return response()->json(['data' => $this->reports->averages((int) $days)]);
    }
}
