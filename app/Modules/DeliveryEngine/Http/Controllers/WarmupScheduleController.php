<?php

namespace App\Modules\DeliveryEngine\Http\Controllers;

use App\Domain\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Modules\DeliveryEngine\Http\Resources\WarmupScheduleResource;
use App\Modules\DeliveryEngine\Models\WarmupSchedule;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * docs/29-api-specification.md §29.6 — /api/v1/warmup-schedules.
 */
class WarmupScheduleController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        Gate::authorize(PermissionName::SmtpManageQuotasRotation->value);

        return WarmupScheduleResource::collection(WarmupSchedule::query()->orderBy('name')->get());
    }

    public function store(Request $request): WarmupScheduleResource
    {
        Gate::authorize(PermissionName::SmtpManageQuotasRotation->value);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'stages' => ['required', 'array', 'min:1'],
            'stages.*.day' => ['required', 'integer', 'min:1'],
            'stages.*.max_per_day' => ['required', 'integer', 'min:1'],
        ]);

        return new WarmupScheduleResource(WarmupSchedule::query()->create($validated));
    }
}
