<?php

namespace App\Modules\DeliveryEngine\Models;

use App\Support\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;

/**
 * docs/04-database-design.md §4.9 — `warmup_schedules`.
 * `stages` is an ordered array of `{day: int, max_per_day: int}`
 * (docs/19-throttling.md §19.6).
 *
 * @property int $id
 * @property string $uuid
 * @property string $name
 * @property list<array{day: int, max_per_day: int}> $stages
 */
class WarmupSchedule extends Model
{
    use HasUuid;

    protected $fillable = ['name', 'stages'];

    protected function casts(): array
    {
        return ['stages' => 'array'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * docs/19-throttling.md §19.6 — current day's cap, clamped to the last
     * defined stage once the schedule is exhausted.
     */
    public function capForDay(int $day): int
    {
        $stages = collect($this->stages)->sortBy('day')->values();

        $applicable = $stages->last(fn (array $stage) => $stage['day'] <= $day);

        return $applicable['max_per_day'] ?? $stages->first()['max_per_day'];
    }
}
