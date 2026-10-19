<?php

namespace App\Modules\Campaigns\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Campaigns\Contracts\AudienceResolver;
use App\Modules\Campaigns\Enums\CampaignStatus;
use App\Modules\Campaigns\Http\Requests\CampaignAudienceEstimateRequest;
use App\Modules\Campaigns\Http\Requests\CampaignBulkRequest;
use App\Modules\Campaigns\Http\Requests\CampaignDesignCopyRequest;
use App\Modules\Campaigns\Http\Requests\CampaignDesignRequest;
use App\Modules\Campaigns\Http\Requests\CampaignIndexRequest;
use App\Modules\Campaigns\Http\Requests\CampaignTestSendRequest;
use App\Modules\Campaigns\Http\Requests\DuplicateCampaignRequest;
use App\Modules\Campaigns\Http\Requests\ScheduleCampaignRequest;
use App\Modules\Campaigns\Http\Requests\StoreCampaignRequest;
use App\Modules\Campaigns\Http\Requests\UpdateCampaignRequest;
use App\Modules\Campaigns\Http\Resources\CampaignDetailResource;
use App\Modules\Campaigns\Http\Resources\CampaignResource;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Campaigns\Models\CampaignFolder;
use App\Modules\Campaigns\Services\CampaignService;
use App\Modules\Campaigns\Support\ExternalReferences;
use App\Modules\Composer\Services\MergeVariableResolver;
use App\Modules\Recipients\Models\Recipient;
use App\Modules\Tracking\Http\Resources\MessageResource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Throwable;

/**
 * docs/15-campaign-management.md — Campaigns API, extended by
 * docs/45-brevo-api-contracts.md §45.4.2 (Campaigns v2).
 */
class CampaignController extends Controller
{
    public const TEST_SENDS_PER_HOUR = 10;

    private const LIST_RELATIONS = ['template', 'recipientList', 'segment', 'folder', 'createdBy', 'approvedBy', 'clonedFrom', 'parentCampaign', 'singleSmtpAccount'];

    public function __construct(private readonly CampaignService $campaigns) {}

    /**
     * `GET /campaigns` — `CampaignListQuery` → `PaginatedResponse<CampaignV2>` + `meta.status_counts`.
     */
    public function index(CampaignIndexRequest $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Campaign::class);

        $filters = $request->validated();

        $query = $this->filteredQuery($filters);
        $this->applyStatusTab($query, $filters['status_tab'] ?? 'all');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $sort = $filters['sort'] ?? '-created_at';
        $query->orderBy(ltrim($sort, '-'), str_starts_with($sort, '-') ? 'desc' : 'asc')->orderByDesc('id');

        $page = $query->with(self::LIST_RELATIONS)->paginate((int) ($filters['per_page'] ?? 25));

