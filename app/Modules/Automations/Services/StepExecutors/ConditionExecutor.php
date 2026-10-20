<?php

namespace App\Modules\Automations\Services\StepExecutors;

use App\Modules\Automations\Models\AutomationRun;
use App\Modules\Automations\Models\AutomationStep;
use App\Modules\Recipients\Models\Recipient;
use App\Modules\Recipients\Segments\Contracts\RuleTreeCompiler;
use Illuminate\Validation\ValidationException;

/** `condition`: { rules: RuleTree } evaluated for the contact → yes / no branch. */
class ConditionExecutor implements StepExecutor
{
    public function __construct(private readonly RuleTreeCompiler $compiler) {}

    public function execute(AutomationRun $run, AutomationStep $step, Recipient $recipient): StepResult
    {
        $rules = $step->config['rules'] ?? null;

        if (! is_array($rules)) {
            throw new StepFailedException('Condition sans règle.');
        }

        try {
            return StepResult::branch($this->compiler->matches($recipient, $rules));
        } catch (ValidationException $e) {
            throw new StepFailedException('Condition invalide : '.collect($e->errors())->flatten()->first());
        }
    }
}
