<?php

namespace App\Modules\Automations\Enums;

/** docs/45 §45.7.5 — `AutomationRun.status`. */
enum AutomationRunStatus: string
{
    case Active = 'active';
    case Waiting = 'waiting';
    case Completed = 'completed';
    case Exited = 'exited';
    case Failed = 'failed';

    /** @return list<string> */
    public static function inProgressValues(): array
    {
        return [self::Active->value, self::Waiting->value];
    }

    public function isInProgress(): bool
    {
        return in_array($this->value, self::inProgressValues(), true);
    }
}
