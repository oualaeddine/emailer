<?php

namespace App\Modules\Campaigns\Enums;

/**
 * docs/15-campaign-management.md §15.3 — Scheduling & Recurrence, extended
 * with the Brevo delivery options of docs/45 §45.4.2 (`best_time`,
 * `batches`). The stored value `immediate` is exposed by the API as `now`.
 */
enum CampaignSendMode: string
{
    case Immediate = 'immediate';
    case Scheduled = 'scheduled';
    case Recurring = 'recurring';
    case BestTime = 'best_time';
    case Batches = 'batches';

    /**
     * API (`CampaignV2.send_mode`) value.
     */
    public function apiValue(): string
    {
        return $this === self::Immediate ? 'now' : $this->value;
    }

    public static function fromApi(string $value): self
    {
        return $value === 'now' ? self::Immediate : self::from($value);
    }
}