        return CampaignResource::collection($page)->additional([
            'meta' => ['status_counts' => $this->statusCounts($filters)],
        ]);
    }

    /**
     * `POST /campaigns` — `CampaignCreateInput` (+ legacy keys, §45.10).
     */
    public function store(StoreCampaignRequest $request): JsonResponse
    {
        $campaign = $this->campaigns->create($request->toCampaignAttributes(), $request->user());

        return (new CampaignDetailResource($campaign->load(self::LIST_RELATIONS)))->response()->setStatusCode(201);
    }

    public function show(Campaign $campaign): CampaignDetailResource
    {
        $this->authorize('view', $campaign);

        return new CampaignDetailResource($campaign->load(self::LIST_RELATIONS));
    }

    /**
     * `PATCH /campaigns/{id}` — `CampaignUpdateInput`; 409 unless draft/scheduled/suspended.
     */
    public function update(UpdateCampaignRequest $request, Campaign $campaign): CampaignDetailResource
    {
        $this->authorize('update', $campaign);

        return $this->detail($this->campaigns->update($campaign, $request->toCampaignAttributes()));
    }

    public function destroy(Campaign $campaign): JsonResponse
    {
        $this->authorize('delete', $campaign);

        $this->campaigns->delete($campaign);

        return response()->json(null, 204);
    }

    public function saveDesign(CampaignDesignRequest $request, Campaign $campaign): CampaignDetailResource
    {
        $this->authorize('update', $campaign);

        $data = $request->validated();

        return $this->detail($this->campaigns->saveDesign(
            $campaign,
            $data['editor_type'],
            $data['html_body'] ?? null,
            $data['design_json'] ?? null,
        ));
    }

    public function copyDesign(CampaignDesignCopyRequest $request, Campaign $campaign): CampaignDetailResource
    {
        $this->authorize('update', $campaign);

        return $this->detail($this->campaigns->copyDesign(
            $campaign,
            $request->validated('source_type'),
            $request->validated('source_id'),
        ));
    }

    /**
     * `GET /campaigns/{id}/preview` — `CampaignPreview`.
     */
    public function preview(Request $request, Campaign $campaign, MergeVariableResolver $merge): JsonResponse
    {
        $this->authorize('view', $campaign);

        $contact = $request->filled('contact_id')
            ? Recipient::query()->where('uuid', $request->query('contact_id'))->firstOrFail()
            : null;

        $render = fn (?string $content): ?string => $content === null || $contact === null
            ? $content
            : $merge->resolve($content, $contact);

        $sender = app(ExternalReferences::class)->sender($campaign->sender_id);
        $toName = $contact !== null && filled($campaign->to_field)
            ? trim($merge->resolve((string) $campaign->to_field, $contact))
            : ($contact !== null ? trim($contact->first_name.' '.$contact->last_name) : null);

        return response()->json(['data' => [
            'subject' => $render($campaign->subject),
            'preview_text' => $render($campaign->preview_text),
            'from' => ['name' => $sender['name'] ?? null, 'email' => $sender['email'] ?? null],
            'to' => $contact === null ? null : ($toName !== '' && $toName !== null ? "{$toName} <{$contact->email}>" : $contact->email),
            'html' => $render($campaign->html_body),
            'contact_id' => $contact?->uuid,
        ]]);
    }

    /**
     * `POST /campaigns/{id}/audience/estimate` — `AudienceEstimate`.
     */
    public function estimateAudience(CampaignAudienceEstimateRequest $request, Campaign $campaign, AudienceResolver $resolver): JsonResponse
    {
        $this->authorize('prepare', $campaign);

        $audience = $request->has('audience') ? (array) $request->validated('audience') : null;

        return response()->json(['data' => $resolver->estimate($campaign, $audience)->toArray()]);
    }

    /**
     * `POST /campaigns/{id}/test-send` — 202 `TestSendResult`; 10/h per campaign.
     */
    public function testSend(CampaignTestSendRequest $request, Campaign $campaign): JsonResponse
    {
        $this->authorize('prepare', $campaign);

        $key = "campaign-test-send:{$campaign->id}";
        if (RateLimiter::tooManyAttempts($key, self::TEST_SENDS_PER_HOUR)) {
            throw new ThrottleRequestsException('Limite de 10 envois de test par heure atteinte pour cette campagne.');
        }
        RateLimiter::hit($key, 3600);

        $contact = $request->filled('contact_id')
            ? Recipient::query()->where('uuid', $request->validated('contact_id'))->first()
            : null;

        $result = $this->campaigns->testSend($campaign, $request->validated('emails'), $contact, $request->user());

        return response()->json(['data' => $result], 202);
    }

    /**
     * `POST /campaigns/{id}/schedule` — `CampaignScheduleInput`.
     */
    public function schedule(ScheduleCampaignRequest $request, Campaign $campaign): CampaignDetailResource
    {
        $this->authorize('send', $campaign);

        return $this->detail($this->campaigns->scheduleWithOptions($campaign, $request->options()));
    }

    public function unschedule(Campaign $campaign): CampaignDetailResource
    {
        $this->authorize('send', $campaign);

        return $this->detail($this->campaigns->unschedule($campaign));
    }

    public function send(Campaign $campaign): CampaignDetailResource
    {
        $this->authorize('send', $campaign);

        return $this->detail($this->campaigns->sendNow($campaign));
    }

    public function pause(Campaign $campaign): CampaignDetailResource
    {
        $this->authorize('cancelPause', $campaign);

        return $this->detail($this->campaigns->pause($campaign));
    }

    public function resume(Campaign $campaign): CampaignDetailResource
    {
        $this->authorize('cancelPause', $campaign);

        return $this->detail($this->campaigns->resume($campaign));
    }

    public function cancel(Campaign $campaign): CampaignDetailResource
    {
        $this->authorize('cancelPause', $campaign);

        return $this->detail($this->campaigns->cancel($campaign));
    }

    public function approve(Campaign $campaign): CampaignDetailResource
    {
        $this->authorize('approve', $campaign);

        return $this->detail($this->campaigns->approve($campaign, request()->user()));
    }

    /**
     * `POST /campaigns/{id}/duplicate` (and legacy `clone` alias).
     */
    public function duplicate(DuplicateCampaignRequest $request, Campaign $campaign): JsonResponse
    {
        $this->authorize('clonable', $campaign);

        $options = [];
        if ($request->filled('name')) {
            $options['name'] = $request->validated('name');
        }
        if ($request->has('folder_id')) {
            $folderUuid = $request->validated('folder_id');
            $options['folder_id'] = $folderUuid === null ? null : CampaignFolder::query()->where('uuid', $folderUuid)->value('id');
        }

        $copy = $this->campaigns->duplicate($campaign, $request->user(), $options);

        return (new CampaignDetailResource($copy->load(self::LIST_RELATIONS)))->response()->setStatusCode(201);
    }

    public function clone(DuplicateCampaignRequest $request, Campaign $campaign): JsonResponse
    {
        return $this->duplicate($request, $campaign);
    }

    public function sendToNewContacts(DuplicateCampaignRequest $request, Campaign $campaign): JsonResponse
    {
        $this->authorize('send', $campaign);

        $child = $this->campaigns->sendToNewContacts($campaign, $request->user(), $request->validated('name'));

        return (new CampaignDetailResource($child->load(self::LIST_RELATIONS)))->response()->setStatusCode(201);
    }

    public function archive(Campaign $campaign): CampaignDetailResource
    {
        $this->authorize('archive', $campaign);

        return $this->detail($this->campaigns->archive($campaign));
    }

    public function unarchive(Campaign $campaign): CampaignDetailResource
    {
        $this->authorize('archive', $campaign);

        return $this->detail($this->campaigns->unarchive($campaign));
    }

    /**
     * `POST /campaigns/bulk` — `CampaignBulkInput` → `BulkResult`.
     */
    public function bulk(CampaignBulkRequest $request): JsonResponse
    {
        $data = $request->validated();
        $user = $request->user();
        $folderId = isset($data['folder_id']) ? CampaignFolder::query()->where('uuid', $data['folder_id'])->value('id') : null;

        $campaigns = Campaign::query()->whereIn('uuid', $data['ids'])->get()->keyBy('uuid');
        $processed = 0;
        $skipped = [];
        $failed = 0;

        foreach ($data['ids'] as $uuid) {
            $campaign = $campaigns->get($uuid);

            if ($campaign === null) {
                $skipped[] = ['id' => $uuid, 'reason' => 'Campagne introuvable'];

                continue;
            }

            $ability = match ($data['action']) {
                'archive', 'unarchive' => 'archive',
                'delete', 'move' => 'update',
            };
            if (! Gate::forUser($user)->allows($ability, $campaign)) {
                $skipped[] = ['id' => $uuid, 'reason' => 'Action non autorisée'];

                continue;
            }

            if (! $this->bulkActionAllowed($campaign, $data['action'])) {
                $skipped[] = ['id' => $uuid, 'reason' => 'Statut incompatible'];

                continue;
            }

            try {
                match ($data['action']) {
                    'archive' => $this->campaigns->archive($campaign),
                    'unarchive' => $this->campaigns->unarchive($campaign),
                    'delete' => $this->campaigns->delete($campaign),
                    'move' => $this->campaigns->move($campaign, $folderId),
                };
                $processed++;
            } catch (Throwable $e) {
                report($e);
                $failed++;
            }
        }

        return response()->json(['data' => ['processed' => $processed, 'skipped' => $skipped, 'failed' => $failed]]);
    }

    /**
     * §15.8 — "Recipients" tab: `messages` grid for this campaign (test sends
     * excluded), optional `status` filter.
     */
    public function recipients(Request $request, Campaign $campaign): AnonymousResourceCollection
    {
        $this->authorize('view', $campaign);

        $query = $campaign->deliveryMessages()->with(['recipient', 'smtpAccount'])->orderByDesc('id');

        if ($request->filled('status')) {
            $query->where('status', (string) $request->query('status'));
        }

        return MessageResource::collection($query->paginate(50));
    }

    /**
     * §15.8 — "Analytics" tab (test sends excluded).
     */
    public function analytics(Campaign $campaign): JsonResponse
    {
        $this->authorize('view', $campaign);

        $counts = $campaign->deliveryMessages()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $materialised = $campaign->campaignRecipients()->count();

        return response()->json([
            'targeted' => $materialised > 0 ? $materialised : $this->campaigns->resolveTargetRecipients($campaign)->count(),
            'by_status' => $counts,
            'open_count' => (int) $campaign->deliveryMessages()->sum('open_count'),
            'click_count' => (int) $campaign->deliveryMessages()->sum('click_count'),
        ]);
    }

    private function detail(Campaign $campaign): CampaignDetailResource
    {
        return new CampaignDetailResource($campaign->load(self::LIST_RELATIONS));
    }

    private function bulkActionAllowed(Campaign $campaign, string $action): bool
    {
        $actions = $this->campaigns->availableActions($campaign);

        return match ($action) {
            'archive' => in_array('archive', $actions, true),
            'unarchive' => in_array('unarchive', $actions, true),
            'delete' => in_array('delete', $actions, true),
            'move' => true,
        };
    }

    /**
     * Filters shared by the list and `status_counts` (everything except the
     * status tab / legacy status).
     *
     * @param  array<string, mixed>  $filters
     * @return Builder<Campaign>
     */
    private function filteredQuery(array $filters): Builder
    {
        $query = Campaign::query();

        if (! empty($filters['folder_id'])) {
            if ($filters['folder_id'] === 'none') {
                $query->whereNull('folder_id');
            } else {
                $query->whereIn('folder_id', CampaignFolder::query()->select('id')->where('uuid', $filters['folder_id']));
            }
        }

        if (! empty($filters['tag'])) {
            $query->whereJsonContains('tags', $filters['tag']);
        }

        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (! empty($filters['q'])) {
            $term = '%'.Str::of($filters['q'])->replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_']).'%';
            $query->where(fn (Builder $q) => $q->where('name', 'like', $term)->orWhere('subject', 'like', $term));
        }

        return $query;
    }

    /**
     * @param  Builder<Campaign>  $query
     */
    private function applyStatusTab(Builder $query, string $tab): void
    {
        if ($tab === 'archived') {
            $query->whereNotNull('archived_at');

            return;
        }

        $query->whereNull('archived_at');

        $statuses = match ($tab) {
            'draft' => [CampaignStatus::Draft],
            'scheduled' => [CampaignStatus::Scheduled],
            'running' => [CampaignStatus::Running, CampaignStatus::Paused],
            'sent' => [CampaignStatus::Completed],
            'suspended' => [CampaignStatus::Suspended],
            default => null,
        };

        if ($statuses !== null) {
            $query->whereIn('status', array_map(fn (CampaignStatus $s) => $s->value, $statuses));
        }
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, int>
     */
    private function statusCounts(array $filters): array
    {
        $byStatus = $this->filteredQuery($filters)
            ->whereNull('archived_at')
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->map(fn ($n): int => (int) $n);

        $get = fn (CampaignStatus $s): int => (int) ($byStatus[$s->value] ?? 0);

        return [
            'all' => (int) $byStatus->sum(),
            'draft' => $get(CampaignStatus::Draft),
            'scheduled' => $get(CampaignStatus::Scheduled),
            'running' => $get(CampaignStatus::Running) + $get(CampaignStatus::Paused),
            'sent' => $get(CampaignStatus::Completed),
            'suspended' => $get(CampaignStatus::Suspended),
            'archived' => $this->filteredQuery($filters)->whereNotNull('archived_at')->count(),
        ];
    }
}
