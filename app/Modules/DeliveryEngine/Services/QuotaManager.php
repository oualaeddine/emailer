<?php

namespace App\Modules\DeliveryEngine\Services;

use App\Modules\DeliveryEngine\Models\SmtpAccount;
use App\Modules\Settings\Services\SettingsService;
use Illuminate\Support\Facades\Cache;

/**
 * docs/19-throttling.md §19.2, §19.3 — real-time enforcement via atomic
 * counters (Redis in production, Laravel's `Cache` facade abstracts the
 * store so the same code runs against the `database` driver locally).
 * `quota_ledger` (the durable reconciliation record) is written by
 * {@see \App\Modules\DeliveryEngine\Jobs\SendEmailJob} on each attempt.
 */
class QuotaManager
{
    public function __construct(private readonly SettingsService $settings)
    {
    }

    /**
     * docs/19-throttling.md §19.3 — minute/hour/day quota check.
     * `$effectiveDailyQuota` lets the caller pass a warm-up-capped value
     * (docs/19-throttling.md §19.6 — "the lower of the two applies").
     */
    public function isWithinQuota(SmtpAccount $account, ?int $effectiveDailyQuota): bool
    {
        foreach ($this->windows($account, $effectiveDailyQuota) as $window => $limit) {
            if ($limit === null) {
                continue;
            }

            if ((int) Cache::get($this->quotaKey($account, $window), 0) >= $limit) {
                return false;
            }
        }

        return true;
    }

    /**
     * Atomically reserves one unit of quota against every window
     * (docs/19-throttling.md §19.3 — reservation happens before the send
     * attempt, and is NOT rolled back on send failure).
     */
    public function reserve(SmtpAccount $account): void
    {
        foreach (array_keys($this->windows($account, null)) as $window) {
            $this->incrementWithTtl($this->quotaKey($account, $window), $this->ttlFor($window));
        }
    }

    /**
     * docs/19-throttling.md §19.2 — per-domain throttling, caps sends to
     * the same recipient domain within a rolling minute bucket.
     */
    public function isDomainWithinThrottle(SmtpAccount $account, string $domain): bool
    {
        $limit = (int) $this->settings->get('throttling.per_domain_per_minute', 10);

        return (int) Cache::get($this->domainKey($account, $domain), 0) < $limit;
    }

    public function reserveDomainSlot(SmtpAccount $account, string $domain): void
    {
        $this->incrementWithTtl($this->domainKey($account, $domain), now()->addMinute());
    }

    /**
     * docs/17-delivery-engine.md §17.6 — `max_concurrent_connections`
     * enforced via a semaphore; rolled back on release (unlike quota
     * counters, a concurrency slot IS genuinely free again after use).
     */
    public function acquireConnectionSlot(SmtpAccount $account): bool
    {
        $key = $this->concurrencyKey($account);
        $current = (int) Cache::get($key, 0);

        if ($current >= max($account->max_concurrent_connections, 1)) {
            return false;
        }

        Cache::put($key, $current + 1, now()->addMinutes(10));

        return true;
    }

    public function releaseConnectionSlot(SmtpAccount $account): void
    {
        $key = $this->concurrencyKey($account);
        $current = (int) Cache::get($key, 0);

        if ($current <= 1) {
            Cache::forget($key);

            return;
        }

        Cache::put($key, $current - 1, now()->addMinutes(10));
    }

    /**
     * @return array<string, int|null>
     */
    private function windows(SmtpAccount $account, ?int $effectiveDailyQuota): array
    {
        return [
            'minute' => $account->minute_quota,
            'hour' => $account->hourly_quota,
            'day' => $effectiveDailyQuota ?? $account->daily_quota,
        ];
    }

    private function incrementWithTtl(string $key, \DateTimeInterface|\DateInterval|int $ttl): int
    {
        Cache::add($key, 0, $ttl);

        return Cache::increment($key);
    }

    private function ttlFor(string $window): \DateTimeInterface
    {
        return match ($window) {
            'minute' => now()->endOfMinute(),
            'hour' => now()->endOfHour(),
            'day' => now()->endOfDay(),
            default => now()->addMinute(),
        };
    }

    private function quotaKey(SmtpAccount $account, string $window): string
    {
        $windowStart = match ($window) {
            'minute' => now()->format('YmdHi'),
            'hour' => now()->format('YmdH'),
            'day' => now()->format('Ymd'),
            default => now()->format('YmdHi'),
        };

        return "smtp:{$account->id}:quota:{$window}:{$windowStart}";
    }

    private function domainKey(SmtpAccount $account, string $domain): string
    {
        return "smtp:{$account->id}:domain:".strtolower($domain).':'.now()->format('YmdHi');
    }

    private function concurrencyKey(SmtpAccount $account): string
    {
        return "smtp:{$account->id}:concurrency";
    }
}
