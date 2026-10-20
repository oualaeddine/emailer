<?php

namespace Tests\Feature\Modules\Campaigns\AbTesting;

use App\Modules\Campaigns\AbTesting\Events\AbTestWinnerPicked;
use App\Modules\Campaigns\AbTesting\Jobs\PickAbWinnerJob;
use App\Modules\Campaigns\AbTesting\Services\AbTestService;
use App\Modules\Campaigns\AbTesting\Services\VariantAssigner;
use App\Modules\Campaigns\Contracts\AudienceResolver;
use App\Modules\Campaigns\Enums\CampaignStatus;
use App\Modules\Campaigns\Jobs\DispatchCampaignJob;
use App\Modules\Campaigns\Models\AbTestVariant;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Campaigns\Models\CampaignRecipient;
use App\Modules\Campaigns\Services\CampaignService;
use App\Modules\DeliveryEngine\Jobs\SendEmailJob;
use App\Modules\Tracking\Models\Message;
use App\Modules\Tracking\Models\MessageEvent;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\Feature\Modules\Campaigns\AbTesting\Concerns\BuildsAbTests;
use Tests\Feature\Modules\Campaigns\Concerns\BuildsCampaignFixtures;
use Tests\TestCase;

/**
 * docs/43 §43.6.2 — deterministic sample split, split dispatch,
 * `PickAbWinnerJob` (opens / clicks / tie), remainder with the winner's
 * content, and the report `ab_test` object.
 */
class AbTestDispatchTest extends TestCase
{
    use BuildsAbTests, BuildsCampaignFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function variant(Campaign $campaign, string $label): AbTestVariant
    {
        return AbTestVariant::query()->where('campaign_id', $campaign->id)->where('label', $label)->firstOrFail();
    }

    private function runJob(Campaign $campaign): void
    {
        app()->call([new DispatchCampaignJob($campaign->id), 'handle']);
    }

    /**
     * Starts the campaign and releases the whole test sample.
     */
    private function startTest(Campaign $campaign): void
    {
        $campaign->update(['status' => CampaignStatus::Running->value]);
        $this->runJob($campaign);
    }

    // ---- assignment --------------------------------------------------------

    public function test_sample_size(): void
    {
        $this->assertSame(0, VariantAssigner::sampleSize(0, 20));
        $this->assertSame(1, VariantAssigner::sampleSize(1, 20));
        $this->assertSame(2, VariantAssigner::sampleSize(3, 20));
        $this->assertSame(20, VariantAssigner::sampleSize(100, 20));
        $this->assertSame(50, VariantAssigner::sampleSize(100, 50));
        $this->assertSame(5, VariantAssigner::sampleSize(100, 5));
    }

    public function test_assignment_is_deterministic_by_recipient_hash_and_even(): void
    {
        $campaign = $this->abCampaign($this->manager(), 100);
        app(AudienceResolver::class)->resolve($campaign);

        $a = $this->variant($campaign, 'A');
        $b = $this->variant($campaign, 'B');
        $rows = CampaignRecipient::query()->where('campaign_id', $campaign->id)->get();

        $this->assertSame(10, $rows->where('variant_id', $a->id)->count());
        $this->assertSame(10, $rows->where('variant_id', $b->id)->count());
        $this->assertSame(80, $rows->whereNull('variant_id')->count());
        $this->assertSame(10, $a->fresh()->sample_size);
        $this->assertSame(10, $b->fresh()->sample_size);

        // Expected split: hash order, first 20, alternating A/B.
        $ordered = $rows->pluck('recipient_id')->sortBy(fn ($id) => VariantAssigner::hashKey($campaign, (int) $id).':'.str_pad((string) $id, 12, '0', STR_PAD_LEFT))->values();
        $expectedA = $ordered->take(20)->filter(fn ($id, $i) => $i % 2 === 0)->sort()->values()->all();
        $this->assertSame($expectedA, $rows->where('variant_id', $a->id)->pluck('recipient_id')->sort()->values()->all());

        // Re-running never re-splits.
        app(VariantAssigner::class)->assign($campaign->fresh());
        $this->assertSame(
            $rows->pluck('variant_id', 'id')->all(),
            CampaignRecipient::query()->where('campaign_id', $campaign->id)->pluck('variant_id', 'id')->all(),
        );

        // Same audience, same campaign → same split after re-materialisation.
        $before = CampaignRecipient::query()->where('campaign_id', $campaign->id)->pluck('variant_id', 'recipient_id')->all();
        CampaignRecipient::query()->where('campaign_id', $campaign->id)->delete();
        $campaign->forceFill(['recipients_count' => 0])->save();
        app(AudienceResolver::class)->resolve($campaign->fresh());
        $this->assertEquals($before, CampaignRecipient::query()->where('campaign_id', $campaign->id)->pluck('variant_id', 'recipient_id')->all());
    }

