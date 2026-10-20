<?php

namespace App\Modules\Automations\Services;

use App\Modules\Automations\Models\Automation;
use App\Modules\Automations\Models\AutomationStep;

/**
 * docs/43 §43.4.19 — navigation in the linear step tree: siblings run in
 * `position` order; a condition step jumps into its yes/no branch and, when
 * that branch is exhausted, both branches continue with the step that
 * follows the condition (the next common step) or end the run.
 */
class StepGraph
{
    public function first(Automation $automation): ?AutomationStep
    {
        return $this->firstIn($automation->id, null, null);
    }

    public function firstOfBranch(AutomationStep $condition, string $branch): ?AutomationStep
    {
        return $this->firstIn($condition->automation_id, $condition->id, $branch);
    }

    /** The step that runs after `$step` completed (climbing out of branches). */
    public function next(AutomationStep $step): ?AutomationStep
    {
        $current = $step;

        while (true) {
            $sibling = AutomationStep::query()
                ->where('automation_id', $current->automation_id)
                ->where('parent_step_id', $current->parent_step_id)
                ->where('branch', $current->branch)
                ->where('position', '>', $current->position)
                ->orderBy('position')
                ->orderBy('id')
                ->first();

            if ($sibling !== null) {
                return $sibling;
            }

            if ($current->parent_step_id === null) {
                return null;
            }

            $current = AutomationStep::query()->find($current->parent_step_id);

            if ($current === null) {
                return null;
            }
        }
    }

    private function firstIn(int $automationId, ?int $parentId, ?string $branch): ?AutomationStep
    {
        return AutomationStep::query()
            ->where('automation_id', $automationId)
            ->where('parent_step_id', $parentId)
            ->where('branch', $branch)
            ->orderBy('position')
            ->orderBy('id')
            ->first();
    }
}
