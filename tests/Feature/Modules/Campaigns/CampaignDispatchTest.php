<?php

namespace Tests\Feature\Modules\Campaigns;

use App\Modules\Campaigns\Contracts\AudienceResolver;
use App\Modules\Campaigns\Enums\CampaignStatus;
use App\Modules\Campaigns\Jobs\DispatchCampaignJob;
use App\Modules\Campaigns\Models\CampaignRecipient;
use App\Modules\Campaigns\Services\CampaignService;
use App\Modules\DeliveryEngine\Jobs\SendEmailJob;
use App\Modules\Recipients\Models\Recipient;
use App\Modules\Tracking\Models\Message;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\Feature\Modules\Campaigns\Concerns\BuildsCampaignFixtures;
use Tests\TestCase;

/**
 * docs/43 §43.6.1 / docs/45 §45.4.2 — DispatchCampaignJob iterates
 * `campaign_recipients`; batches mode releases rows with delays.
 */
class CampaignDispatchTest extends TestCase
{
    use BuildsCampaignFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_dispatch_materialises_then_sends_one_message_per_row(): void
    {
        $owner = $this->operator();
        $list = $this->listWith($this->recipients(3));
        $other = $this->listWith($this->recipients(2));
        $campaign = $this->campaignWithAudience($owner, ['lists' => [$list->id, $other->id]]);
        $campaign->update(['status' => CampaignStatus::Running->value]);

        Bus::fake();
        app(CampaignService::class)->runDispatchCycle($campaign);

        $this->assertSame(5, Message::query()->where('campaign_id', $campaign->id)->count());
        $this->assertSame(5, CampaignRecipient::query()->where('campaign_id', $campaign->id)->where('status', 'queued')->whereNotNull('message_id')->count());
        Bus::assertDispatched(SendEmailJob::class, 5);

        $campaign->refresh();
        $this->assertSame(CampaignStatus::Completed->value, $campaign->status);
        $this->assertNotNull($campaign->completed_at);
        $this->assertNotNull($campaign->sent_at);
    }

    public function test_batches_mode_releases_rows_with_delays(): void
    {
        $owner = $this->operator();
        $list = $this->listWith($this->recipients(5));
        $campaign = $this->campaignWithAudience($owner, ['lists' => [$list->id]]);
        $campaign->update([
            'status' => CampaignStatus::Running->value,
            'send_mode' => 'batches',
            'batch_size' => 2,
            'batch_interval_minutes' => 30,
        ]);

        Bus::fake();
        $service = app(CampaignService::class);

        $service->runDispatchCycle($campaign);
        Bus::assertDispatched(SendEmailJob::class, 2);
        Bus::assertDispatched(DispatchCampaignJob::class, fn (DispatchCampaignJob $job) => $job->delay !== null);
        $this->assertSame(3, CampaignRecipient::query()->where('campaign_id', $campaign->id)->where('status', 'pending')->count());

        // Too early: nothing more is released.
        $this->travel(10)->minutes();
        $service->runDispatchCycle($campaign);
        Bus::assertDispatched(SendEmailJob::class, 2);

        $this->travel(21)->minutes();
        $service->runDispatchCycle($campaign);
        Bus::assertDispatched(SendEmailJob::class, 4);

        $this->travel(30)->minutes();
        $service->runDispatchCycle($campaign);
        Bus::assertDispatched(SendEmailJob::class, 5);
        $this->assertSame(CampaignStatus::Completed->value, $campaign->fresh()->status);
    }

    public function test_recipient_suppressed_after_materialisation_is_skipped(): void
    {
        $owner = $this->operator();
        $recipients = $this->recipients(2);
        $list = $this->listWith($recipients);
        $campaign = $this->campaignWithAudience($owner, ['lists' => [$list->id]]);
        $campaign->update(['status' => CampaignStatus::Running->value]);
        app(AudienceResolver::class)->resolve($campaign);

        Recipient::query()->whereKey($recipients->first()->id)->update(['status' => 'suppressed']);

        Bus::fake();
        app(CampaignService::class)->runDispatchCycle($campaign);

        Bus::assertDispatched(SendEmailJob::class, 1);
        $this->assertDatabaseHas('campaign_recipients', [
            'campaign_id' => $campaign->id, 'recipient_id' => $recipients->first()->id,
            'status' => 'skipped', 'skip_reason' => 'suppressed',
        ]);
    }

    public function test_paused_campaign_does_not_release_rows(): void
    {
        $owner = $this->operator();
        $campaign = $this->campaignWithAudience($owner, ['lists' => [$this->listWith($this->recipients(2))->id]]);
        $campaign->update(['status' => CampaignStatus::Paused->value]);

        Bus::fake();
        app(CampaignService::class)->runDispatchCycle($campaign);

        Bus::assertNotDispatched(SendEmailJob::class);
        $this->assertSame(0, CampaignRecipient::query()->count());
    }

    public function test_stale_scheduled_job_is_ignored_after_unschedule(): void
    {
        $owner = $this->operator();
        $campaign = $this->campaignWithAudience($owner, ['lists' => [$this->listWith($this->recipients(1))->id]]);
        $service = app(CampaignService::class);

        Bus::fake();
        $service->schedule($campaign, now()->addHour());
        $job = null;
        Bus::assertDispatched(DispatchCampaignJob::class, function (DispatchCampaignJob $j) use (&$job) {
            $job = $j;

            return true;
        });

        $service->unschedule($campaign->fresh());
        $job->handle($service);

        $this->assertSame(CampaignStatus::Draft->value, $campaign->fresh()->status);
        Bus::assertNotDispatched(SendEmailJob::class);
    }

    public function test_test_messages_do_not_block_real_delivery(): void
    {
        $owner = $this->operator();
        $recipient = $this->recipients(1)->first();
        $campaign = $this->campaignWithAudience($owner, ['lists' => [$this->listWith([$recipient])->id]]);

        Bus::fake();
        app(CampaignService::class)->testSend($campaign, [$recipient->email], null, $owner);
        $campaign->update(['status' => CampaignStatus::Running->value]);
        app(CampaignService::class)->runDispatchCycle($campaign);

        Bus::assertDispatched(SendEmailJob::class, 2);
        $this->assertSame(1, Message::query()->where('campaign_id', $campaign->id)->where('is_test', true)->count());
        $this->assertSame(1, Message::query()->where('campaign_id', $campaign->id)->where('is_test', false)->count());
    }
}
