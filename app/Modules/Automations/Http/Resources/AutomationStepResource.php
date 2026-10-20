<?php

namespace App\Modules\Automations\Http\Resources;

use App\Modules\Automations\Enums\AutomationStepType;
use App\Modules\Automations\Models\Automation;
use App\Modules\Automations\Models\AutomationStep;
use Illuminate\Support\Collection;

/** docs/45 §45.7.5 — `AutomationStep` tree (condition steps carry `yes` / `no`). */
class AutomationStepResource
{
    /**
     * @param  array<int, array{entered: int, completed: int}>  $stats
     * @return list<array<string, mixed>>
     */
    public static function tree(Automation $automation, array $stats): array
    {
        $byParent = $automation->steps()->orderBy('position')->orderBy('id')->get()
            ->groupBy(fn (AutomationStep $s) => ($s->parent_step_id ?? 0).':'.($s->branch ?? ''));

        return self::level($byParent, 0, '', $stats);
    }

    /**
     * @param  Collection<string, Collection<int, AutomationStep>>  $byParent
     * @param  array<int, array{entered: int, completed: int}>  $stats
     * @return list<array<string, mixed>>
     */
    private static function level(Collection $byParent, int $parentId, string $branch, array $stats): array
    {
        return $byParent->get("{$parentId}:{$branch}", collect())->values()->map(function (AutomationStep $step) use ($byParent, $stats): array {
            $isCondition = $step->type === AutomationStepType::Condition->value;

            return [
                'id' => $step->uuid,
                'type' => $step->type,
                'position' => $step->position,
                'config' => (object) ($step->config ?? []),
                'yes' => $isCondition ? self::level($byParent, $step->id, 'yes', $stats) : [],
                'no' => $isCondition ? self::level($byParent, $step->id, 'no', $stats) : [],
                'stats' => $stats[$step->id] ?? ['entered' => 0, 'completed' => 0],
            ];
        })->all();
    }
}
