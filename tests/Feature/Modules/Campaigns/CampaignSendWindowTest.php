<?php

namespace Tests\Feature\Modules\Campaigns;

use App\Modules\Campaigns\Enums\CampaignStatus;
use App\Modules\Campaigns\Jobs\DispatchCampaignJob;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Campaigns\Support\SendWindow;
use App\Modules\DeliveryEngine\Jobs\SendEmailJob;
use App\Modules\Tracking\Models\Message;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Tests\Feature\Modules\Campaigns\Concerns\BuildsCampaignFixtures;
use Tests\TestCase;

/**
 * gap-approval — « Plage horaire d'envoi » (`business_hours_*`): messages are
 * only released inside the window, evaluated in the campaign's timezone
 * (Africa/Algiers, UTC+1 all year).
 */
class CampaignSendWindowTest extends TestCase
{
    use BuildsCampaignFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function windowCampaign(array $attributes = []): Campaign
    {
        $campaign = $this->campaignWithAudience($this->operator(), ['lists' => [$this->listWith($this->recipients(3))->id]]);
        $campaign->update([
            'status' => CampaignStatus::Running->value,
            'timezone' => 'Africa/Algiers',
            'business_hours_only' => true,
            'business_hours_start' => '08:00',
            'business_hours_end' => '17:00',
            'business_days' => [1, 2, 3, 4, 5],
            ...$attributes,
        ]);

        return $campaign->fresh();
    }

    private function runJob(Campaign $campaign, ?string $scheduledFor = null): void
    {
        $this->app->call([new DispatchCampaignJob($campaign->id, $scheduledFor), 'handle']);
    }

    private function at(string $localTime): void
    {
        $this->travelTo(Carbon::parse($localTime, 'Africa/Algiers'));
    }

    public function test_nothing_is_released_outside_the_window_and_the_job_waits_for_the_next_opening(): void
    {
        Bus::fake();
        $campaign = $this->windowCampaign();

        $this->at('2026-10-24 10:00'); // Saturday
        $this->runJob($campaign);

        $this->assertSame(0, Message::query()->where('campaign_id', $campaign->id)->count());
        $this->assertSame(CampaignStatus::Running->value, $campaign->fresh()->status);
        Bus::assertNotDispatched(SendEmailJob::class);
        Bus::assertDispatched(
            DispatchCampaignJob::class,
            fn (DispatchCampaignJob $job) => $job->delay instanceof Carbon
                && $job->delay->equalTo(Carbon::parse('2026-10-26 08:00', 'Africa/Algiers')), // Monday
        );
    }

    public function test_after_closing_time_the_next_opening_is_the_next_allowed_day(): void
    {
        Bus::fake();
        $campaign = $this->windowCampaign();

        $this->at('2026-10-22 18:30'); // Thursday evening
        $this->runJob($campaign);

        Bus::assertDispatched(
            DispatchCampaignJob::class,
            fn (DispatchCampaignJob $job) => $job->delay->equalTo(Carbon::parse('2026-10-23 08:00', 'Africa/Algiers')),
        );
        $this->assertSame(0, Message::query()->where('campaign_id', $campaign->id)->count());
    }

    public function test_messages_are_released_inside_the_window(): void
    {
        Bus::fake();
        $campaign = $this->windowCampaign();

        $this->at('2026-10-22 09:15'); // Thursday
        $this->runJob($campaign);

        $this->assertSame(3, Message::query()->where('campaign_id', $campaign->id)->count());
        Bus::assertDispatched(SendEmailJob::class, 3);
        $this->assertSame(CampaignStatus::Completed->value, $campaign->fresh()->status);
    }

    public function test_the_window_is_evaluated_in_the_campaign_timezone(): void
    {
        Bus::fake();
        $campaign = $this->windowCampaign();

        // 16:30 UTC is 17:30 in Algiers: closed, although 16:30 would be open.
        $this->travelTo(Carbon::parse('2026-10-22 16:30', 'UTC'));
        $this->runJob($campaign);
        Bus::assertNotDispatched(SendEmailJob::class);

        // 15:59 UTC is 16:59 in Algiers: open.
        $this->travelTo(Carbon::parse('2026-10-22 15:59', 'UTC'));
        $this->runJob($campaign);
        Bus::assertDispatched(SendEmailJob::class, 3);
    }

