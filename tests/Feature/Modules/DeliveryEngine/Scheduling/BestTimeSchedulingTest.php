<?php

namespace Tests\Feature\Modules\DeliveryEngine\Scheduling;

use App\Domain\Enums\RoleName;
use App\Modules\Campaigns\Enums\CampaignStatus;
use App\Modules\Campaigns\Jobs\DispatchCampaignJob;
use App\Modules\Campaigns\Models\CampaignRecipient;
use App\Modules\Campaigns\Services\CampaignService;
use App\Modules\DeliveryEngine\Jobs\SendEmailJob;
use App\Modules\DeliveryEngine\Scheduling\Jobs\ComputeBestSendHoursJob;
use App\Modules\DeliveryEngine\Scheduling\Services\BestSendTimeService;
use App\Modules\Identity\Models\User;
use App\Modules\Recipients\Models\Recipient;
use App\Modules\Tracking\Models\Message;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Modules\Campaigns\Concerns\BuildsCampaignFixtures;
use Tests\Feature\Modules\DeliveryEngine\Scheduling\Concerns\SeedsOpenHistory;
use Tests\TestCase;

/**
 * docs/45 §45.4.2 `mode: best_time` end to end through CampaignService's
 * guarded dispatcher hook, the §45.7.3 preview endpoint and the Slot P
 * nightly precompute.
 */
