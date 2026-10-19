<?php

namespace Tests\Feature\Modules\Campaigns;

use App\Domain\Enums\RecipientStatus;
use App\Modules\Campaigns\Contracts\AudienceResolver;
use App\Modules\Campaigns\Enums\CampaignStatus;
use App\Modules\Campaigns\Enums\CampaignSuspendedReason;
use App\Modules\Campaigns\Exceptions\CampaignStateConflictException;
use App\Modules\Campaigns\Jobs\CheckCampaignLinksJob;
use App\Modules\Campaigns\Jobs\DispatchCampaignJob;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Campaigns\Models\CampaignRecipient;
use App\Modules\Campaigns\Services\CampaignService;
use App\Modules\Campaigns\Services\Checklist\CampaignChecklistContract;
use App\Modules\Campaigns\Support\ExternalReferences;
use App\Modules\DeliveryEngine\Jobs\SendEmailJob;
use App\Modules\Recipients\Models\Recipient;
use App\Modules\Senders\Models\Sender;
use App\Modules\Tracking\Models\Message;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Queue;
use Tests\Feature\Modules\Campaigns\Concerns\BuildsCampaignFixtures;
use Tests\TestCase;

/**
 * Wave 1 review fixes (docs/44 — review-fix A) on the Campaigns module.
 */
class CampaignReviewFixesTest extends TestCase
{
    use BuildsCampaignFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function readyCampaign(int $recipients = 2, array $attributes = []): Campaign
    {
        return $this->campaignWithAudience($this->operator(), ['lists' => [$this->listWith($this->recipients($recipients))->id]], $attributes);
    }

    private function checklistStatus(Campaign $campaign, string $key): string
    {
        return app(CampaignChecklistContract::class)->evaluate($campaign)->item($key)->status;
    }

    // B2 -------------------------------------------------------------------

    public function test_unsubscribe_check_accepts_legacy_tag_and_unsubscribe_href(): void
    {
        $campaign = $this->readyCampaign();

        foreach ([
            '<a href="{{ unsubscribe }}">u</a>' => 'ok',
            '<a href="{{unsubscribe_url}}">u</a>' => 'ok',
            '<a href="{{ UNSUBSCRIBE_URL }}">u</a>' => 'ok',
            '<a href="https://mail.example.dz/unsubscribe/abc">u</a>' => 'ok',
            '<a href="https://example.dz/">u</a>' => 'error',
        ] as $html => $expected) {
            $campaign->html_body = '<p>Bonjour</p>'.$html;
            $this->assertSame($expected, $this->checklistStatus($campaign, 'unsubscribe_link'), $html);
        }
    }

    // B3 / N4 --------------------------------------------------------------

    public function test_job_serialized_before_scheduled_for_existed_still_runs(): void
    {
        $campaign = $this->readyCampaign();
        $campaign->update(['status' => CampaignStatus::Running->value]);

        // Payload queued by the previous release: no `scheduledFor` key at all.
        $class = DispatchCampaignJob::class;
        $legacy = 'O:'.strlen($class).':"'.$class.'":1:{s:10:"campaignId";i:'.$campaign->id.';}';

        $job = unserialize($legacy);
        $this->assertInstanceOf(DispatchCampaignJob::class, $job);
        $this->assertNull($job->scheduledFor);

        Bus::fake();
        $job->handle(app(CampaignService::class));

        Bus::assertDispatched(SendEmailJob::class, 2);
    }

    public function test_stale_check_compares_whole_seconds(): void
    {
        $campaign = $this->readyCampaign(1);
        $campaign->update(['status' => CampaignStatus::Scheduled->value, 'scheduled_at' => now()->startOfSecond()]);

        $withFraction = $campaign->fresh()->scheduled_at->copy()->addMilliseconds(400)->format('Y-m-d\TH:i:s.uP');

        Bus::fake();
        (new DispatchCampaignJob($campaign->id, $withFraction))->handle(app(CampaignService::class));

        Bus::assertDispatched(SendEmailJob::class, 1);
    }

    // S1 -------------------------------------------------------------------

