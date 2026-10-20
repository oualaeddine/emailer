<?php

namespace App\Modules\Automations\Models;

use App\Modules\Automations\Enums\AutomationRunStatus;
use App\Modules\Automations\Jobs\AdvanceAutomationRunsJob;
use App\Modules\Recipients\Models\Recipient;
use App\Support\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * docs/45 §45.8 Slot R — one contact's journey through an automation.
 * `next_run_at` is when the tick ({@see AdvanceAutomationRunsJob})
 * should advance it next (a `wait` step stores its resume time there).
 *
 * @property int $id
 * @property string $uuid
 * @property int $automation_id
 * @property int $recipient_id
 * @property string $status
 * @property int|null $current_step_id
 * @property Carbon|null $next_run_at
 * @property array<string, mixed>|null $context
 * @property Carbon $started_at
 * @property Carbon|null $finished_at
 * @property string|null $error
 */
class AutomationRun extends Model
{
    use HasUuid;

    protected $fillable = [
        'automation_id', 'recipient_id', 'status', 'current_step_id', 'next_run_at',
        'context', 'started_at', 'finished_at', 'error',
    ];

    protected function casts(): array
    {
        return [
            'context' => 'array',
            'next_run_at' => 'datetime',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function isInProgress(): bool
    {
        return in_array($this->status, AutomationRunStatus::inProgressValues(), true);
    }

    /** @return BelongsTo<Automation, $this> */
    public function automation(): BelongsTo
    {
        return $this->belongsTo(Automation::class)->withTrashed();
    }

    /** @return BelongsTo<Recipient, $this> */
    public function recipient(): BelongsTo
    {
        return $this->belongsTo(Recipient::class)->withTrashed();
    }

    /** @return BelongsTo<AutomationStep, $this> */
    public function currentStep(): BelongsTo
    {
        return $this->belongsTo(AutomationStep::class, 'current_step_id');
    }

    /** @return HasMany<AutomationStepLog, $this> */
    public function logs(): HasMany
    {
        return $this->hasMany(AutomationStepLog::class);
    }
}
