<?php

namespace App\Modules\Tracking\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * `recipients.engagement_score` (0–100, doc 45 §45.8 Slot E), refreshed by
 * the tracker on every counted open / click:
 * - 50 points × open ratio (opened / delivered, falling back to sent);
 * - 30 points × click ratio (clicked / delivered, falling back to sent);
 * - 20 points of recency, decaying linearly to 0 over 90 days since the
 *   last open or click.
 */
final class EngagementScore
{
    private const RECENCY_DAYS = 90;

    public static function compute(
        int $sent,
        int $delivered,
        int $opened,
        int $clicked,
        ?CarbonInterface $lastActivityAt,
        ?CarbonInterface $now = null,
    ): int {
        $base = max($delivered, $sent, $opened, 1);
        $openRatio = min(1.0, $opened / $base);
        $clickRatio = min(1.0, $clicked / $base);

        $recency = 0.0;
        if ($lastActivityAt !== null) {
            $days = max(0.0, Carbon::instance($lastActivityAt)->diffInSeconds($now ?? now(), false) / 86400);
            $recency = max(0.0, 1 - $days / self::RECENCY_DAYS);
        }

        return (int) max(0, min(100, round(50 * $openRatio + 30 * $clickRatio + 20 * $recency)));
    }
}
