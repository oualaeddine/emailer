<?php

namespace App\Modules\Automations\Models;

use App\Modules\Automations\Enums\AutomationStatus;
use App\Modules\Identity\Models\User;
use App\Support\Concerns\HasUuid;
use Database\Factories\AutomationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * docs/43 §43.4.19, docs/45 §45.7.5 / §45.8 Slot R — a workflow: one trigger
 * (`trigger_type` + `trigger_config`) and a tree of steps.
 *
 * @property int $id
 * @property string $uuid
 * @property string $name
 * @property string|null $description
 * @property string $status
 * @property string $trigger_type
 * @property array<string, mixed> $trigger_config
 * @property bool $allow_reentry
 * @property int|null $reentry_after_days
 * @property Carbon|null $activated_at
 * @property int|null $created_by
 */
class Automation extends Model
{
    /** @use HasFactory<AutomationFactory> */
    use HasFactory, HasUuid, SoftDeletes;

    protected $fillable = [
        'name', 'description', 'status', 'trigger_type', 'trigger_config',
        'allow_reentry', 'reentry_after_days', 'activated_at', 'created_by',
    ];

    protected $attributes = [
        'status' => 'draft',
        'allow_reentry' => false,
    ];

    protected function casts(): array
    {
        return [
            'trigger_config' => 'array',
            'allow_reentry' => 'boolean',
            'reentry_after_days' => 'integer',
            'activated_at' => 'datetime',
        ];
    }

    protected static function newFactory(): AutomationFactory
    {
        return AutomationFactory::new();
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function isActive(): bool
    {
        return $this->status === AutomationStatus::Active->value && ! $this->trashed();
    }

    /** @param  Builder<self>  $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', AutomationStatus::Active->value);
    }

    /** @return HasMany<AutomationStep, $this> */
    public function steps(): HasMany
    {
        return $this->hasMany(AutomationStep::class);
    }

    /** @return HasMany<AutomationRun, $this> */
    public function runs(): HasMany
    {
        return $this->hasMany(AutomationRun::class);
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