    public function test_classic_campaigns_are_not_split(): void
    {
        $campaign = $this->campaignWithAudience($this->manager(), ['lists' => [$this->listWith($this->recipients(5))->id]]);
        app(AudienceResolver::class)->resolve($campaign);

        $this->assertSame(0, CampaignRecipient::query()->whereNotNull('variant_id')->count());
    }

    // ---- split dispatch ----------------------------------------------------

    public function test_split_dispatch_sends_only_the_sample_and_schedules_the_winner_job(): void
    {
        Bus::fake([SendEmailJob::class, PickAbWinnerJob::class, DispatchCampaignJob::class]);
        $this->travelTo(now()->startOfMinute());
        $campaign = $this->abCampaign($this->manager(), 50);

        $this->startTest($campaign);

        $a = $this->variant($campaign, 'A');
        $b = $this->variant($campaign, 'B');
        $messages = Message::query()->where('campaign_id', $campaign->id)->get();

        $this->assertCount(10, $messages);
        $this->assertSame(5, $messages->where('variant_id', $a->id)->count());
        $this->assertSame(5, $messages->where('variant_id', $b->id)->count());
        $this->assertTrue($messages->where('variant_id', $a->id)->every(fn (Message $m) => $m->subject === 'Objet A'));
        $this->assertTrue($messages->where('variant_id', $b->id)->every(fn (Message $m) => $m->subject === 'Objet B'));
        Bus::assertDispatched(SendEmailJob::class, 10);

        $this->assertSame(40, CampaignRecipient::query()->where('campaign_id', $campaign->id)->where('status', 'pending')->whereNull('variant_id')->count());

        $campaign->refresh();
        $this->assertSame(CampaignStatus::Running->value, $campaign->status);
        $this->assertNotNull($campaign->sent_at);
        $this->assertNull($campaign->completed_at);
        Bus::assertDispatched(PickAbWinnerJob::class, fn (PickAbWinnerJob $job) => $job->campaignId === $campaign->id
            && $job->delay->equalTo($campaign->sent_at->copy()->addMinutes(240)));
        Bus::assertNotDispatched(DispatchCampaignJob::class);

        // Another cycle (e.g. resume) during the test releases nothing and does not re-schedule the pick.
        $this->runJob($campaign);
        Bus::assertDispatched(SendEmailJob::class, 10);
        Bus::assertDispatched(PickAbWinnerJob::class, 1);
    }

    public function test_large_sample_is_released_in_chunks_before_the_pick_is_scheduled(): void
    {
        Bus::fake([SendEmailJob::class, PickAbWinnerJob::class, DispatchCampaignJob::class]);
        $campaign = $this->abCampaign($this->manager(), 1100, ['sample_percent' => 50]);

        $this->startTest($campaign);
        Bus::assertDispatched(SendEmailJob::class, CampaignService::BATCH_SIZE);
        Bus::assertDispatched(DispatchCampaignJob::class, 1);
        Bus::assertNotDispatched(PickAbWinnerJob::class);

        $this->runJob($campaign);
        Bus::assertDispatched(SendEmailJob::class, 550);
        Bus::assertDispatched(PickAbWinnerJob::class, 1);
        $this->assertSame(550, CampaignRecipient::query()->where('campaign_id', $campaign->id)->whereNull('variant_id')->where('status', 'pending')->count());
    }

    // ---- winner job --------------------------------------------------------

    /**
     * Marks the first `$opens` / `$clicks` sample messages of a version as
     * opened / clicked (on the message, or as `message_events` rows).
     */
    private function engage(Campaign $campaign, string $label, int $opens, int $clicks, bool $viaEvents = false): void
    {
        $variant = $this->variant($campaign, $label);
        $messages = Message::query()->where('campaign_id', $campaign->id)->where('variant_id', $variant->id)->orderBy('id')->get();

        foreach ($messages->take($opens) as $message) {
            if ($viaEvents) {
                MessageEvent::factory()->create(['message_id' => $message->id, 'campaign_id' => $campaign->id, 'recipient_id' => $message->recipient_id, 'event_type' => MessageEvent::OPENED]);
            } else {
                DB::table('messages')->where('id', $message->id)->update(['opened_at' => now(), 'status' => 'opened', 'sent_at' => now()]);
            }
        }
        foreach ($messages->take($clicks) as $message) {
            if ($viaEvents) {
                MessageEvent::factory()->create(['message_id' => $message->id, 'campaign_id' => $campaign->id, 'recipient_id' => $message->recipient_id, 'event_type' => MessageEvent::CLICKED]);
            } else {
                DB::table('messages')->where('id', $message->id)->update(['clicked_at' => now(), 'status' => 'clicked', 'sent_at' => now()]);
            }
        }
    }

