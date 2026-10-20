<?php

namespace App\Modules\Reporting\Http\Controllers;

use App\Domain\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Reporting\Http\Requests\CompareCampaignsRequest;
use App\Modules\Reporting\Http\Requests\ReportContactsRequest;
use App\Modules\Reporting\Http\Requests\ReportSegmentRequest;
use App\Modules\Reporting\Services\ReportQueryService;
use App\Modules\Recipients\Segments\Http\Resources\SegmentResource;
use App\Modules\Recipients\Segments\Services\SegmentService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * docs/45-brevo-api-contracts.md §45.6.5 (WP-72) — campaign report tabs, "voir les contacts", comparison.
 */
class ReportController extends Controller
{
    public function __construct(
        private readonly ReportQueryService $reports,
        private readonly SegmentService $segments,
    ) {}

    public function overview(Campaign $campaign): JsonResponse
    {
        return $this->tab($campaign, 'overview');
    }

    public function deliverability(Campaign $campaign): JsonResponse
    {
        return $this->tab($campaign, 'deliverability');
    }

    public function opens(Campaign $campaign): JsonResponse
    {
        return $this->tab($campaign, 'opens');
    }

    public function clicks(Campaign $campaign): JsonResponse
    {
        return $this->tab($campaign, 'clicks');
    }

    public function unsubscriptions(Campaign $campaign): JsonResponse
    {
        return $this->tab($campaign, 'unsubscriptions');
    }

    private function tab(Campaign $campaign, string $tab): JsonResponse
    {
        self::authorizeRead();
        $this->reports->assertReportable($campaign);

        return response()->json(['data' => $this->reports->{$tab}($campaign)]);
    }

    public function contacts(ReportContactsRequest $request, Campaign $campaign): JsonResponse
    {
        self::authorizeRead();
        Gate::authorize(PermissionName::RecipientsView->value);
        $this->reports->assertReportable($campaign);

        $page = $this->reports->contacts(
            $campaign,
            $request->string('metric')->toString(),
            $request->input('link_id'),
            $request->input('list_id'),
            $request->input('cursor'),
            $request->integer('per_page', 25),
        );

        return response()->json($page);
    }

    public function segment(ReportSegmentRequest $request, Campaign $campaign): JsonResponse
    {
        Gate::authorize(PermissionName::SegmentsManage->value);
        $this->reports->assertReportable($campaign);

        $definition = $this->reports->segmentDefinition($campaign, $request->string('metric')->toString(), $request->input('link_id'));
        $segment = $this->segments->create([
            'name' => $request->input('name') ?: $definition['default_name'],
            'description' => null,
            'folder_id' => $request->input('folder_id'),
            'rules' => $definition['rules'],
        ], $request->user());

        SegmentResource::prime([$segment]);

        return response()->json(['data' => (new SegmentResource($segment))->toArray($request)], 201);
    }

    public function compare(CompareCampaignsRequest $request): JsonResponse
    {
        self::authorizeRead();

        $ids = $request->validated('ids');
        $found = Campaign::query()->whereIn('uuid', $ids)->get()->keyBy('uuid');
        $missing = array_values(array_diff($ids, $found->keys()->all()));
        if ($missing !== []) {
            throw ValidationException::withMessages(['ids' => 'Campagne inconnue : '.implode(', ', $missing)]);
        }

        return response()->json(['data' => $this->reports->compare(collect($ids)->map(fn (string $id) => $found[$id]))]);
    }

    /**
     * `reporting.view` | `campaigns.view` (docs/45 §45.2).
     */
    public static function authorizeRead(): void
    {
        if (! Gate::allows(PermissionName::ReportingView->value) && ! Gate::allows(PermissionName::CampaignsView->value)) {
            throw new AuthorizationException;
        }
    }
}
