<?php

namespace App\Modules\Automations\Services;

use App\Modules\Automations\Enums\AutomationRunStatus;
use App\Modules\Automations\Enums\AutomationTriggerType;
use App\Modules\Automations\Events\AutomationRunStarted;
use App\Modules\Automations\Jobs\StartAutomationRunJob;
use App\Modules\Automations\Models\Automation;
use App\Modules\Automations\Models\AutomationRun;
use App\Modules\Recipients\Models\Recipient;
use App\Modules\Recipients\Segments\Contracts\RuleTreeCompiler;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

/**
 * docs/43 §43.4.19 — turns trigger occurrences into runs. Listeners call
 * {@see dispatch()} with the matching active automations; the queued
 * {@see StartAutomationRunJob} then calls {@see start()} per contact, which
 * applies the trigger filter and the re-entry setting:
 * - `allow_reentry = false` → a contact enters once, ever ("never");
 * - `allow_reentry = true`, `reentry_after_days = N` → again once N days
 *   passed since its last entry ("after N days");
 * - `allow_reentry = true`, no delay → every time ("always").
 * A contact never has two runs in progress in the same automation.
 */
class TriggerDispatcher
{
    private const CHUNK = 500;

    public function __construct(
        private readonly StepGraph $graph,
        private readonly AutomationRunner $runner,
        private readonly RuleTreeCompiler $compiler,
    ) {}

    /** @return Collection<int, Automation> */
    public function activeAutomations(AutomationTriggerType $type): Collection
    {
        return Automation::query()->active()->where('trigger_type', $type->value)->orderBy('id')->get();
    }

    /**
     * @param  iterable<Automation>  $automations
     * @param  list<int>  $recipientIds
     * @param  array<string, mixed>  $context  stored on the run (`trigger` payload)
     */
    public function dispatch(iterable $automations, array $recipientIds, array $context = []): void
    {
        $recipientIds = array_values(array_unique(array_map('intval', $recipientIds)));
        if ($recipientIds === []) {
            return;
        }

        foreach ($automations as $automation) {
            foreach (array_chunk($recipientIds, self::CHUNK) as $chunk) {
                StartAutomationRunJob::dispatch($automation->id, $chunk, $context);
            }
        }
    }

    /** @param  array<string, mixed>  $context */
    public function start(Automation $automation, int $recipientId, array $context = []): ?AutomationRun
    {
        $lock = Cache::lock("automation:{$automation->id}:recipient:{$recipientId}", 30);

        if (! $lock->block(5)) {
            return null;
        }

        try {
            $run = $this->createRun($automation->refresh(), $recipientId, $context);
        } finally {
            $lock->release();
        }

        if ($run !== null) {
            AutomationRunStarted::dispatch($run->id, $automation->id, $recipientId);
            $this->runner->advance($run);
        }

        return $run;
    }

    /** @param  array<string, mixed>  $context */
    private function createRun(Automation $automation, int $recipientId, array $context): ?AutomationRun
    {
        if (! $automation->isActive()) {
            return null;
        }

        $recipient = Recipient::query()->find($recipientId);
        if ($recipient === null || ! $this->mayEnter($automation, $recipientId) || ! $this->passesFilter($automation, $recipient)) {
            return null;
        }

        $first = $this->graph->first($automation);
        if ($first === null) {
            return null;
        }

        return AutomationRun::query()->create([
            'automation_id' => $automation->id,
            'recipient_id' => $recipientId,
            'status' => AutomationRunStatus::Active->value,
            'current_step_id' => $first->id,
            'next_run_at' => now(),
            'context' => $context === [] ? null : $context,
            'started_at' => now(),
        ]);
    }

    public function mayEnter(Automation $automation, int $recipientId): bool
    {
        $runs = AutomationRun::query()->where('automation_id', $automation->id)->where('recipient_id', $recipientId);

        if ((clone $runs)->whereIn('status', AutomationRunStatus::inProgressValues())->exists()) {
            return false;
        }

        $lastStartedAt = (clone $runs)->max('started_at');
        if ($lastStartedAt === null) {
            return true;
        }

        if (! $automation->allow_reentry) {
            return false;
        }

        $days = $automation->reentry_after_days;

        return $days === null || $days < 1
            || now()->subDays($days)->greaterThanOrEqualTo(Carbon::parse($lastStartedAt));
    }

    private function passesFilter(Automation $automation, Recipient $recipient): bool
    {
        $filter = $automation->trigger_config['filter'] ?? null;

        if (! is_array($filter) || ($filter['children'] ?? []) === []) {
            return true;
        }

        try {
            return $this->compiler->matches($recipient, $filter);
        } catch (ValidationException) {
            return false;
        }
    }
}
