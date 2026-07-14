<?php

namespace App\Modules\DeliveryEngine\Services;

use App\Modules\Settings\Services\SettingsService;

/**
 * docs/17-delivery-engine.md §17.7, docs/19-throttling.md §19.7 — Retry
 * Engine: Settings-driven global policy (base delay, multiplier, max
 * attempts, max delay cap, permanent-failure pattern matching).
 */
class RetryEngine
{
    public function __construct(private readonly SettingsService $settings)
    {
    }

    public function maxAttempts(): int
    {
        return (int) $this->settings->get('retry_policy.max_attempts', 5);
    }

    /**
     * Exponential backoff: base * multiplier^(attempt-1), capped.
     */
    public function delayForAttempt(int $attemptNumber): int
    {
        $base = (int) $this->settings->get('retry_policy.base_delay', 60);
        $multiplier = (float) $this->settings->get('retry_policy.multiplier', 2);
        $maxDelay = (int) $this->settings->get('retry_policy.max_delay', 3600);

        $delay = $base * ($multiplier ** max($attemptNumber - 1, 0));

        return (int) min($delay, $maxDelay);
    }

    /**
     * docs/19-throttling.md §19.7 — permanent-failure detection via
     * provider response code/keyword matching (configurable in Settings).
     */
    public function isPermanentFailure(string $providerResponse): bool
    {
        $patterns = $this->settings->get('retry_policy.permanent_patterns', self::defaultPermanentPatterns());

        foreach ($patterns as $pattern) {
            if (stripos($providerResponse, (string) $pattern) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    public static function defaultPermanentPatterns(): array
    {
        return ['550', '551', '553', 'mailbox does not exist', 'user unknown', 'no such user', 'does not exist'];
    }

    /**
     * docs/17-delivery-engine.md §17.7 — account-level connectivity
     * failures (auth error, connection refused) trigger the Failover
     * Engine rather than a plain retry of the same account.
     */
    public function isAccountLevelFailure(string $providerResponse): bool
    {
        $patterns = ['connection could not be established', 'connection refused', 'authentication', 'timed out', 'timeout', 'could not connect'];

        foreach ($patterns as $pattern) {
            if (stripos($providerResponse, $pattern) !== false) {
                return true;
            }
        }

        return false;
    }
}
