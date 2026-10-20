<?php

namespace App\Modules\Automations\Http\Resources;

use App\Modules\Automations\Models\Automation;
use App\Modules\Automations\Services\AutomationStatsService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * docs/45 §45.7.5 — `Automation` (list) / `AutomationDetail` (with `steps`
 * when {@see withSteps()} is used). Run counters are pre-computed for a
 * page with {@see AutomationStatsService::runCounts()} and passed in the
 * request attribute {@see STATS_ATTRIBUTE} (else computed for this automation).
 *
 * @mixin Automation
 */
class AutomationResource extends JsonResource
{
    public const STATS_ATTRIBUTE = 'automation_run_counts';

    private bool $withSteps = false;

    public function withSteps(): static
    {
        $this->withSteps = true;

        return $this;
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var Automation $automation */
        $automation = $this->resource;
        $statsService = app(AutomationStatsService::class);
        $stats = ($request->attributes->get(self::STATS_ATTRIBUTE) ?? [])[$automation->id] ?? $statsService->runCounts([$automation->id])[$automation->id];

        $data = [
            'id' => $automation->uuid,
            'name' => $automation->name,
            'description' => $automation->description,
            'status' => $automation->status,
            'trigger' => [
                'type' => $automation->trigger_type,
                'config' => (object) ($automation->trigger_config ?? []),
            ],
            'allow_reentry' => (bool) $automation->allow_reentry,
            'reentry_after_days' => $automation->reentry_after_days,
            'stats' => $stats,
            'created_by' => $automation->createdBy ? ['id' => $automation->createdBy->uuid, 'name' => $automation->createdBy->name] : null,
            'activated_at' => $automation->activated_at?->toIso8601ZuluString(),
            'created_at' => $automation->created_at?->toIso8601ZuluString(),
            'updated_at' => $automation->updated_at?->toIso8601ZuluString(),
        ];

        if ($this->withSteps) {
            $data['steps'] = AutomationStepResource::tree($automation, $statsService->stepStats($automation));
        }

        return $data;
    }
}
