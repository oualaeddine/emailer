<?php

namespace App\Modules\Automations\Services\StepExecutors;

use App\Modules\Automations\Enums\AutomationWaitUnit;
use App\Modules\Automations\Models\AutomationRun;
use App\Modules\Automations\Models\AutomationStep;
use App\Modules\Recipients\Models\Recipient;

/** `wait`: { amount, unit } — the run sleeps until `next_run_at` (resumed by the tick). */
class WaitExecutor implements StepExecutor
{
    public function execute(AutomationRun $run, AutomationStep $step, Recipient $recipient): StepResult
    {
        $unit = AutomationWaitUnit::tryFrom((string) ($step->config['unit'] ?? ''));
        $amount = (int) ($step->config['amount'] ?? 0);

        if ($unit === null || $amount < 1) {
            throw new StepFailedException('Délai d’attente invalide.');
        }

        return StepResult::wait(now()->addMinutes($unit->toMinutes($amount)));
    }
}