    public function test_partial_materialisation_is_completed_on_next_cycle(): void
    {
        $campaign = $this->readyCampaign(3);
        $campaign->update(['status' => CampaignStatus::Running->value]);
        $first = $campaign->audiences()->first();
        $someone = Recipient::query()->whereIn('id', \DB::table('recipient_list_recipient')->where('recipient_list_id', $first->target_id)->pluck('recipient_id'))->first();

        // A worker killed before the completion marker was written.
        CampaignRecipient::query()->create(['campaign_id' => $campaign->id, 'recipient_id' => $someone->id, 'status' => 'pending', 'materialised_at' => now()]);
        $this->assertFalse($campaign->fresh()->isAudienceMaterialised());

        Bus::fake();
        app(CampaignService::class)->runDispatchCycle($campaign);

        Bus::assertDispatched(SendEmailJob::class, 3);
        $this->assertSame(3, $campaign->fresh()->recipients_count);
        $this->assertSame(CampaignStatus::Completed->value, $campaign->fresh()->status);
    }

    // S2 -------------------------------------------------------------------

    public function test_editing_a_scheduled_campaign_reasserts_the_checklist(): void
    {
        $campaign = $this->readyCampaign();
        $campaign->update(['status' => CampaignStatus::Scheduled->value, 'scheduled_at' => now()->addDay()]);
        $user = $campaign->createdBy;
        $html = $campaign->html_body;

        $this->actingAs($user)->patchJson("/api/v1/campaigns/{$campaign->uuid}", ['html_body' => '<p>Sans lien</p>'])
            ->assertStatus(422)->assertJsonPath('errors.checklist', ['unsubscribe_link']);
        $this->assertSame($html, $campaign->fresh()->html_body);

        $this->actingAs($user)->putJson("/api/v1/campaigns/{$campaign->uuid}/design", ['editor_type' => 'html', 'html_body' => '<p>Sans lien</p>'])
            ->assertStatus(422)->assertJsonPath('errors.checklist', ['unsubscribe_link']);
        $this->assertSame($html, $campaign->fresh()->html_body);

        $this->actingAs($user)->patchJson("/api/v1/campaigns/{$campaign->uuid}", ['name' => 'Renommée'])->assertOk();
    }

    // S3 -------------------------------------------------------------------

    public function test_audience_edit_while_suspended_is_applied_on_resume(): void
    {
        $service = app(CampaignService::class);
        $campaign = $this->readyCampaign(2);
        $campaign->update(['status' => CampaignStatus::Running->value, 'send_mode' => 'batches', 'batch_size' => 1, 'batch_interval_minutes' => 60]);

        Bus::fake();
        $service->runDispatchCycle($campaign);
        Bus::assertDispatched(SendEmailJob::class, 1);
        $sentRow = CampaignRecipient::query()->where('campaign_id', $campaign->id)->where('status', 'queued')->first();

        $service->suspend($campaign->fresh(), CampaignSuspendedReason::cases()[0]);

        $newcomers = $this->recipients(3);
        $service->update($campaign->fresh(), ['audience' => ['lists' => [$this->listWith($newcomers)->id]]]);

        $this->assertSame(0, CampaignRecipient::query()->where('campaign_id', $campaign->id)->where('status', 'pending')->count());
        $this->assertTrue(CampaignRecipient::query()->whereKey($sentRow->id)->where('status', 'queued')->exists());

        $campaign->fresh()->update(['send_mode' => 'immediate', 'batch_size' => null, 'batch_interval_minutes' => null]);
        $service->resume($campaign->fresh());
        $service->runDispatchCycle($campaign->fresh());

        $this->assertEqualsCanonicalizing(
            $newcomers->pluck('id')->all(),
            Message::query()->where('campaign_id', $campaign->id)->where('id', '!=', $sentRow->message_id)->pluck('recipient_id')->all(),
        );
        $this->assertSame(CampaignStatus::Completed->value, $campaign->fresh()->status);
    }

    // S4 -------------------------------------------------------------------

    public function test_deleted_sender_fails_checklist_and_cannot_be_set(): void
    {
        $campaign = $this->readyCampaign();
        $this->assertSame('ok', $this->checklistStatus($campaign, 'sender_verified'));

        Sender::query()->whereKey($campaign->sender_id)->first()->delete();
        $this->assertSame('error', $this->checklistStatus($campaign->fresh(), 'sender_verified'));

        $deleted = Sender::factory()->verified()->create();
        $deleted->delete();
        ExternalReferences::flush();

        $this->actingAs($campaign->createdBy)->patchJson("/api/v1/campaigns/{$campaign->uuid}", ['sender_id' => $deleted->uuid])
            ->assertStatus(422)->assertJsonValidationErrors('sender_id');
    }

    // S9 -------------------------------------------------------------------

