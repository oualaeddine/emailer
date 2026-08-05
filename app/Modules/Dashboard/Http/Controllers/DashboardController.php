<?php

namespace App\Modules\Dashboard\Http\Controllers;

use App\Domain\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Modules\Dashboard\Services\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * docs/09-dashboard.md — Dashboard API (WP-33).
 *
 * A single combined endpoint (docs/42-parallel-execution-plan.md §42.8
 * steers towards "prefer ONE combined endpoint" for this WP) rather than
 * one route per widget — doc 09's per-widget "independently loading card"
 * layout (§9.3) is a frontend rendering concern, not a reason to fragment
 * the request; all four widgets here are cheap aggregate queries answered
 * in one round trip.
 *
 * `dashboard.view` (checked once, for the endpoint as a whole) gates
 * *access* to the dashboard; each widget beyond that is additionally
 * gated by the existing permission that already governs its underlying
 * data elsewhere in the app (`smtp.view` for quota usage, `campaigns.view`
 * for recent campaigns) per docs/26-rbac.md's role-aware widget visibility
 * (doc 09 §9.1) — a widget whose permission the caller lacks is simply
 * omitted from the response.
 */
class DashboardController extends Controller
{
    public function __construct(private readonly DashboardService $dashboard)
    {
    }

    public function widgets(Request $request): JsonResponse
    {
        Gate::authorize(PermissionName::DashboardView->value);

        $user = $request->user();
        $days = (int) $request->integer('days', 7);
        $days = max(1, min($days, 30));

        $widgets = [
            'send_volume' => $this->dashboard->sendVolume($days),
            'deliverability' => $this->dashboard->deliverability(),
        ];

        if ($user->hasPermission(PermissionName::SmtpView->value)) {
            $widgets['quota_usage'] = $this->dashboard->quotaUsage();
        }

        if ($user->hasPermission(PermissionName::CampaignsView->value)) {
            $widgets['recent_campaigns'] = $this->dashboard->recentCampaigns();
        }

        return response()->json($widgets);
    }
}
