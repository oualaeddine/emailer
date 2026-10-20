<?php

namespace App\Modules\Campaigns\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Campaigns\Http\Resources\CampaignChecklistResource;
use App\Modules\Campaigns\Jobs\CheckCampaignLinksJob;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Campaigns\Services\Checklist\CampaignChecklistContract;
use App\Modules\Campaigns\Services\Checklist\ChecklistItem;
use App\Modules\Campaigns\Services\Checklist\LinkChecker;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

/**
 * docs/45-brevo-api-contracts.md §45.4.5 — WP-45 pre-send checklist.
 */
class CampaignChecklistController extends Controller
{
    public function __construct(private readonly CampaignChecklistContract $checklist)
    {
    }

    public function show(Campaign $campaign): CampaignChecklistResource
    {
        $this->authorize('view', $campaign);

        return new CampaignChecklistResource($this->checklist->evaluate($campaign));
    }

    public function checkLinks(Campaign $campaign): JsonResponse
    {
        $this->authorize('create', Campaign::class);

        Cache::forget(LinkChecker::cacheKey($campaign->uuid, $campaign->html_body));
        CheckCampaignLinksJob::dispatch($campaign->id);

        $checklist = $this->checklist->evaluate($campaign)->withItem(new ChecklistItem(
            'links_valid',
            ChecklistItem::PENDING,
            false,
            'Vérification des liens en cours',
            ['broken' => []],
        ));

        return (new CampaignChecklistResource($checklist))->response()->setStatusCode(202);
    }
}
