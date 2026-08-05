<?php

namespace App\Modules\Campaigns\Enums;

/**
 * docs/15-campaign-management.md §15.1 — Campaign Lifecycle (State Machine).
 *
 * ```
 * draft --> scheduled   (schedule set)
 * draft --> running     (send immediately)
 * scheduled --> running (scheduled time reached / dispatcher fires)
 * running --> paused    (user pauses)
 * paused --> running    (user resumes)
 * running --> completed (all recipients processed)
 * draft --> cancelled
 * scheduled --> cancelled
 * paused --> cancelled
 * ```
 *
 * Transitions are enforced exclusively through {@see \App\Modules\Campaigns\Services\CampaignService}
 * (never direct model saves) per §15.1.
 */
enum CampaignStatus: string
{
    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case Running = 'running';
    case Paused = 'paused';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    /**
     * @return list<self>
     */
    public function allowedNextStatuses(): array
    {
        return match ($this) {
            self::Draft => [self::Scheduled, self::Running, self::Cancelled],
            self::Scheduled => [self::Running, self::Cancelled],
            self::Running => [self::Paused, self::Completed],
            self::Paused => [self::Running, self::Cancelled],
            self::Completed, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedNextStatuses(), true);
    }
}
