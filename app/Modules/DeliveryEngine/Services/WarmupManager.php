<?php

namespace App\Modules\DeliveryEngine\Services;

use App\Modules\DeliveryEngine\Models\SmtpAccount;

/**
 * docs/19-throttling.md §19.6 — Warm-up Schedules.
 */
class WarmupManager
{
    /**
     * The effective daily cap for the account today: the warm-up stage cap
     * if warm-up is enabled and a schedule is linked, clamped to the last
     * defined stage once exhausted, combined with `daily_quota` by taking
     * the lower of the two ("the lower of the two applies").
     */
    public function effectiveDailyQuota(SmtpAccount $account): ?int
    {
        if (! $account->warmup_enabled || $account->warmupSchedule === null) {
            return $account->daily_quota;
        }

        $day = (int) $account->created_at->diffInDays(now()) + 1;
        $cap = $account->warmupSchedule->capForDay($day);

        if ($account->daily_quota === null) {
            return $cap;
        }

        return min($account->daily_quota, $cap);
    }
}
