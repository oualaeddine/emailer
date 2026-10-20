<?php

namespace App\Modules\Automations\Services;

use App\Modules\Audit\Services\AuditLogger;
use App\Modules\Automations\Enums\AutomationRunStatus;
use App\Modules\Automations\Enums\AutomationStatus;
use App\Modules\Automations\Enums\AutomationStepType;
use App\Modules\Automations\Models\Automation;
use App\Modules\Automations\Models\AutomationRun;
use App\Modules\Automations\Models\AutomationStep;
use App\Modules\Identity\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * docs/45 §45.7.5 — automations CRUD, whole-tree step replacement,
 * activate / pause / duplicate, manual run exit.
 */
class AutomationService
{
    public function __construct(
        private readonly AutomationValidator $validator,
        private readonly AutomationRunner $runner,
        private readonly AuditLogger $audit,
    ) {}

    /** @return LengthAwarePaginator<int, Automation> */
    public function paginate(?string $q, ?string $status, int $perPage): LengthAwarePaginator
    {
        return Automation::query()
            ->with('createdBy')
            ->when($q !== null && trim($q) !== '', fn ($query) => $query->where('name', 'like', '%'.addcslashes(trim((string) $q), '%_\\').'%'))
            ->when($status !== null && $status !== '', fn ($query) => $query->where('status', $status))
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    /** @param  array<string, mixed>  $input  validated AutomationInput (+ optional steps) */
    public function create(array $input, ?User $actor): Automation
    {
        return DB::transaction(function () use ($input, $actor): Automation {
            $automation = Automation::query()->create([
                'name' => $input['name'],
                'description' => $input['description'] ?? null,
                'status' => AutomationStatus::Draft->value,
                'trigger_type' => $input['trigger']['type'],
                'trigger_config' => $this->validator->normalizeTriggerConfig($input['trigger']['type'], $input['trigger']['config'] ?? []),
                'allow_reentry' => (bool) ($input['allow_reentry'] ?? false),
                'reentry_after_days' => ($input['allow_reentry'] ?? false) ? ($input['reentry_after_days'] ?? null) : null,
                'created_by' => $actor?->id,
            ]);

            if (isset($input['steps']) && is_array($input['steps'])) {
                $this->persistTree($automation, $input['steps']);
            }

            return $automation;
        });
    }

    /** @param  array<string, mixed>  $input  validated partial AutomationInput */
    public function update(Automation $automation, array $input): Automation
    {
        $attributes = [];

        foreach (['name', 'description', 'allow_reentry', 'reentry_after_days'] as $key) {
            if (array_key_exists($key, $input)) {
                $attributes[$key] = $input[$key];
            }
        }

        if (array_key_exists('trigger', $input)) {
            $type = $input['trigger']['type'];
            $config = $this->validator->normalizeTriggerConfig($type, $input['trigger']['config'] ?? []);

            if ($automation->isActive() && ($type !== $automation->trigger_type || $config != $automation->trigger_config)) {
                throw new ConflictHttpException('Mettez l’automatisation en pause avant de modifier son déclencheur.');
            }

            $attributes['trigger_type'] = $type;
            $attributes['trigger_config'] = $config;
        }

        if (($attributes['allow_reentry'] ?? $automation->allow_reentry) === false) {
            $attributes['reentry_after_days'] = null;
        }

        $automation->update($attributes);

        return $automation->refresh();
    }

    /** @param  list<array<string, mixed>>  $steps  validated AutomationStepInput[] */
    public function replaceSteps(Automation $automation, array $steps): Automation
    {
        if ($automation->isActive() && $this->hasRunsInProgress($automation)) {
            throw new ConflictHttpException('Des contacts sont en cours dans ce scénario : mettez-le en pause avant de modifier ses étapes.');
        }

        DB::transaction(function () use ($automation, $steps): void {
            $this->persistTree($automation, $steps);
            $automation->touch();
        });

        return $automation->refresh();
    }

    public function delete(Automation $automation): void
    {
        if ($automation->isActive()) {
            throw new ConflictHttpException('Mettez l’automatisation en pause avant de la supprimer.');
        }

        $automation->delete();
    }

    public function activate(Automation $automation, ?User $actor): Automation
    {
        $errors = $this->validator->triggerErrors(
            ['type' => $automation->trigger_type, 'config' => $automation->trigger_config ?? []], true,
        );

        $stepErrors = $this->validator->stepsErrors($this->toInputTree($automation), true);
        if ($stepErrors !== []) {
            $errors['steps'] = implode(' ', array_unique(array_values($stepErrors)));
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($this->errorBag($errors, $stepErrors));
        }

        if ($automation->status !== AutomationStatus::Active->value) {
            $automation->update(['status' => AutomationStatus::Active->value, 'activated_at' => now()]);
            $this->audit->record('automation.activated', $automation, $actor);
        }

        return $automation->refresh();
    }

    public function pause(Automation $automation, ?User $actor): Automation
    {
        if ($automation->status !== AutomationStatus::Active->value) {
            throw new ConflictHttpException('Seules les automatisations actives peuvent être mises en pause.');
        }

        $automation->update(['status' => AutomationStatus::Paused->value]);
        $this->audit->record('automation.paused', $automation, $actor);

        return $automation->refresh();
    }

    public function duplicate(Automation $automation, ?User $actor): Automation
    {
        return DB::transaction(function () use ($automation, $actor): Automation {
            $copy = Automation::query()->create([
                'name' => mb_substr($automation->name.' (copie)', 0, 150),
                'description' => $automation->description,
                'status' => AutomationStatus::Draft->value,
                'trigger_type' => $automation->trigger_type,
                'trigger_config' => $automation->trigger_config,
                'allow_reentry' => $automation->allow_reentry,
                'reentry_after_days' => $automation->reentry_after_days,
                'created_by' => $actor?->id,
            ]);

            $this->persistTree($copy, $this->toInputTree($automation, withIds: false));

            return $copy;
        });
    }

    public function exitRun(AutomationRun $run): AutomationRun
    {
        if (! $run->isInProgress()) {
            throw new ConflictHttpException('Ce contact a déjà quitté le scénario.');
        }

        $this->runner->finish($run, AutomationRunStatus::Exited, null);

        return $run->refresh();
    }

    public function hasRunsInProgress(Automation $automation): bool
    {
        return $automation->runs()->whereIn('status', AutomationRunStatus::inProgressValues())->exists();
    }

    /**
     * The stored tree as AutomationStepInput[] (ids = step uuids).
     *
     * @return list<array<string, mixed>>
     */
    public function toInputTree(Automation $automation, bool $withIds = true): array
    {
        $byParent = $automation->steps()->orderBy('position')->orderBy('id')->get()
            ->groupBy(fn (AutomationStep $s) => ($s->parent_step_id ?? 0).':'.($s->branch ?? ''));

        $build = function (int $parentId, string $branch) use (&$build, $byParent, $withIds): array {
            return $byParent->get("{$parentId}:{$branch}", collect())->values()->map(function (AutomationStep $s, int $i) use ($build, $withIds) {
                $node = [
                    'type' => $s->type,
                    'position' => $i,
                    'config' => $s->config ?? [],
                    'yes' => $s->type === AutomationStepType::Condition->value ? $build($s->id, 'yes') : [],
                    'no' => $s->type === AutomationStepType::Condition->value ? $build($s->id, 'no') : [],
                ];

                return $withIds ? ['id' => $s->uuid] + $node : $node;
            })->all();
        };

        return $build(0, '');
    }

    /**
     * Replaces the automation's step tree: steps sent back with their `id`
     * are updated in place (stats and runs on them are kept), others are
     * created, missing ones deleted.
     *
     * @param  list<array<string, mixed>>  $steps
     */
    private function persistTree(Automation $automation, array $steps): void
    {
        $existing = $automation->steps()->get()->keyBy('uuid');
        $kept = [];

        $persist = function (array $nodes, ?int $parentId, ?string $branch) use (&$persist, $automation, $existing, &$kept): void {
            $nodes = collect($nodes)->values()
                ->sortBy(fn ($node, $i) => [(int) ($node['position'] ?? $i), $i])
                ->values();

            foreach ($nodes as $position => $node) {
                $attributes = [
                    'parent_step_id' => $parentId,
                    'branch' => $branch,
                    'position' => $position,
                    'type' => $node['type'],
                    'config' => $this->validator->normalizeStepConfig($node['type'], $node['config'] ?? []),
                ];

                $id = $node['id'] ?? null;
                $step = is_string($id) && $existing->has($id) && ! isset($kept[$existing[$id]->id]) ? $existing[$id] : null;

                if ($step !== null) {
                    $step->fill($attributes)->save();
                } else {
                    $step = $automation->steps()->create($attributes);
                }

                $kept[$step->id] = true;

                if ($node['type'] === AutomationStepType::Condition->value) {
                    $persist($node['yes'] ?? [], $step->id, 'yes');
                    $persist($node['no'] ?? [], $step->id, 'no');
                }
            }
        };

        $persist($steps, null, null);

        $automation->steps()->whereNotIn('id', array_keys($kept) ?: [0])->get()
            ->each(fn (AutomationStep $s) => AutomationStep::query()->whereKey($s->id)->delete());
    }

    /**
     * @param  array<string, string>  $errors
     * @param  array<string, string>  $stepErrors
     * @return array<string, list<string>>
     */
    private function errorBag(array $errors, array $stepErrors): array
    {
        $bag = [];
        foreach ($errors as $key => $message) {
            $bag[$key] = $key === 'steps' ? array_values(array_unique($stepErrors)) : [$message];
        }

        // Detailed dot-path keys too, so the editor can point at the faulty step.
        foreach ($stepErrors as $key => $message) {
            $bag[$key] ??= [$message];
        }

        return $bag;
    }
}
