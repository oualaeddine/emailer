<?php

namespace App\Modules\Automations\Listeners;

use App\Modules\Automations\Enums\AutomationTriggerType;
use App\Modules\Automations\Services\TriggerDispatcher;
use App\Modules\Recipients\Attributes\Contracts\ContactAttributeService;
use App\Modules\Recipients\Models\Recipient;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Trigger `date_anniversary` — { attribute, offset_days, time "HH:MM" }.
 * Called by every tick; once a day per automation (when the account-time
 * `time` has come) it scans the contacts whose date attribute's
 * anniversary is today − offset_days (offset −7 = a week before, +1 = the
 * day after; 29 February matches 28 February in common years) and starts
 * their runs. Account timezone: docs/45 §45.1 default `Africa/Algiers`.
 */
class DateAnniversaryScheduler
{
    public const TIMEZONE = 'Africa/Algiers';

    public function __construct(
        private readonly TriggerDispatcher $dispatcher,
        private readonly ContactAttributeService $attributes,
    ) {}

    /** @return int contacts dispatched */
    public function scan(?Carbon $now = null): int
    {
        $local = ($now ?? now())->copy()->setTimezone(self::TIMEZONE);
        $dispatched = 0;

        foreach ($this->dispatcher->activeAutomations(AutomationTriggerType::DateAnniversary) as $automation) {
            $config = $automation->trigger_config;

            if ($local->format('H:i') < $this->time($config['time'] ?? null)) {
                continue;
            }

            if (! Cache::add("automations:anniversary:{$automation->id}:{$local->toDateString()}", true, now()->addDays(2))) {
                continue;
            }

            $target = $local->copy()->startOfDay()->subDays((int) ($config['offset_days'] ?? 0));
            $ids = $this->matchingRecipientIds(strtoupper((string) ($config['attribute'] ?? '')), $target);

            $this->dispatcher->dispatch([$automation], $ids, [
                'trigger' => AutomationTriggerType::DateAnniversary->value,
                'date' => $target->toDateString(),
            ]);
            $dispatched += count($ids);
        }

        return $dispatched;
    }

    /** @return list<int> */
    public function matchingRecipientIds(string $attribute, Carbon $target): array
    {
        $definition = $this->attributes->definitions()->get($attribute);
        if ($definition === null) {
            return [];
        }

        $column = $definition->storage === 'column' && $definition->column ? $definition->column : null;
        $query = Recipient::query()->select(['id', 'custom_fields', ...($column ? [$column] : [])]);
        $column ? $query->whereNotNull($column) : $query->whereNotNull('custom_fields->'.$attribute);

        $ids = [];
        $query->chunkById(1000, function ($recipients) use (&$ids, $column, $attribute, $target): void {
            foreach ($recipients as $recipient) {
                $value = $column ? $recipient->getAttribute($column) : ($recipient->custom_fields[$attribute] ?? null);
                if (self::isAnniversary($value, $target)) {
                    $ids[] = $recipient->id;
                }
            }
        });

        return $ids;
    }

    public static function isAnniversary(mixed $value, Carbon $target): bool
    {
        if ($value === null || $value === '') {
            return false;
        }

        try {
            $date = $value instanceof \DateTimeInterface ? Carbon::instance($value) : Carbon::parse((string) $value);
        } catch (Throwable) {
            return false;
        }

        if ($date->format('Y-m-d') > $target->format('Y-m-d')) {
            return false;
        }

        if ($date->month === 2 && $date->day === 29 && ! $target->isLeapYear()) {
            return $target->month === 2 && $target->day === 28;
        }

        return $date->month === $target->month && $date->day === $target->day;
    }

    private function time(mixed $time): string
    {
        return is_string($time) && preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $time) === 1 ? $time : '09:00';
    }
}
