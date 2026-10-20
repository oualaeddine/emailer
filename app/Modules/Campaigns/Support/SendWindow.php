<?php

namespace App\Modules\Campaigns\Support;

use App\Modules\Campaigns\Models\Campaign;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * docs/15-campaign-management.md §15.5 business-hours sending — the
 * campaign's send window (`business_hours_only` + `business_hours_start` /
 * `business_hours_end` + `business_days`, 0 = Sunday … 6 = Saturday). Messages
 * are only released inside it; outside, {@see \App\Modules\Campaigns\Services\CampaignService::runDispatchCycle()}
 * re-enqueues the dispatch for {@see self::nextOpening()}.
 *
 * Evaluated in the campaign's timezone (`campaigns.timezone`, default
 * `Africa/Algiers`, else the app timezone). `[start, end)`; an `end` at or
 * before `start` is an overnight window (22:00–06:00). No days = every day.
 */
final class SendWindow
{
    private const NAMES = ['sun' => 0, 'mon' => 1, 'tue' => 2, 'wed' => 3, 'thu' => 4, 'fri' => 5, 'sat' => 6];

    /**
     * @param  list<int>  $days
     */
    public function __construct(
        private readonly array $days,
        private readonly int $startMinute,
        private readonly int $endMinute,
        private readonly string $timezone,
    ) {}

    /**
     * `null` when the campaign has no window (or an unusable one).
     */
    public static function forCampaign(Campaign $campaign): ?self
    {
        if (! $campaign->business_hours_only) {
            return null;
        }

        $days = [];
        foreach ((array) ($campaign->business_days ?? []) as $day) {
            if (is_numeric($day)) {
                $days[] = (int) $day % 7;
            } elseif (isset(self::NAMES[strtolower(substr((string) $day, 0, 3))])) {
                $days[] = self::NAMES[strtolower(substr((string) $day, 0, 3))];
            }
        }

        $timezone = (string) ($campaign->timezone ?: config('app.timezone'));
        try {
            new \DateTimeZone($timezone);
        } catch (Throwable) {
            $timezone = (string) config('app.timezone');
        }

        return new self(
            array_values(array_unique($days)),
            self::minutes($campaign->business_hours_start, 0),
            self::minutes($campaign->business_hours_end, 24 * 60),
            $timezone,
        );
    }

    public function contains(CarbonInterface $at): bool
    {
        $local = CarbonImmutable::instance($at)->setTimezone($this->timezone);
        $minute = $local->hour * 60 + $local->minute;

        if ($this->startMinute < $this->endMinute) {
            return $this->allowsDay($local->dayOfWeek) && $minute >= $this->startMinute && $minute < $this->endMinute;
        }

        // Overnight: the late part belongs to the day it started on.
        return ($this->allowsDay($local->dayOfWeek) && $minute >= $this->startMinute)
            || ($this->allowsDay(($local->dayOfWeek + 6) % 7) && $minute < $this->endMinute);
    }

    /**
     * First instant at or after `$from` inside the window (`$from` itself when open).
     */
    public function nextOpening(CarbonInterface $from): Carbon
    {
        if ($this->contains($from)) {
            return Carbon::instance($from);
        }

        $local = CarbonImmutable::instance($from)->setTimezone($this->timezone);

        for ($offset = 0; $offset <= 7; $offset++) {
            $day = $local->addDays($offset)->startOfDay();
            if (! $this->allowsDay($day->dayOfWeek)) {
                continue;
            }

            $opening = $day->addMinutes($this->startMinute);
            if ($opening->greaterThan($local)) {
                return Carbon::instance($opening);
            }
        }

        // No day selected can open (cannot happen: days are never empty-and-closed) — retry in an hour.
        return Carbon::instance($from)->addHour();
    }

    private function allowsDay(int $dayOfWeek): bool
    {
        return $this->days === [] || in_array($dayOfWeek, $this->days, true);
    }

    private static function minutes(mixed $time, int $default): int
    {
        if (! is_string($time) || ! preg_match('/^(\d{1,2}):(\d{2})/', $time, $m)) {
            return $default;
        }

        return min(24 * 60, ((int) $m[1]) * 60 + (int) $m[2]);
    }
}
