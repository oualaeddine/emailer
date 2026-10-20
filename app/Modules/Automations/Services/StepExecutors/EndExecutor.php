<?php

namespace App\Modules\Automations\Services\StepExecutors;

use App\Modules\Automations\Models\AutomationRun;
use App\Modules\Automations\Models\AutomationStep;
use App\Modules\Recipients\Models\Recipient;

/** `end`: the run completes here. */
class EndExecutor implements StepExecutor
{
    public function execute(AutomationRun $run, AutomationStep $step, Recipient $recipient): StepResult
    {
        return StepResult::end();
    }
}