    public function test_test_send_reuses_trashed_contact_and_never_creates_marketing_contacts(): void
    {
        $campaign = $this->readyCampaign(1);
        $trashed = $this->recipients(1, ['email' => 'ancien@exemple.dz'])->first();
        $trashed->delete();

        Bus::fake();
        $this->actingAs($campaign->createdBy)->postJson("/api/v1/campaigns/{$campaign->uuid}/test-send", [
            'emails' => ['ancien@exemple.dz', 'nouveau@exemple.dz'],
        ])->assertStatus(202);

        $this->assertTrue($trashed->fresh()->trashed());
        $this->assertTrue(Message::query()->where('recipient_id', $trashed->id)->where('is_test', true)->exists());

        $created = Recipient::query()->where('email', 'nouveau@exemple.dz')->firstOrFail();
        $this->assertSame(RecipientStatus::Invalid->value, $created->status);

        // Even if later added to a targeted list, it is never in an audience.
        $other = $this->campaignWithAudience($campaign->createdBy, ['lists' => [$this->listWith([$created])->id]]);
        $this->assertSame([], app(AudienceResolver::class)->recipientIds($other));
    }

    // S11 ------------------------------------------------------------------

    public function test_checklist_recipient_count_matches_the_resolved_audience(): void
    {
        $recipients = $this->recipients(3);
        $recipients->first()->update(['status' => RecipientStatus::Suppressed->value]);
        $campaign = $this->campaignWithAudience($this->operator(), ['lists' => [$this->listWith($recipients)->id]]);

        $item = app(CampaignChecklistContract::class)->evaluate($campaign)->item('recipients_selected');
        $this->assertSame(2, $item->details['count']);
    }

    // S13 ------------------------------------------------------------------

    public function test_link_check_job_runs_on_the_maintenance_queue(): void
    {
        Queue::fake();
        CheckCampaignLinksJob::dispatch(1);

        Queue::assertPushedOn('maintenance', CheckCampaignLinksJob::class);
        $this->assertLessThan(90, (new CheckCampaignLinksJob(1))->timeout);
    }

    // S15 / N8 -------------------------------------------------------------

    public function test_lifecycle_actions_on_archived_campaign_are_rejected(): void
    {
        $campaign = Campaign::factory()->create(['status' => CampaignStatus::Suspended->value, 'archived_at' => now()]);

        $this->expectException(CampaignStateConflictException::class);
        $this->expectExceptionMessage('Cette campagne est archivée.');
        app(CampaignService::class)->resume($campaign);
    }

    // N2 -------------------------------------------------------------------

    public function test_recipients_tab_lists_campaign_recipient_rows(): void
    {
        $recipients = $this->recipients(2);
        $campaign = $this->campaignWithAudience($this->operator(), ['lists' => [$this->listWith($recipients)->id]]);
        $campaign->update(['status' => CampaignStatus::Running->value, 'send_mode' => 'batches', 'batch_size' => 1, 'batch_interval_minutes' => 60]);

        Bus::fake();
        app(CampaignService::class)->runDispatchCycle($campaign);

        $user = $campaign->createdBy;
        $this->actingAs($user)->getJson("/api/v1/campaigns/{$campaign->uuid}/recipients")
            ->assertOk()->assertJsonCount(2, 'data');

        $queued = $this->actingAs($user)->getJson("/api/v1/campaigns/{$campaign->uuid}/recipients?status=queued")
            ->assertOk()->assertJsonCount(1, 'data');
        $this->assertNotNull($queued->json('data.0.id'));
        $this->assertSame('queued', $queued->json('data.0.recipient_status'));

        $pending = $this->actingAs($user)->getJson("/api/v1/campaigns/{$campaign->uuid}/recipients?status=pending")
            ->assertOk()->assertJsonCount(1, 'data');
        $this->assertNull($pending->json('data.0.id'));
        $this->assertSame('pending', $pending->json('data.0.status'));
        $this->assertNotNull($pending->json('data.0.recipient_email'));
    }

    // N3 -------------------------------------------------------------------

    public function test_list_rows_omit_html_body_and_references_are_shared(): void
    {
        $campaign = $this->readyCampaign(1);
        $user = $campaign->createdBy;

        $list = $this->actingAs($user)->getJson('/api/v1/campaigns')->assertOk();
        $this->assertArrayNotHasKey('html_body', $list->json('data.0'));

        $this->actingAs($user)->getJson("/api/v1/campaigns/{$campaign->uuid}")
            ->assertOk()->assertJsonPath('data.html_body', $campaign->html_body);

        $this->assertSame(app(ExternalReferences::class), app(ExternalReferences::class));
    }
}
