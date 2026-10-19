<?php

namespace App\Modules\Campaigns\Services\Checklist;

use Illuminate\Support\Carbon;

/**
 * docs/45-brevo-api-contracts.md §45.4.5 — `CampaignChecklist` value object.
 */
final class CampaignChecklist
{
    public const COMPLETE = 'complete';
    public const INCOMPLETE = 'incomplete';
    public const BLOCKING = 'blocking';

    /**
     * @param  list<ChecklistItem>  $items
     */
    public function __construct(
        public readonly string $campaignId,
        public readonly array $items,
        public readonly Carbon $checkedAt,
    ) {
    }

    /**
     * @return list<string>
     */
    public function blockingFailures(): array
    {
        $keys = [];
        foreach ($this->items as $item) {
            if ($item->status === ChecklistItem::ERROR) {
                $keys[] = $item->key;
            }
        }

        return $keys;
    }

    public function canSchedule(): bool
    {
        foreach ($this->items as $item) {
            if ($item->blocking && $item->status === ChecklistItem::ERROR) {
                return false;
            }
        }

        return true;
    }

    public function status(): string
    {
        if (! $this->canSchedule()) {
            return self::BLOCKING;
        }

        foreach ($this->items as $item) {
            if (in_array($item->status, [ChecklistItem::WARNING, ChecklistItem::ERROR, ChecklistItem::PENDING], true)) {
                return self::INCOMPLETE;
            }
        }

        return self::COMPLETE;
    }

    public function item(string $key): ?ChecklistItem
    {
        foreach ($this->items as $item) {
            if ($item->key === $key) {
                return $item;
            }
        }

        return null;
    }

    public function withItem(ChecklistItem $replacement): self
    {
        return new self(
            $this->campaignId,
            array_map(fn (ChecklistItem $i) => $i->key === $replacement->key ? $replacement : $i, $this->items),
            $this->checkedAt,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'campaign_id' => $this->campaignId,
            'can_schedule' => $this->canSchedule(),
            'status' => $this->status(),
            'items' => array_map(fn (ChecklistItem $i) => $i->toArray(), $this->items),
            'checked_at' => $this->checkedAt->utc()->format('Y-m-d\TH:i:s\Z'),
        ];
    }
}
