<?php

namespace App\Modules\Automations\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * docs/45 §45.8 Slot R — a run went through a step (`outcome`
 * completed|yes|no|failed|skipped); `message_id` for send_email steps.
 *
 * @property int $id
 * @property int $automation_run_id
 * @property int $automation_step_id
 * @property string $outcome
 * @property int|null $message_id
 */
class AutomationStepLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['automation_run_id', 'automation_step_id', 'outcome', 'message_id'];

    /** @return BelongsTo<AutomationRun, $this> */
    public function run(): BelongsTo
    {
        return $this->belongsTo(AutomationRun::class, 'automation_run_id');
    }

    /** @return BelongsTo<AutomationStep, $this> */
    public function step(): BelongsTo
    {
        return $this->belongsTo(AutomationStep::class, 'automation_step_id');
    }
}
