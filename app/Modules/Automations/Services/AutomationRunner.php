<?php

namespace App\Modules\Automations\Services;

use App\Modules\Automations\Enums\AutomationRunStatus;
use App\Modules\Automations\Enums\AutomationStatus;
use App\Modules\Automations\Enums\AutomationStepOutcome;
use App\Modules\Automations\Events\AutomationRunFinished;
use App\Modules\Automations\Models\AutomationRun;
use App\Modules\Automations\Models\AutomationStep;
use App\Modules\Automations\Models\AutomationStepLog;
use App\Modules\Automations\Services\StepExecutors\StepExecutorRegistry;
use App\Modules\Automations\Services\StepExecutors\StepResult;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * docs/43 §43.4.19 — advances runs. A run executes its steps back to back
 * until a `wait` step (status `waiting`, `next_run_at` = resume time), an
 * `end` step or the end of the tree (status `completed`). The scheduler
 * tick ({@see tick()}) resumes due runs; runs of a paused / deleted
 * automation are frozen (never advanced) until it is re-activated.
 */
class AutomationRunner
{
    /** Guard against loops in a corrupted tree (≤ 50 steps per automation). */
    private const MAX_STEPS_PER_ADVANCE = 200;

    private const TICK_BATCH = 500;

    private const TICK_MAX = 5000;

    public function __construct(
        private readonly StepGraph $graph,
        private readonly StepExecutorRegistry $executors,
    ) {}

    /** Advances every due run of an active automation; returns how many were processed. */
    public function tick(?Carbon $now = null): int
    {
        $now ??= now();
        $processed = 0;
        $lastId = 0;

        while ($processed < self::TICK_MAX) {
            $runs = AutomationRun::query()
                ->whereIn('status', AutomationRunStatus::inProgressValues())
                ->whereNotNull('next_run_at')
                ->where('next_run_at', '<=', $now)
                ->where('id', '>', $lastId)
                ->whereHas('automation', fn ($q) => $q->where('status', AutomationStatus::Active->value)->whereNull('deleted_at'))
                ->orderBy('id')
                ->limit(self::TICK_BATCH)
                ->get();

            if ($runs->isEmpty()) {
                break;
            }

            foreach ($runs as $run) {
                $this->advance($run);
                $processed++;
                $lastId = $run->id;
            }
        }

        return $processed;
    }

    public function advance(AutomationRun $run): void
    {
        $lock = Cache::lock('automation-run:'.$run->id, 120);

        if (! $lock->get()) {
            return;
        }

        try {
            $this->advanceLocked($run->refresh());
        } finally {
            $lock->release();
        }
    }

    /** Ends a run in progress (manual exit or step failure). */
    public function finish(AutomationRun $run, AutomationRunStatus $status, ?string $error = null): void
    {
        $run->forceFill([
            'status' => $status->value,
            'finished_at' => now(),
            'next_run_at' => null,
            'current_step_id' => $status === AutomationRunStatus::Completed ? null : $run->current_step_id,
            'error' => $error,
        ])->save();

        AutomationRunFinished::dispatch($run->id, $status->value);
    }

    private function advanceLocked(AutomationRun $run): void
    {
        if (! $run->isInProgress()) {
            return;
        }

        $automation = $run->automation;
        if ($automation === null || $automation->trashed() || $automation->status !== AutomationStatus::Active->value) {
            return;
        }

        $recipient = $run->recipient;
        if ($recipient === null || $recipient->trashed()) {
            $this->finish($run, AutomationRunStatus::Exited, 'Contact supprimé.');

            return;
        }

        for ($i = 0; $i < self::MAX_STEPS_PER_ADVANCE; $i++) {
            $step = $run->current_step_id !== null ? AutomationStep::query()->find($run->current_step_id) : null;

            if ($step === null) {
                $this->finish($run, AutomationRunStatus::Exited, 'Étape supprimée du scénario.');

                return;
            }

            if ($run->status === AutomationRunStatus::Waiting->value) {
                if ($run->next_run_at !== null && $run->next_run_at->isFuture()) {
                    return;
                }

                // The wait is over: the wait step completes, the run moves on.
                $this->log($run, $step, AutomationStepOutcome::Completed);
                $run->status = AutomationRunStatus::Active->value;
                if (! $this->moveTo($run, $this->graph->next($step))) {
                    return;
                }

                continue;
            }

            try {
                $result = $this->executors->for($step->type)->execute($run, $step, $recipient);
            } catch (Throwable $e) {
                report($e);
                $this->log($run, $step, AutomationStepOutcome::Failed);
                $this->finish($run, AutomationRunStatus::Failed, mb_substr($e->getMessage(), 0, 1000));

                return;
            }

            switch ($result->kind) {
                case StepResult::WAIT:
                    $run->forceFill([
                        'status' => AutomationRunStatus::Waiting->value,
                        'next_run_at' => $result->resumeAt,
                    ])->save();

                    return;

                case StepResult::END:
                    $this->log($run, $step, $result->outcome);
                    $this->finish($run, AutomationRunStatus::Completed);

                    return;

                case StepResult::BRANCH:
                    $this->log($run, $step, $result->outcome);
                    $target = $this->graph->firstOfBranch($step, (string) $result->branch) ?? $this->graph->next($step);
                    if (! $this->moveTo($run, $target)) {
                        return;
                    }
                    break;

                default:
                    $this->log($run, $step, $result->outcome, $result->messageId);
                    if (! $this->moveTo($run, $this->graph->next($step))) {
                        return;
                    }
            }
        }

        // Loop guard hit: leave the run due so the next tick continues.
        $run->forceFill(['next_run_at' => now()])->save();
    }

    /** @return bool false when the run finished (no step left) */
    private function moveTo(AutomationRun $run, ?AutomationStep $next): bool
    {
        if ($next === null) {
            $this->finish($run, AutomationRunStatus::Completed);

            return false;
        }

        $run->forceFill([
            'status' => AutomationRunStatus::Active->value,
            'current_step_id' => $next->id,
            'next_run_at' => now(),
        ])->save();

        return true;
    }

    private function log(AutomationRun $run, AutomationStep $step, AutomationStepOutcome $outcome, ?int $messageId = null): void
    {
        AutomationStepLog::query()->create([
            'automation_run_id' => $run->id,
            'automation_step_id' => $step->id,
            'outcome' => $outcome->value,
            'message_id' => $messageId,
        ]);
    }
}
