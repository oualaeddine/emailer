<?php

namespace App\Modules\Automations\Enums;

/** docs/45 §45.7.5 — `wait` step unit (≥ 1 minute, ≤ 365 days). */
enum AutomationWaitUnit: string
{
    case Minutes = 'minutes';
    case Hours = 'hours';
    case Days = 'days';

    public function toMinutes(int $amount): int
    {
        return match ($this) {
            self::Minutes => $amount,
            self::Hours => $amount * 60,
            self::Days => $amount * 1440,
        };
    }
}