    public function test_a_scheduled_campaign_stays_scheduled_until_the_window_opens(): void
    {
        Bus::fake();
        $campaign = $this->windowCampaign(['status' => CampaignStatus::Scheduled->value, 'scheduled_at' => Carbon::parse('2026-10-24 10:00', 'Africa/Algiers')]);

        $this->at('2026-10-24 10:00'); // Saturday: due, but closed
        $this->runJob($campaign, $campaign->scheduled_at->toIso8601String());

        $this->assertSame(CampaignStatus::Scheduled->value, $campaign->fresh()->status);
        Bus::assertDispatched(
            DispatchCampaignJob::class,
            fn (DispatchCampaignJob $job) => $job->scheduledFor !== null && $job->delay->equalTo(Carbon::parse('2026-10-26 08:00', 'Africa/Algiers')),
        );

        // Monday 08:00: the re-enqueued job (same scheduledFor) now sends.
        $this->at('2026-10-26 08:00');
        $this->runJob($campaign, $campaign->scheduled_at->toIso8601String());
        $this->assertSame(CampaignStatus::Completed->value, $campaign->fresh()->status);
        Bus::assertDispatched(SendEmailJob::class, 3);
    }

    public function test_a_campaign_without_window_is_never_deferred(): void
    {
        Bus::fake();
        $campaign = $this->windowCampaign(['business_hours_only' => false]);

        $this->at('2026-10-24 03:00');
        $this->runJob($campaign);

        Bus::assertDispatched(SendEmailJob::class, 3);
    }

    public function test_a_finished_campaign_can_complete_outside_the_window(): void
    {
        Bus::fake();
        $campaign = $this->windowCampaign();

        $this->at('2026-10-22 09:00');
        $this->runJob($campaign);
        $this->assertSame(CampaignStatus::Completed->value, $campaign->fresh()->status);

        // Running + materialised + nothing pending (e.g. resumed after a crash): no pointless deferral.
        $campaign->update(['status' => CampaignStatus::Running->value]);
        $this->at('2026-10-24 23:00');
        $this->assertNull(app(\App\Modules\Campaigns\Services\CampaignService::class)->sendWindowDeferral($campaign->fresh()));
    }

    public function test_window_math_handles_overnight_windows_days_and_defaults(): void
    {
        $overnight = new SendWindow([1, 2, 3, 4, 5], 22 * 60, 6 * 60, 'UTC');

        $this->assertTrue($overnight->contains(Carbon::parse('2026-10-23 23:00', 'UTC'))); // Friday night
        $this->assertTrue($overnight->contains(Carbon::parse('2026-10-24 05:00', 'UTC'))); // still Friday's window
        $this->assertFalse($overnight->contains(Carbon::parse('2026-10-24 23:00', 'UTC'))); // Saturday night
        $this->assertFalse($overnight->contains(Carbon::parse('2026-10-25 05:00', 'UTC'))); // Saturday's window is closed
        $this->assertTrue($overnight->nextOpening(Carbon::parse('2026-10-24 12:00', 'UTC'))->equalTo(Carbon::parse('2026-10-26 22:00', 'UTC')));

        $everyDay = new SendWindow([], 9 * 60, 12 * 60, 'UTC');
        $this->assertTrue($everyDay->contains(Carbon::parse('2026-10-25 09:00', 'UTC')));
        $this->assertFalse($everyDay->contains(Carbon::parse('2026-10-25 12:00', 'UTC')));
        $this->assertTrue($everyDay->nextOpening(Carbon::parse('2026-10-25 12:00', 'UTC'))->equalTo(Carbon::parse('2026-10-26 09:00', 'UTC')));
    }

    public function test_send_window_from_the_api_drives_the_dispatch(): void
    {
        Bus::fake();
        $user = $this->manager();
        $campaign = $this->campaignWithAudience($user, ['lists' => [$this->listWith($this->recipients(2))->id]]);

        $this->actingAs($user)->patchJson("/api/v1/campaigns/{$campaign->uuid}", [
            'send_window' => ['days' => [1, 2, 3, 4, 5], 'start' => '08:00', 'end' => '17:00'],
        ])->assertOk()->assertJsonPath('data.send_window.start', '08:00');

        $campaign->update(['status' => CampaignStatus::Running->value, 'timezone' => 'Africa/Algiers']);

        $this->at('2026-10-25 12:00'); // Sunday
        $this->runJob($campaign->fresh());
        Bus::assertNotDispatched(SendEmailJob::class);

        $this->at('2026-10-26 12:00'); // Monday
        $this->runJob($campaign->fresh());
        Bus::assertDispatched(SendEmailJob::class, 2);
    }
}
