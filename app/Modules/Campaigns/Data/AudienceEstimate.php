<?php

namespace App\Modules\Campaigns\Data;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Carbon;

/**
 * docs/45-brevo-api-contracts.md §45.4.2 `AudienceEstimate`.
 *
 * @implements Arrayable<string, mixed>
 */
final class AudienceEstimate implements Arrayable
{
    /**
     * @param  list<array{type: string, mode: string, id: string, name: string, count: int}>  $byTarget
     */
    public function __construct(
        public readonly int $included,
        public readonly int $excluded,
        public readonly int $suppressed,
        public readonly int $duplicates,
        public readonly int $total,
        public readonly int $unengaged,
        public readonly float $unengagedPercent,
        public readonly bool $unengagedWarning,
        public readonly array $byTarget,
        public readonly Carbon $computedAt,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'included' => $this->included,
            'excluded' => $this->excluded,
            'suppressed' => $this->suppressed,
            'duplicates' => $this->duplicates,
            'total' => $this->total,
            'unengaged' => $this->unengaged,
            'unengaged_percent' => $this->unengagedPercent,
            'unengaged_warning' => $this->unengagedWarning,
            'by_target' => $this->byTarget,
            'computed_at' => $this->computedAt->toIso8601ZuluString(),
        ];
    }
}