class BestTimeSchedulingTest extends TestCase
{
    use BuildsCampaignFixtures, RefreshDatabase, SeedsOpenHistory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->freezeMonday();
    }

    public function test_best_time_campaign_releases_each_contact_at_its_best_hour(): void
    {
        $owner = $this->operator();
        $now = Recipient::factory()->create();       // best hour = current hour (10h local)
        $later = Recipient::factory()->create();     // best hour = 15h local
        $this->openAt($now, '2026-10-26 09:15:00');
        $this->openAt($later, '2026-10-27 14:10:00');
        $this->openAt($later, '2026-10-20 14:10:00');
        $campaign = $this->campaignWithAudience($owner, ['lists' => [$this->listWith([$now, $later])->id]]);

        Bus::fake();
        $service = app(CampaignService::class);
        $service->scheduleWithOptions($campaign, ['mode' => 'best_time']);

        $campaign->refresh();
        $this->assertTrue($campaign->send_time_optimized);
        $this->assertSame(CampaignStatus::Running->value, $campaign->status);
        Bus::assertDispatched(DispatchCampaignJob::class);

        // First cycle (what DispatchCampaignJob runs): materialise → plan → release due rows.
        $service->runDispatchCycle($campaign);

        $rows = CampaignRecipient::query()->where('campaign_id', $campaign->id)->get()->keyBy('recipient_id');
        $this->assertSame('2026-11-02 09:30:00', $rows[$now->id]->scheduled_for->utc()->format('Y-m-d H:i:s'));
        $this->assertSame('2026-11-02 14:00:00', $rows[$later->id]->scheduled_for->utc()->format('Y-m-d H:i:s'));
        $this->assertSame('queued', $rows[$now->id]->status);
        $this->assertSame('pending', $rows[$later->id]->status);
        Bus::assertDispatched(SendEmailJob::class, 1);
        Bus::assertDispatched(DispatchCampaignJob::class, fn (DispatchCampaignJob $job) => $job->delay !== null);

        $this->travelTo(Carbon::parse('2026-11-02 13:59:00', 'UTC'));
        $service->runDispatchCycle($campaign);
        Bus::assertDispatched(SendEmailJob::class, 1);

        $this->travelTo(Carbon::parse('2026-11-02 14:00:00', 'UTC'));
        $service->runDispatchCycle($campaign);
        Bus::assertDispatched(SendEmailJob::class, 2);
        $this->assertSame(2, Message::query()->where('campaign_id', $campaign->id)->count());
        $this->assertSame(CampaignStatus::Completed->value, $campaign->fresh()->status);
    }

    public function test_scheduled_best_time_window_starts_at_scheduled_at(): void
    {
        $owner = $this->operator();
        $recipient = Recipient::factory()->create();
        $this->openAt($recipient, '2026-10-27 06:10:00');           // 07h local
        $campaign = $this->campaignWithAudience($owner, ['lists' => [$this->listWith([$recipient])->id]]);

        Bus::fake();
        $service = app(CampaignService::class);
        $service->scheduleWithOptions($campaign, ['mode' => 'best_time', 'scheduled_at' => '2026-11-03T12:00:00+01:00']);
        $this->assertSame(CampaignStatus::Scheduled->value, $campaign->fresh()->status);

        $this->travelTo(Carbon::parse('2026-11-03 11:00:00', 'UTC'));
        $service->runDispatchCycle($campaign);

        // 07h already passed on the 3rd → the 4th at 07h local, < 24 h after 12h.
        $this->assertSame(
            '2026-11-04 06:00:00',
            CampaignRecipient::query()->where('campaign_id', $campaign->id)->first()->scheduled_for->utc()->format('Y-m-d H:i:s'),
        );
        Bus::assertNotDispatched(SendEmailJob::class);
    }

    public function test_preview_endpoint_returns_best_time_preview(): void
    {
        $owner = $this->operator();
        $recipient = Recipient::factory()->create();
        $this->openAt($recipient, '2026-10-27 14:10:00');
        $campaign = $this->campaignWithAudience($owner, ['lists' => [$this->listWith([$recipient, ...$this->recipients(1)])->id]]);

        $this->actingAs($owner)->getJson("/api/v1/campaigns/{$campaign->uuid}/best-time-preview")
            ->assertOk()
            ->assertExactJson(['data' => [
                'contacts_with_history' => 1,
                'contacts_fallback' => 1,
                'fallback_hour' => 15,
                'distribution' => [['hour' => 15, 'count' => 2]],
                'window_hours' => 24,
            ]]);
    }

    public function test_preview_endpoint_requires_campaigns_create(): void
    {
        $owner = $this->operator();
        $campaign = $this->campaignWithAudience($owner, ['lists' => [$this->listWith($this->recipients(1))->id]]);
        $viewer = User::factory()->withRole(RoleName::Viewer)->create();

        $this->actingAs($viewer)->getJson("/api/v1/campaigns/{$campaign->uuid}/best-time-preview")->assertForbidden();
    }

    public function test_nightly_job_precomputes_contact_and_account_hours(): void
    {
        $engaged = Recipient::factory()->create();
        $cold = Recipient::factory()->create();
        $lapsed = Recipient::factory()->create();
        DB::table('recipients')->where('id', $lapsed->id)->update(['best_send_hour' => 3]);
        $this->openAt($engaged, '2026-10-27 14:10:00');             // Tue 15h → 39
        $this->messageFor($cold, $this->pastCampaign());

        ComputeBestSendHoursJob::dispatchSync();

        $this->assertDatabaseHas('recipients', ['id' => $engaged->id, 'best_send_hour' => 39]);
        $this->assertNotNull(DB::table('recipients')->where('id', $engaged->id)->value('best_send_hour_computed_at'));
        $this->assertDatabaseHas('recipients', ['id' => $lapsed->id, 'best_send_hour' => null]);
        $this->assertNull(DB::table('recipients')->where('id', $cold->id)->value('best_send_hour_computed_at'));
        $this->assertSame(15, Cache::get(BestSendTimeService::ACCOUNT_HOUR_CACHE_KEY)['hour']);
    }

    public function test_nightly_job_is_scheduled(): void
    {
        $events = collect(app(Schedule::class)->events())
            ->filter(fn ($event) => str_contains((string) $event->description, 'ComputeBestSendHoursJob'));

        $this->assertCount(1, $events);
    }
}
