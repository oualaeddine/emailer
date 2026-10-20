<?php

namespace Tests\Feature\Modules\DeliveryEngine\Scheduling\Concerns;

use App\Modules\Campaigns\Enums\CampaignStatus;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Recipients\Models\Recipient;
use App\Modules\Tracking\Models\Message;
use App\Modules\Tracking\Models\MessageEvent;
use Illuminate\Support\Carbon;

/**
 * WP-81 fixtures: past campaigns, their messages and open events.
 * Reference "now" is Monday 2026-11-02 09:30 UTC = 10:30 Africa/Algiers
 * (UTC+1, no DST): hour of week = (ISO weekday - 1) * 24 + local hour.
 */
trait SeedsOpenHistory
{
    protected const NOW_UTC = '2026-11-02 09:30:00';

    protected function freezeMonday(): void
    {
        $this->travelTo(Carbon::parse(self::NOW_UTC, 'UTC'));
    }

    protected function pastCampaign(): Campaign
    {
        return Campaign::factory()->status(CampaignStatus::Completed)->create();
    }

    protected function messageFor(Recipient $recipient, Campaign $campaign): Message
    {
        return Message::factory()->create(['campaign_id' => $campaign->id, 'recipient_id' => $recipient->id]);
    }

    /**
     * One open (UTC timestamp) of `$recipient` on a (new) past campaign.
     */
    protected function openAt(Recipient $recipient, string $utc, ?Campaign $campaign = null, bool $proxy = false): Campaign
    {
        $campaign ??= $this->pastCampaign();
        $message = Message::query()->where('campaign_id', $campaign->id)->where('recipient_id', $recipient->id)->first()
            ?? $this->messageFor($recipient, $campaign);

        MessageEvent::factory()->forMessage($message)->opened([
            'occurred_at' => Carbon::parse($utc, 'UTC'),
            'is_proxy_open' => $proxy,
        ])->create();

        return $campaign;
    }
}
