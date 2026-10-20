<?php

namespace App\Modules\Automations\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Automations\Enums\AutomationStatus;
use App\Modules\Automations\Http\Requests\ReplaceAutomationStepsRequest;
use App\Modules\Automations\Http\Requests\StoreAutomationRequest;
use App\Modules\Automations\Http\Requests\UpdateAutomationRequest;
use App\Modules\Automations\Http\Resources\AutomationResource;
use App\Modules\Automations\Models\Automation;
use App\Modules\Automations\Services\AutomationService;
use App\Modules\Automations\Services\AutomationStatsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/** docs/45 §45.7.5 — WP-91 automations endpoints (permission `automations.manage`). */
class AutomationController extends Controller
{
    public function __construct(
        private readonly AutomationService $automations,
        private readonly AutomationStatsService $stats,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Automation::class);

        $validated = $request->validate([
            'q' => ['sometimes', 'nullable', 'string', 'max:150'],
            'status' => ['sometimes', 'nullable', Rule::enum(AutomationStatus::class)],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);

        $page = $this->automations->paginate($validated['q'] ?? null, $validated['status'] ?? null, (int) ($validated['per_page'] ?? 25));
        $request->attributes->set(AutomationResource::STATS_ATTRIBUTE, $this->stats->runCounts($page->getCollection()->pluck('id')->all()));

        return AutomationResource::collection($page);
    }

    public function store(StoreAutomationRequest $request): JsonResponse
    {
        $data = $request->validated();
        if ($request->has('steps')) {
            $data['steps'] = $request->input('steps');
        }

        $automation = $this->automations->create($data, $request->user());

        return $this->detail($automation)->response()->setStatusCode(201);
    }

    public function show(string $id): AutomationResource
    {
        $automation = $this->find($id);
        $this->authorize('view', $automation);

        return $this->detail($automation);
    }

    public function update(UpdateAutomationRequest $request, string $id): AutomationResource
    {
        $automation = $this->find($id);
        $data = $request->validated();
        if ($request->has('trigger.config')) {
            $data['trigger']['config'] = $request->input('trigger.config') ?? [];
        }

        return $this->detail($this->automations->update($automation, $data));
    }

    public function replaceSteps(ReplaceAutomationStepsRequest $request, string $id): AutomationResource
    {
        $automation = $this->find($id);

        return $this->detail($this->automations->replaceSteps($automation, array_values((array) $request->input('steps'))));
    }

    public function destroy(string $id): Response
    {
        $automation = $this->find($id);
        $this->authorize('delete', $automation);
        $this->automations->delete($automation);

        return response()->noContent();
    }

    public function activate(Request $request, string $id): AutomationResource
    {
        $automation = $this->find($id);
        $this->authorize('update', $automation);

        return $this->detail($this->automations->activate($automation, $request->user()));
    }

    public function pause(Request $request, string $id): AutomationResource
    {
        $automation = $this->find($id);
        $this->authorize('update', $automation);

        return $this->detail($this->automations->pause($automation, $request->user()));
    }

    public function duplicate(Request $request, string $id): JsonResponse
    {
        $automation = $this->find($id);
        $this->authorize('create', Automation::class);

        return $this->detail($this->automations->duplicate($automation, $request->user()))->response()->setStatusCode(201);
    }

    public function activity(Request $request, string $id): JsonResponse
    {
        $automation = $this->find($id);
        $this->authorize('view', $automation);

        $validated = $request->validate([
            'date_from' => ['sometimes', 'nullable', 'date'],
            'date_to' => ['sometimes', 'nullable', 'date', 'after_or_equal:date_from'],
        ]);

        $from = isset($validated['date_from']) ? Carbon::parse($validated['date_from'])->utc() : null;
        $to = isset($validated['date_to']) ? Carbon::parse($validated['date_to'])->utc() : null;
        if ($to !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $validated['date_to']) === 1) {
            $to = $to->endOfDay();
        }

        return response()->json(['data' => $this->stats->activity($automation, $from, $to)]);
    }

    private function detail(Automation $automation): AutomationResource
    {
        return (new AutomationResource($automation->loadMissing('createdBy')))->withSteps();
    }

    private function find(string $id): Automation
    {
        return Automation::query()->where('uuid', $id)->firstOrFail();
    }
}
