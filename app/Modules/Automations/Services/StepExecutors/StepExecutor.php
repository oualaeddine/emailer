<?php

namespace App\Modules\Automations\Services\StepExecutors;

use App\Modules\Automations\Models\AutomationRun;
use App\Modules\Automations\Models\AutomationStep;
use App\Modules\Recipients\Models\Recipient;

/**
 * One implementation per `AutomationStepType`. An executor performs the
 * step's side effect for the run's contact and tells the runner where to go
 * next; it throws {@see StepFailedException} (or any exception) to fail the run.
 */
interface StepExecutor
{
    public function execute(AutomationRun $run, AutomationStep $step, Recipient $recipient): StepResult;
}
