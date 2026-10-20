<?php

namespace App\Modules\Automations\Enums;

/** docs/45 §45.8 Slot R — `automation_step_logs.outcome`. */
enum AutomationStepOutcome: string
{
    case Completed = 'completed';
    case Yes = 'yes';
    case No = 'no';
    case Failed = 'failed';
    case Skipped = 'skipped';
}