    private function runPick(Campaign $campaign): void
    {
        app()->call([new PickAbWinnerJob($campaign->id), 'handle']);
    }

    public function test_winner_by_opens_releases_the_remainder_with_the_winner_content(): void
    {
        Bus::fake([SendEmailJob::class, PickAbWinnerJob::class, DispatchCampaignJob::class]);
        Event::fake([AbTestWinnerPicked::class]);
        $campaign = $this->abCampaign($this->manager(), 50);
        $this->startTest($campaign);

        $this->engage($campaign, 'A', 1, 3);
        $this->engage($campaign, 'B', 3, 0);

        // Too early: re-scheduled, nothing picked.
        $this->runPick($campaign);
        $this->assertNull($campaign->fresh()->winner_variant_id);
        Bus::assertDispatched(PickAbWinnerJob::class, 2);

        $this->travel(241)->minutes();
        $this->runPick($campaign);

        $b = $this->variant($campaign, 'B');
        $campaign->refresh();
        $this->assertSame($b->id, $campaign->winner_variant_id);
        $this->assertTrue($b->is_winner);
        $this->assertFalse($this->variant($campaign, 'A')->is_winner);
        Event::assertDispatched(AbTestWinnerPicked::class, fn (AbTestWinnerPicked $e) => $e->campaignId === $campaign->id && $e->variantId === $b->id && $e->pickedBy === 'auto');
        Bus::assertDispatched(DispatchCampaignJob::class, 1);
        $this->assertDatabaseHas('audit_logs', ['action' => 'campaign.ab_winner_picked', 'auditable_id' => $campaign->id, 'user_id' => null]);

        // Remainder: 40 messages with B's subject, no variant, then completed.
        $this->runJob($campaign);
        $remainder = Message::query()->where('campaign_id', $campaign->id)->whereNull('variant_id')->get();
        $this->assertCount(40, $remainder);
        $this->assertTrue($remainder->every(fn (Message $m) => $m->subject === 'Objet B'));
        $this->assertSame(0, CampaignRecipient::query()->where('campaign_id', $campaign->id)->where('status', 'pending')->count());
        $this->assertSame(CampaignStatus::Completed->value, $campaign->fresh()->status);
        $this->assertSame('completed', app(AbTestService::class)->phase($campaign->fresh()));

        // A late duplicate pick is a no-op.
        $this->runPick($campaign);
        $this->assertSame($b->id, $campaign->fresh()->winner_variant_id);
    }

    public function test_winner_by_clicks_counts_message_events(): void
    {
        Bus::fake([SendEmailJob::class, PickAbWinnerJob::class, DispatchCampaignJob::class]);
        $campaign = $this->abCampaign($this->manager(), 50, ['winner_metric' => 'clicks']);
        $this->startTest($campaign);

        $this->engage($campaign, 'A', 5, 1, viaEvents: true);
        $this->engage($campaign, 'B', 0, 2, viaEvents: true);

        $metrics = app(AbTestService::class)->metrics($campaign);
        $this->assertSame(['sent' => 5, 'opens' => 5, 'clicks' => 1], $metrics[$this->variant($campaign, 'A')->id]);
        $this->assertSame(['sent' => 5, 'opens' => 0, 'clicks' => 2], $metrics[$this->variant($campaign, 'B')->id]);

        $this->travel(5)->hours();
        $this->runPick($campaign);
        $this->assertSame($this->variant($campaign, 'B')->id, $campaign->fresh()->winner_variant_id);
    }

    public function test_tie_goes_to_a(): void
    {
        Bus::fake([SendEmailJob::class, PickAbWinnerJob::class, DispatchCampaignJob::class]);
        $campaign = $this->abCampaign($this->manager(), 50);
        $this->startTest($campaign);
        $this->engage($campaign, 'A', 2, 0);
        $this->engage($campaign, 'B', 2, 0);

        $this->travel(5)->hours();
        $this->runPick($campaign);

        $this->assertSame($this->variant($campaign, 'A')->id, $campaign->fresh()->winner_variant_id);
    }

