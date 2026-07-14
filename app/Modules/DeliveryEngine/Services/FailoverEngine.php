<?php

namespace App\Modules\DeliveryEngine\Services;

use App\Domain\Enums\SmtpHealthStatus;
use App\Modules\DeliveryEngine\Models\SmtpAccount;
use Illuminate\Support\Facades\Cache;

/**
 * docs/17-delivery-engine.md §17.8, docs/19-throttling.md §19.5 —
 * Failover Engine: marks an account degraded/unhealthy after consecutive
 * account-level failures, and lets it recover on success.
 */
class FailoverEngine
{
    private const UNHEALTHY_THRESHOLD = 5;

    public function recordAccountFailure(SmtpAccount $account): void
    {
        $key = $this->consecutiveFailuresKey($account);
        Cache::add($key, 0, now()->addHour());
        $failures = Cache::increment($key);

        $account->health_status = $failures >= self::UNHEALTHY_THRESHOLD
            ? SmtpHealthStatus::Unhealthy->value
            : SmtpHealthStatus::Degraded->value;
        $account->save();
    }

    /**
     * docs/19-throttling.md §19.5 — never an instant jump back to fully
     * healthy; a single success clears the failure streak and, if the
     * account was only `degraded` (not yet `unhealthy`), restores it.
     */
    public function recordAccountSuccess(SmtpAccount $account): void
    {
        Cache::forget($this->consecutiveFailuresKey($account));

        if ($account->health_status === SmtpHealthStatus::Degraded->value) {
            $account->health_status = SmtpHealthStatus::Healthy->value;
            $account->save();
        }
    }

    private function consecutiveFailuresKey(SmtpAccount $account): string
    {
        return "smtp:{$account->id}:consecutive_failures";
    }
}
