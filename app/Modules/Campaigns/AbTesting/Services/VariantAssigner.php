<?php

namespace App\Modules\Campaigns\AbTesting\Services;

use App\Modules\Campaigns\Enums\CampaignRecipientStatus;
use App\Modules\Campaigns\Models\AbTestVariant;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Campaigns\Models\CampaignRecipient;

/**
 * docs/43 §43.6.1/§43.6.2 — deterministic A/B sample split, run by the
 * audience resolver right after `campaign_recipients` is materialised.
 *
 * Pending rows are ordered by a per-campaign hash of the recipient id; the
 * first {@see sampleSize()} rows form the test sample and alternate A, B, A…
 * (so A and B differ by at most one). Every other row keeps
 * `variant_id = null`: the remainder, sent with the winner's content once
 * {@see AbTestService::pickWinner()} ran. The same campaign + audience always
 * yields the same split, and an already split audience is never re-split.
 */
class VariantAssigner
{
    private const CHUNK = 1000;

    /**
     * Sample = `round(total × percent / 100)`, at least 2 (one per variant)
     * when the audience allows it, never more than the audience.
     */
    public static function sampleSize(int $total, int $percent): int
    {
        if ($total <= 0) {
            return 0;
        }

        $size = (int) round($total * max(0, min(100, $percent)) / 100);

        return min($total, max(2, $size));
    }

    /**
     * Stable ordering key of a recipient inside a campaign's audience.
     */
    public static function hashKey(Campaign $campaign, int $recipientId): string
    {
        return md5($campaign->uuid.':'.$recipientId);
    }

    public function assign(Campaign $campaign): void
    {
        if (! $campaign->ab_test_enabled || $campaign->winner_variant_id !== null) {
            return;
        }

        $variants = $campaign->abTestVariants()->get()->keyBy('label');
        $a = $variants->get(AbTestVariant::LABEL_A);
        $b = $variants->get(AbTestVariant::LABEL_B);

        if ($a === null || $b === null) {
            return;
        }

        $rows = CampaignRecipient::query()->where('campaign_id', $campaign->id);

        if ((clone $rows)->whereNotNull('variant_id')->exists()) {
            return; // already split (resumed / re-materialised audience)
        }

        $ids = (clone $rows)->where('status', CampaignRecipientStatus::Pending->value)
            ->pluck('recipient_id')->map(fn ($id): int => (int) $id)->all();

        $keys = [];
        foreach ($ids as $id) {
            $keys[$id] = self::hashKey($campaign, $id);
        }
        uksort($keys, fn (int $x, int $y): int => [$keys[$x], $x] <=> [$keys[$y], $y]);

        $percent = (int) ($campaign->ab_test_config['sample_percent'] ?? AbTestService::DEFAULT_SAMPLE_PERCENT);
        $sample = array_slice(array_keys($keys), 0, self::sampleSize(count($ids), $percent));

        $groups = [$a->id => [], $b->id => []];
        foreach ($sample as $index => $recipientId) {
            $groups[$index % 2 === 0 ? $a->id : $b->id][] = $recipientId;
        }

        foreach ($groups as $variantId => $recipientIds) {
            foreach (array_chunk($recipientIds, self::CHUNK) as $chunk) {
                CampaignRecipient::query()->where('campaign_id', $campaign->id)
                    ->whereIn('recipient_id', $chunk)
                    ->update(['variant_id' => $variantId]);
            }
        }

        $a->update(['sample_size' => count($groups[$a->id])]);
        $b->update(['sample_size' => count($groups[$b->id])]);
    }
}
