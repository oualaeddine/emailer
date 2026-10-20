<?php

namespace App\Modules\Automations\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Automations\Enums\AutomationRunStatus;
use App\Modules\Automations\Http\Resources\AutomationRunResource;
use App\Modules\Automations\Models\Automation;
use App\Modules\Automations\Models\AutomationRun;
use App\Modules\Automations\Services\AutomationService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/** docs/45 §45.7.5 — runs list (cursor) and manual exit. */
class AutomationRunController extends Controller
{
    public function __construct(private readonly AutomationService $automations) {}

    public function index(Request $request, string $id): AnonymousResourceCollection
    {
        $automation = $this->automation($id);
        $this->authorize('view', $automation);

        $validated = $request->validate([
            'status' => ['sometimes', 'nullable', Rule::enum(AutomationRunStatus::class)],
            'cursor' => ['sometimes', 'nullable', 'string'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $runs = $automation->runs()
            ->with(['recipient', 'currentStep'])
            ->when($validated['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->orderByDesc('id')
            ->cursorPaginate((int) ($validated['per_page'] ?? 25));

        return AutomationRunResource::collection($runs);
    }

    public function exit(string $id, string $run): AutomationRunResource
    {
        $automation = $this->automation($id);
        $this->authorize('update', $automation);

        $model = AutomationRun::query()->where('automation_id', $automation->id)->where('uuid', $run)->firstOrFail();

        return new AutomationRunResource($this->automations->exitRun($model)->load(['recipient', 'currentStep']));
    }

    private function automation(string $id): Automation
    {
        return Automation::query()->where('uuid', $id)->firstOrFail();
    }
}
