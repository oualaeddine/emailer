<?php

namespace App\Modules\Automations\Models;

use App\Support\Concerns\HasUuid;
use Database\Factories\AutomationStepFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * docs/45 §45.8 Slot R — one node of an automation's step tree. Root steps
 * have `parent_step_id = null`; the steps of a condition's yes/no branch
 * point at the condition with `branch = yes|no`. Siblings run in
 * `position` order.
 *
 * @property int $id
 * @property string $uuid
 * @property int $automation_id
 * @property int|null $parent_step_id
 * @property string|null $branch
 * @property int $position
 * @property string $type
 * @property array<string, mixed> $config
 */
class AutomationStep extends Model
{
    /** @use HasFactory<AutomationStepFactory> */
    use HasFactory, HasUuid;

    protected $fillable = ['automation_id', 'parent_step_id', 'branch', 'position', 'type', 'config'];

    protected function casts(): array
    {
        return ['config' => 'array', 'position' => 'integer'];
    }

    protected static function newFactory(): AutomationStepFactory
    {
        return AutomationStepFactory::new();
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /** @return BelongsTo<Automation, $this> */
    public function automation(): BelongsTo
    {
        return $this->belongsTo(Automation::class);
    }

    /** @return BelongsTo<AutomationStep, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_step_id');
    }

    /** @return HasMany<AutomationStep, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_step_id');
    }
}
