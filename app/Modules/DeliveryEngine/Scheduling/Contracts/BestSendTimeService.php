<?php

namespace App\Modules\DeliveryEngine\Scheduling\Contracts;

use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Recipients\Models\Recipient;

/**
 * docs/43-brevo-parity-plan.md §43.6.4 / docs/45-brevo-api-contracts.md
 * §45.7.3, §45.9 — WP-81 "Envoyer au meilleur moment".
 *
 * Hours of week are 0–167 with Monday 00h = 0, in the account timezone
 * ({@see self::ACCOUNT_TIMEZONE}); hours of day are 0–23 in the same zone.
 *
 * Bound in AppServiceProvider; `CampaignService::runDispatchCycle()` calls
 * {@see plan()} right after the audience is materialised when the campaign
 * has `send_time_optimized`. Planning only writes
 * `campaign_recipients.scheduled_for`: the dispatcher releases due rows and
 * the throttling engine (QuotaManager / RotationEngine) still applies.
 */
interface BestSendTimeService
{
    /** Opens of the contact's last N campaigns are analysed. */
    public const HISTORY_CAMPAIGNS = 10;

    /** Account-wide fallback looks at the opens of the last N days. */
    public const ACCOUNT_LOOKBACK_DAYS = 90;

    /** Every message is released within this many hours of the send time. */
    public const WINDOW_HOURS = 24;

    public const ACCOUNT_TIMEZONE = 'Africa/Algiers';

    /**
     * Writes `campaign_recipients.scheduled_for` for every `pending` row:
     * the next occurrence (within {@see WINDOW_HOURS} of the send time) of
     * the contact's best hour, else of the account best hour, else the send
     * time itself. Send time = future `scheduled_at`, otherwise now.
     */
    public function plan(Campaign $campaign): void;

    /**
     * The contact's hour of week (0–167) with the most opens over their
     * last {@see HISTORY_CAMPAIGNS} campaigns; null without any open.
     */
    public function bestHourFor(Recipient $recipient): ?int;

    /**
     * Account-wide hour of day (0–23) with the most opens over the last
     * {@see ACCOUNT_LOOKBACK_DAYS} days; null without any open.
     */
    public function accountBestHour(): ?int;

    /**
     * `BestTimePreview` (docs/45 §45.7.3): `contacts_with_history`,
     * `contacts_fallback`, `fallback_hour` (account best hour, or the send
     * time's hour when the account has no history), `distribution` (planned
     * release hour of day → contacts, ascending hour), `window_hours`.
     *
     * @return array{contacts_with_history: int, contacts_fallback: int, fallback_hour: int, distribution: list<array{hour: int, count: int}>, window_hours: int}
     */
    public function explain(Campaign $campaign): array;
}
