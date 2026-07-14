<?php

namespace App\Modules\DeliveryEngine\Services;

use App\Domain\Enums\SmtpHealthStatus;
use App\Modules\DeliveryEngine\Models\SmtpAccount;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * docs/17-delivery-engine.md §17.5 — Rotation Engine selection algorithm.
 */
class RotationEngine
{
    public function __construct(
        private readonly QuotaManager $quota,
        private readonly WarmupManager $warmup,
    ) {
    }

    /**
     * @param  list<int>  $excludeAccountIds  accounts already tried and failed for this message (Failover Engine)
     */
    public function selectAccount(?SmtpAccount $pinned = null, array $excludeAccountIds = []): ?SmtpAccount
    {
        if ($pinned !== null) {
            return $this->isEligible($pinned) ? $pinned : null;
        }

        $candidates = SmtpAccount::query()
            ->where('is_active', true)
            ->whereIn('health_status', [SmtpHealthStatus::Healthy->value, SmtpHealthStatus::Degraded->value])
            ->when($excludeAccountIds !== [], fn ($q) => $q->whereNotIn('id', $excludeAccountIds))
            ->orderBy('priority')
            ->get()
            ->filter(fn (SmtpAccount $account) => $this->isEligible($account))
            ->values();

        if ($candidates->isEmpty()) {
            return null;
        }

        $topPriority = $candidates->first()->priority;
        $tier = $candidates->where('priority', $topPriority)->values();

        return $tier->count() === 1 ? $tier->first() : $this->weightedPick($tier);
    }

    private function isEligible(SmtpAccount $account): bool
    {
        return $account->isEligibleForRotation()
            && $this->quota->isWithinQuota($account, $this->warmup->effectiveDailyQuota($account));
    }

    /**
     * docs/17-delivery-engine.md §17.5 — weighted round-robin keyed by
     * `rotation_weight`, state kept in a rolling counter per priority tier.
     *
     * @param  Collection<int, SmtpAccount>  $tier
     */
    private function weightedPick(Collection $tier): SmtpAccount
    {
        $tierKey = 'smtp:rotation:tier:'.$tier->pluck('id')->sort()->implode('-');
        $totalWeight = max($tier->sum('rotation_weight'), 1);

        Cache::add($tierKey, 0, now()->addDay());
        $position = Cache::increment($tierKey) % $totalWeight;

        $cumulative = 0;

        foreach ($tier as $account) {
            $cumulative += $account->rotation_weight;

            if ($position < $cumulative) {
                return $account;
            }
        }

        return $tier->last();
    }
}
