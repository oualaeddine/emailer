<?php

namespace App\Modules\Campaigns\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Campaigns\Http\Requests\ScheduleCampaignRequest;
use App\Modules\Campaigns\Http\Requests\StoreCampaignRequest;
use App\Modules\Campaigns\Http\Requests\UpdateCampaignRequest;
use App\Modules\Campaigns\Http\Resources\CampaignResource;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Campaigns\Services\CampaignService;
use App\Modules\DeliveryEngine\Models\SmtpAccount;
use App\Modules\Recipients\Models\RecipientList;
use App\Modules\Recipients\Models\Segment;
use App\Modules\Templates\Models\Template;
use App\Modules\Tracking\Http\Resources\MessageResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;

/**
 * docs/15-campaign-management.md — Campaigns API.
 */
class CampaignController extends Controller
{
    public function __construct(private readonly CampaignService $campaigns)
    {
    }

    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Campaign::class);

        return CampaignResource::collection(
            Campaign::query()->with(['template', 'recipientList', 'segment'])->orderByDesc('created_at')->paginate(25),
        );
    }

    public function store(StoreCampaignRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['template_id'] = $this->resolveUuid(Template::class, $data['template_id'] ?? null);
        $data['recipient_list_id'] = $this->resolveUuid(RecipientList::class, $data['recipient_list_id'] ?? null);
        $data['segment_id'] = $this->resolveUuid(Segment::class, $data['segment_id'] ?? null);
        $data['single_smtp_account_id'] = $this->resolveUuid(
            SmtpAccount::class,
            $data['single_smtp_account_id'] ?? null,
        );

        $campaign = $this->campaigns->create($data, $request->user());

        return (new CampaignResource($campaign))->response()->setStatusCode(201);
    }

    /**
     * §15.8 — "Overview"/"Content" tabs.
     */
    public function show(Campaign $campaign): CampaignResource
    {
        $this->authorize('view', $campaign);

        return new CampaignResource($campaign->load(['template', 'recipientList', 'segment', 'createdBy', 'approvedBy', 'clonedFrom', 'singleSmtpAccount']));
    }

    public function update(UpdateCampaignRequest $request, Campaign $campaign): CampaignResource
    {
        $this->authorize('update', $campaign);

        $data = $request->validated();

        if (array_key_exists('template_id', $data)) {
            $data['template_id'] = $this->resolveUuid(Template::class, $data['template_id']);
        }
        if (array_key_exists('recipient_list_id', $data)) {
            $data['recipient_list_id'] = $this->resolveUuid(RecipientList::class, $data['recipient_list_id']);
        }
        if (array_key_exists('segment_id', $data)) {
            $data['segment_id'] = $this->resolveUuid(Segment::class, $data['segment_id']);
        }
        if (array_key_exists('single_smtp_account_id', $data)) {
            $data['single_smtp_account_id'] = $this->resolveUuid(SmtpAccount::class, $data['single_smtp_account_id']);
        }

        $updated = $this->campaigns->update($campaign, $data);

        return new CampaignResource($updated);
    }

    public function schedule(ScheduleCampaignRequest $request, Campaign $campaign): CampaignResource
    {
        $this->authorize('send', $campaign);

        $updated = $this->campaigns->schedule($campaign, Carbon::parse($request->validated('scheduled_at')));

        return new CampaignResource($updated);
    }

    public function send(Campaign $campaign): CampaignResource
    {
        $this->authorize('send', $campaign);

        $updated = $this->campaigns->sendNow($campaign);

        return new CampaignResource($updated);
    }

    public function pause(Campaign $campaign): CampaignResource
    {
        $this->authorize('cancelPause', $campaign);

        return new CampaignResource($this->campaigns->pause($campaign));
    }

    public function resume(Campaign $campaign): CampaignResource
    {
        $this->authorize('cancelPause', $campaign);

        return new CampaignResource($this->campaigns->resume($campaign));
    }

    public function cancel(Campaign $campaign): CampaignResource
    {
        $this->authorize('cancelPause', $campaign);

        return new CampaignResource($this->campaigns->cancel($campaign));
    }

    public function approve(Campaign $campaign): CampaignResource
    {
        $this->authorize('approve', $campaign);

        return new CampaignResource($this->campaigns->approve($campaign, request()->user()));
    }

    public function clone(Campaign $campaign): JsonResponse
    {
        $this->authorize('clonable', $campaign);

        $cloned = $this->campaigns->clone($campaign, request()->user());

        return (new CampaignResource($cloned))->response()->setStatusCode(201);
    }

    /**
     * §15.8 — "Recipients" tab: `messages` grid for this campaign.
     */
    public function recipients(Campaign $campaign): AnonymousResourceCollection
    {
        $this->authorize('view', $campaign);

        return MessageResource::collection(
            $campaign->messages()->with(['recipient', 'smtpAccount'])->orderByDesc('id')->paginate(50),
        );
    }

    /**
     * §15.8 — "Analytics" tab: delivery funnel / bounce breakdown, see
     * docs/25-reporting.md.
     */
    public function analytics(Campaign $campaign): JsonResponse
    {
        $this->authorize('view', $campaign);

        $counts = $campaign->messages()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return response()->json([
            'targeted' => $this->campaigns->resolveTargetRecipients($campaign)->count(),
            'by_status' => $counts,
            'open_count' => (int) $campaign->messages()->sum('open_count'),
            'click_count' => (int) $campaign->messages()->sum('click_count'),
        ]);
    }

    /**
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $model
     */
    private function resolveUuid(string $model, ?string $uuid): ?int
    {
        if ($uuid === null) {
            return null;
        }

        return $model::query()->where('uuid', $uuid)->value('id');
    }
}
