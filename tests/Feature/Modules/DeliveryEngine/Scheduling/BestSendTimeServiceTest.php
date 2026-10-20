<?php

namespace Tests\Feature\Modules\DeliveryEngine\Scheduling;

use App\Modules\Campaigns\Contracts\AudienceResolver;
use App\Modules\Campaigns\Models\CampaignRecipient;
use App\Modules\DeliveryEngine\Scheduling\Contracts\BestSendTimeService as BestSendTimeContract;
use App\Modules\DeliveryEngine\Scheduling\Services\BestSendTimeService;
use App\Modules\Recipients\Models\Recipient;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Modules\Campaigns\Concerns\BuildsCampaignFixtures;
use Tests\Feature\Modules\DeliveryEngine\Scheduling\Concerns\SeedsOpenHistory;
use Tests\TestCase;

/**
 * docs/43 §43.6.4 / docs/45 §45.7.3 — WP-81 best hour per contact,
 * fallbacks, 24 h window and planning of `campaign_recipients.scheduled_for`.
 */
class BestSendTimeServiceTest extends TestCase
{
    use BuildsCampaignFixtures, RefreshDatabase, SeedsOpenHistory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->freezeMonday();
    }

    private function service(): BestSendTimeService
    {
        return app(BestSendTimeService::class);
    }

    public function test_best_hour_is_the_hour_of_week_with_most_opens(): void
    {
        $recipient = Recipient::factory()->create();
        $this->openAt($recipient, '2026-10-27 14:10:00');          // Tue 15h → 39
        $this->openAt($recipient, '2026-10-20 14:40:00');          // Tue 15h → 39
        $this->openAt($recipient, '2026-10-28 08:00:00');          // Wed 09h → 57

        $this->assertSame(39, $this->service()->bestHourFor($recipient));
    }

    public function test_only_the_last_ten_campaigns_and_real_opens_count(): void
    {
        $recipient = Recipient::factory()->create();

        // Oldest campaign: 3 opens Mon 08h — outside the last 10 campaigns.
        $old = $this->openAt($recipient, '2026-09-07 07:05:00');
        $this->openAt($recipient, '2026-09-07 07:10:00', $old);
        $this->openAt($recipient, '2026-09-07 07:20:00', $old);

        // 10 newer campaigns: two opens Tue 15h, Apple MPP proxy opens ignored.
        $newer = [];
        for ($i = 0; $i < 10; $i++) {
            $newer[] = $campaign = $this->pastCampaign();
            $this->messageFor($recipient, $campaign);
        }
        $this->openAt($recipient, '2026-10-20 14:10:00', $newer[3]);
        $this->openAt($recipient, '2026-10-27 14:10:00', $newer[7]);
        foreach (range(1, 4) as $minute) {
            $this->openAt($recipient, "2026-10-26 07:0{$minute}:00", $newer[8], proxy: true);
        }

        $this->assertSame(39, $this->service()->bestHourFor($recipient));
    }

    public function test_contact_without_history_has_no_best_hour(): void
    {
        $recipient = Recipient::factory()->create();
        $this->messageFor($recipient, $this->pastCampaign());

        $this->assertNull($this->service()->bestHourFor($recipient));
    }

    public function test_account_best_hour_uses_the_last_90_days(): void
    {
        $this->assertNull($this->service()->refreshAccountBestHour());

        $someone = Recipient::factory()->create();
        foreach (range(1, 5) as $i) {
            $this->openAt($someone, '2026-07-20 04:0'.$i.':00');    // > 90 days ago, 05h
        }
        $this->openAt($someone, '2026-10-27 13:10:00');            // 14h
        $this->openAt($someone, '2026-10-28 13:20:00');            // 14h
        $this->openAt($someone, '2026-10-29 08:00:00');            // 09h

        $this->assertSame(14, $this->service()->refreshAccountBestHour());
        $this->assertSame(14, $this->service()->accountBestHour());
    }

    public function test_release_time_stays_within_the_24_hour_window(): void
    {
        $start = Carbon::parse(self::NOW_UTC, 'UTC');               // Mon 10:30 local
        $service = $this->service();

        $this->assertSame('2026-11-02 14:00:00', $service->releaseAt(15, $start)->format('Y-m-d H:i:s'));
        // Hour already passed today → tomorrow.
        $this->assertSame('2026-11-03 08:00:00', $service->releaseAt(9, $start)->format('Y-m-d H:i:s'));
        // Inside the best hour → leaves at the send time.
        $this->assertSame('2026-11-02 09:30:00', $service->releaseAt(10, $start)->format('Y-m-d H:i:s'));

        foreach (range(0, 23) as $hour) {
            $release = $service->releaseAt($hour, $start);
            $this->assertTrue($release->gte($start));
            $this->assertTrue($release->lt($start->copy()->addHours(24)), "hour {$hour}");
        }
    }

    public function test_plan_writes_scheduled_for_for_every_pending_row(): void
    {
        $owner = $this->operator();
        $engaged = Recipient::factory()->create();
        $cold = Recipient::factory()->create();
        $alreadyQueued = Recipient::factory()->create();
        $this->openAt($engaged, '2026-10-27 14:10:00');            // Tue 15h

        // Account-wide history dominated by another contact at 09h local.
        $other = Recipient::factory()->create();
        foreach (range(1, 3) as $day) {
            $this->openAt($other, "2026-10-2{$day} 08:00:00");
        }

        $campaign = $this->campaignWithAudience($owner, ['lists' => [$this->listWith([$engaged, $cold, $alreadyQueued])->id]]);
        app(AudienceResolver::class)->resolve($campaign);
        CampaignRecipient::query()->where('campaign_id', $campaign->id)->where('recipient_id', $alreadyQueued->id)
            ->update(['status' => 'queued']);

        $this->service()->plan($campaign);

        $this->assertSame('2026-11-02 14:00:00', $this->scheduledFor($campaign->id, $engaged->id));
        $this->assertSame('2026-11-03 08:00:00', $this->scheduledFor($campaign->id, $cold->id));
        $this->assertNull($this->scheduledFor($campaign->id, $alreadyQueued->id));
    }

    public function test_plan_without_any_history_uses_the_scheduled_time(): void
    {
        $owner = $this->operator();
        $campaign = $this->campaignWithAudience($owner, ['lists' => [$this->listWith($this->recipients(3))->id]]);
        $campaign->update(['scheduled_at' => Carbon::parse('2026-11-05 12:00:00', 'UTC')]);
        app(AudienceResolver::class)->resolve($campaign);

        $this->service()->plan($campaign->fresh());

        $this->assertSame(
            ['2026-11-05 12:00:00'],
            CampaignRecipient::query()->where('campaign_id', $campaign->id)->pluck('scheduled_for')
                ->map(fn ($at) => $at->utc()->format('Y-m-d H:i:s'))->unique()->values()->all(),
        );
    }

    public function test_plan_uses_fresh_precomputed_hours_and_recomputes_stale_ones(): void
    {
        $owner = $this->operator();
        $fresh = Recipient::factory()->create();
        $stale = Recipient::factory()->create();
        DB::table('recipients')->where('id', $fresh->id)->update(['best_send_hour' => 24 + 20, 'best_send_hour_computed_at' => now()->subHours(3)]);
        DB::table('recipients')->where('id', $stale->id)->update(['best_send_hour' => 24 + 20, 'best_send_hour_computed_at' => now()->subDays(3)]);
        $this->openAt($stale, '2026-10-27 11:00:00');              // Tue 12h

        $campaign = $this->campaignWithAudience($owner, ['lists' => [$this->listWith([$fresh, $stale])->id]]);
        app(AudienceResolver::class)->resolve($campaign);

        $this->service()->plan($campaign);

        $this->assertSame('2026-11-02 19:00:00', $this->scheduledFor($campaign->id, $fresh->id));
        $this->assertSame('2026-11-02 11:00:00', $this->scheduledFor($campaign->id, $stale->id));
    }

    public function test_explain_summarises_the_planned_distribution(): void
    {
        $owner = $this->operator();
        $a = Recipient::factory()->create();
        $b = Recipient::factory()->create();
        $cold = $this->recipients(2);
        $this->openAt($a, '2026-10-27 14:10:00');                  // 15h
        $this->openAt($a, '2026-10-20 14:10:00');                  // 15h
        $this->openAt($b, '2026-10-28 08:00:00');                  // 09h

        $campaign = $this->campaignWithAudience($owner, ['lists' => [$this->listWith([$a, $b, ...$cold])->id]]);

        // Account best hour = 15h (2 opens vs 1).
        $this->assertSame([
            'contacts_with_history' => 2,
            'contacts_fallback' => 2,
            'fallback_hour' => 15,
            'distribution' => [['hour' => 9, 'count' => 1], ['hour' => 15, 'count' => 3]],
            'window_hours' => 24,
        ], $this->service()->explain($campaign));
    }

    public function test_explain_without_history_falls_back_to_the_send_hour(): void
    {
        $owner = $this->operator();
        $campaign = $this->campaignWithAudience($owner, ['lists' => [$this->listWith($this->recipients(2))->id]]);

        $preview = $this->service()->explain($campaign);

        $this->assertSame(0, $preview['contacts_with_history']);
        $this->assertSame(2, $preview['contacts_fallback']);
        $this->assertSame(10, $preview['fallback_hour']);
        $this->assertSame([['hour' => 10, 'count' => 2]], $preview['distribution']);
    }

    public function test_contract_is_bound(): void
    {
        $this->assertInstanceOf(BestSendTimeService::class, app(BestSendTimeContract::class));
        $this->assertTrue(app()->bound('App\\Modules\\DeliveryEngine\\Scheduling\\Contracts\\BestSendTimeService'));
    }

    private function scheduledFor(int $campaignId, int $recipientId): ?string
    {
        $at = CampaignRecipient::query()->where('campaign_id', $campaignId)->where('recipient_id', $recipientId)->value('scheduled_for');

        return $at === null ? null : Carbon::parse($at, 'UTC')->utc()->format('Y-m-d H:i:s');
    }
}