    public function test_no_auto_pick_when_disabled_or_cancelled(): void
    {
        Bus::fake([SendEmailJob::class, PickAbWinnerJob::class, DispatchCampaignJob::class]);
        $manual = $this->abCampaign($this->manager(), 20, ['auto_send_winner' => false]);
        $this->startTest($manual);
        $cancelled = $this->abCampaign($this->manager(), 20);
        $this->startTest($cancelled);
        $cancelled->update(['status' => CampaignStatus::Cancelled->value]);

        $this->travel(5)->hours();
        $this->runPick($manual);
        $this->runPick($cancelled);

        $this->assertNull($manual->fresh()->winner_variant_id);
        $this->assertSame('testing', app(AbTestService::class)->phase($manual->fresh()));
        $this->assertNull($cancelled->fresh()->winner_variant_id);
    }

    public function test_pick_during_pause_sends_the_remainder_on_resume(): void
    {
        Bus::fake([SendEmailJob::class, PickAbWinnerJob::class, DispatchCampaignJob::class]);
        $campaign = $this->abCampaign($this->manager(), 20);
        $this->startTest($campaign);
        app(CampaignService::class)->pause($campaign->fresh());

        $this->travel(5)->hours();
        $this->runPick($campaign);
        $this->assertNotNull($campaign->fresh()->winner_variant_id);
        Bus::assertNotDispatched(DispatchCampaignJob::class);

        app(CampaignService::class)->resume($campaign->fresh());
        $this->runJob($campaign);

        $this->assertSame(20, Message::query()->where('campaign_id', $campaign->id)->count());
        $this->assertSame(CampaignStatus::Completed->value, $campaign->fresh()->status);
    }

    // ---- report ------------------------------------------------------------

    public function test_report_overview_contains_the_ab_test_object(): void
    {
        Bus::fake([SendEmailJob::class, PickAbWinnerJob::class, DispatchCampaignJob::class]);
        $owner = $this->manager();
        $campaign = $this->abCampaign($owner, 50);
        $this->startTest($campaign);

        DB::table('messages')->where('campaign_id', $campaign->id)->update(['status' => 'sent', 'sent_at' => now()]);
        $this->engage($campaign, 'A', 1, 1);
        $this->engage($campaign, 'B', 4, 2);

        $data = $this->actingAs($owner)->getJson("/api/v1/campaigns/{$campaign->uuid}/report/overview")->assertOk()->json('data.ab_test');
        $this->assertSame('testing', $data['phase']);
        $this->assertNull($data['winner_variant_id']);
        $this->assertNull($data['picked_by']);
        $this->assertSame(20, $data['config']['sample_percent']);
        $this->assertSame(['A', 'B'], array_column($data['variants'], 'label'));
        $this->assertSame('Objet B', $data['variants'][1]['subject']);
        $this->assertSame(5, $data['variants'][0]['globalStats']['sent']);
        $this->assertSame(1, $data['variants'][0]['globalStats']['uniqueViews']);
        $this->assertSame(4, $data['variants'][1]['globalStats']['uniqueViews']);
        $this->assertSame(2, $data['variants'][1]['globalStats']['uniqueClicks']);
        $this->assertEqualsWithDelta(0.8, $data['variants'][1]['rates']['open_rate'], 0.0001);
        $this->assertEqualsWithDelta(0.2, $data['variants'][0]['rates']['click_rate'], 0.0001);

        $b = $this->variant($campaign, 'B');
        $this->actingAs($owner)->postJson("/api/v1/campaigns/{$campaign->uuid}/ab-test/winner", ['variant_id' => $b->uuid])->assertOk();
        $campaign->forceFill(['stats_updated_at' => null])->save();

        $response = $this->actingAs($owner)->getJson("/api/v1/campaigns/{$campaign->uuid}/report/overview")->assertOk();
        $response->assertJsonPath('data.ab_test.phase', 'winner_picked')
            ->assertJsonPath('data.ab_test.winner_variant_id', $b->uuid)
            ->assertJsonPath('data.ab_test.picked_by', 'manual');
        $this->assertNotNull($response->json('data.ab_test.picked_at'));
        $this->assertContains('ab_winner_picked', array_column($response->json('data.timeline'), 'action'));

        // Classic campaigns: null.
        $classic = $this->campaignWithAudience($owner, ['lists' => [$this->listWith($this->recipients(1))->id]]);
        $classic->update(['status' => CampaignStatus::Running->value]);
        app(CampaignService::class)->runDispatchCycle($classic);
        $this->actingAs($owner)->getJson("/api/v1/campaigns/{$classic->uuid}/report/overview")->assertOk()->assertJsonPath('data.ab_test', null);
    }
}
