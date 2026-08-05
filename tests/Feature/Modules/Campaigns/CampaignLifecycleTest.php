<?php

namespace Tests\Feature\Modules\Campaigns;

use App\Domain\Enums\PermissionName;
use App\Domain\Enums\RecipientListType;
use App\Domain\Enums\RoleName;
use App\Domain\Enums\SuppressionReason;
use App\Modules\Campaigns\Enums\CampaignStatus;
use App\Modules\Campaigns\Jobs\DispatchCampaignJob;
use App\Modules\Campaigns\Services\CampaignService;
use App\Modules\DeliveryEngine\Jobs\SendEmailJob;
use App\Modules\Identity\Models\User;
use App\Modules\Recipients\Models\Recipient;
use App\Modules\Recipients\Models\RecipientList;
use App\Modules\Recipients\Models\Segment;
use App\Modules\Suppression\Services\SuppressionManager;
use App\Modules\Templates\Models\Template;
use App\Modules\Tracking\Models\Message;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

/**
 * docs/15-campaign-management.md — Campaign Management module (WP-20).
 *
 * Exercises the state machine (§15.1), pause/resume/cancel (§15.6),
 * permission gating (§15.9, own `campaigns.send` vs. `campaigns.send_any`),
 * and the per-recipient fan-out dispatch — asserting one `SendEmailJob`
 * per targeted, non-suppressed recipient via `Bus::fake()`, mirroring
 * `SendEmailJobTest`'s "call the job's handle()/service method directly,
 * intercept nested dispatch with Bus::fake()" approach rather than relying
 * on the queue worker to actually run `DispatchCampaignJob`.
 */
class CampaignLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function createTemplate(): Template
    {
        return Template::query()->create([
            'name' => 'Modèle Test',
            'html_content' => '<p>Bonjour {{recipient.first_name|there}}</p>',
        ]);
    }

    private function createStaticList(int $recipientCount = 3, bool $withOneSuppressed = false): RecipientList
    {
        $list = RecipientList::query()->create([
            'name' => 'Liste Test',
            'type' => RecipientListType::Static->value,
        ]);

        $recipients = Recipient::factory()->count($recipientCount)->create();
        $list->recipients()->syncWithoutDetaching(
            $recipients->mapWithKeys(fn (Recipient $r) => [$r->id => ['added_at' => now()]])->all(),
        );

        if ($withOneSuppressed) {
            $suppressed = Recipient::factory()->suppressed()->create();
            $list->recipients()->syncWithoutDetaching([$suppressed->id => ['added_at' => now()]]);
            app(SuppressionManager::class)->suppress($suppressed->email, SuppressionReason::ManualBlock);
        }

        return $list;
    }

    private function createSegment(): Segment
    {
        $list = RecipientList::query()->create([
            'name' => 'Segment Test',
            'type' => RecipientListType::DynamicSegment->value,
        ]);

        Recipient::factory()->count(2)->create();

        return Segment::query()->create([
            'recipient_list_id' => $list->id,
            'rules' => ['operator' => 'AND', 'conditions' => []],
        ]);
    }

    /**
     * Payload keyed by internal integer FKs, for calling
     * `CampaignService::create()` directly (bypassing the HTTP layer,
     * which is the only layer that resolves uuid -> id).
     *
     * @return array<string, mixed>
     */
    private function draftData(Template $template, RecipientList $list): array
    {
        return [
            'name' => 'Campagne Test',
            'template_id' => $template->id,
            'subject' => 'Objet de test',
            'html_body' => '<p>Bonjour {{recipient.first_name|there}}</p>',
            'recipient_list_id' => $list->id,
        ];
    }

    /**
     * Payload keyed by public uuids, for `postJson('/api/v1/campaigns', ...)`.
     *
     * @return array<string, mixed>
     */
    private function apiPayload(Template $template, RecipientList $list): array
    {
        return [
            'name' => 'Campagne Test',
            'template_id' => $template->uuid,
            'subject' => 'Objet de test',
            'html_body' => '<p>Bonjour {{recipient.first_name|there}}</p>',
            'recipient_list_id' => $list->uuid,
        ];
    }

    public function test_marketing_operator_can_create_a_draft_campaign(): void
    {
        $operator = User::factory()->withRole(RoleName::MarketingOperator)->create();
        $template = $this->createTemplate();
        $list = $this->createStaticList();

        $response = $this->actingAs($operator)->postJson('/api/v1/campaigns', $this->apiPayload($template, $list));

        $response->assertCreated();
        $response->assertJsonPath('status', CampaignStatus::Draft->value);
        $this->assertDatabaseHas('campaigns', ['name' => 'Campagne Test', 'status' => 'draft']);
    }

    public function test_creating_a_campaign_requires_exactly_one_of_recipient_list_or_segment(): void
    {
        $operator = User::factory()->withRole(RoleName::MarketingOperator)->create();
        $template = $this->createTemplate();
        $list = $this->createStaticList();
        $segment = $this->createSegment();

        // Neither set.
        $this->actingAs($operator)->postJson('/api/v1/campaigns', [
            'name' => 'Sans cible',
            'template_id' => $template->uuid,
        ])->assertStatus(422);

        // Both set.
        $this->actingAs($operator)->postJson('/api/v1/campaigns', [
            'name' => 'Deux cibles',
            'template_id' => $template->uuid,
            'recipient_list_id' => $list->uuid,
            'segment_id' => $segment->uuid,
        ])->assertStatus(422);
    }

    public function test_full_lifecycle_draft_to_scheduled_to_running_to_completed(): void
    {
        $operator = User::factory()->withRole(RoleName::MarketingOperator)->create();
        $template = $this->createTemplate();
        $list = $this->createStaticList(3);

        $service = app(CampaignService::class);
        $campaign = $service->create($this->draftData($template, $list), $operator);
        $this->assertSame(CampaignStatus::Draft->value, $campaign->status);

        Bus::fake();

        $scheduledAt = now()->addHour();
        $service->schedule($campaign, $scheduledAt);

        $campaign->refresh();
        $this->assertSame(CampaignStatus::Scheduled->value, $campaign->status);
        $this->assertNotNull($campaign->scheduled_at);

        Bus::assertDispatched(DispatchCampaignJob::class, function (DispatchCampaignJob $job) use ($campaign): bool {
            return $job->campaignId === $campaign->id;
        });

        // Simulate the scheduled DispatchCampaignJob firing (Bus::fake()
        // prevented actual execution) — one fan-out cycle should dispatch a
        // SendEmailJob per targeted recipient and complete the campaign
        // since 3 recipients fit well within CampaignService::BATCH_SIZE.
        $service->runDispatchCycle($campaign);

        $campaign->refresh();
        $this->assertSame(CampaignStatus::Completed->value, $campaign->status);
        $this->assertNotNull($campaign->sent_at);

        $this->assertSame(3, Message::query()->where('campaign_id', $campaign->id)->count());
        Bus::assertDispatched(SendEmailJob::class, 3);
    }

    public function test_send_now_transitions_draft_to_running_and_dispatches_fan_out(): void
    {
        $operator = User::factory()->withRole(RoleName::MarketingOperator)->create();
        $template = $this->createTemplate();
        $list = $this->createStaticList(2);

        $campaign = app(CampaignService::class)->create($this->draftData($template, $list), $operator);

        Bus::fake();

        $response = $this->actingAs($operator)->postJson("/api/v1/campaigns/{$campaign->uuid}/send");
        $response->assertOk();
        $response->assertJsonPath('status', CampaignStatus::Running->value);

        Bus::assertDispatched(DispatchCampaignJob::class);
    }

    public function test_fan_out_creates_one_message_and_send_email_job_per_non_suppressed_recipient(): void
    {
        $operator = User::factory()->withRole(RoleName::MarketingOperator)->create();
        $template = $this->createTemplate();
        $list = $this->createStaticList(3, withOneSuppressed: true);

        $campaign = app(CampaignService::class)->create($this->draftData($template, $list), $operator);
        $campaign->update(['status' => CampaignStatus::Running->value]);

        Bus::fake();

        app(CampaignService::class)->runDispatchCycle($campaign);

        // 3 non-suppressed + 1 suppressed targeted, but only the 3
        // non-suppressed recipients get a Message + SendEmailJob.
        $this->assertSame(3, Message::query()->where('campaign_id', $campaign->id)->count());
        Bus::assertDispatched(SendEmailJob::class, 3);
    }

    public function test_pause_then_resume_cycle(): void
    {
        $operator = User::factory()->withRole(RoleName::MarketingOperator)->create();
        $template = $this->createTemplate();
        $list = $this->createStaticList(2);

        $campaign = app(CampaignService::class)->create($this->draftData($template, $list), $operator);
        $campaign->update(['status' => CampaignStatus::Running->value]);

        $response = $this->actingAs($operator)->postJson("/api/v1/campaigns/{$campaign->uuid}/pause");
        $response->assertOk();
        $response->assertJsonPath('status', CampaignStatus::Paused->value);

        Bus::fake();
        $response = $this->actingAs($operator)->postJson("/api/v1/campaigns/{$campaign->uuid}/resume");
        $response->assertOk();
        $response->assertJsonPath('status', CampaignStatus::Running->value);
        Bus::assertDispatched(DispatchCampaignJob::class);
    }

    public function test_resume_only_dispatches_to_recipients_without_an_existing_message(): void
    {
        $operator = User::factory()->withRole(RoleName::MarketingOperator)->create();
        $template = $this->createTemplate();
        $list = $this->createStaticList(3);

        $campaign = app(CampaignService::class)->create($this->draftData($template, $list), $operator);
        $campaign->update(['status' => CampaignStatus::Running->value]);

        // Simulate one recipient already dispatched before the pause.
        $alreadySent = $list->recipients()->first();
        Message::factory()->create(['campaign_id' => $campaign->id, 'recipient_id' => $alreadySent->id]);

        Bus::fake();
        app(CampaignService::class)->runDispatchCycle($campaign);

        // Only the 2 remaining recipients get a newly dispatched SendEmailJob.
        $this->assertSame(3, Message::query()->where('campaign_id', $campaign->id)->count());
        Bus::assertDispatched(SendEmailJob::class, 2);
    }

    public function test_cancel_is_allowed_from_draft_scheduled_and_paused(): void
    {
        $operator = User::factory()->withRole(RoleName::MarketingOperator)->create();
        $template = $this->createTemplate();

        foreach ([CampaignStatus::Draft, CampaignStatus::Scheduled, CampaignStatus::Paused] as $fromStatus) {
            $list = $this->createStaticList(1);
            $campaign = app(CampaignService::class)->create($this->draftData($template, $list), $operator);
            $campaign->update(['status' => $fromStatus->value]);

            $response = $this->actingAs($operator)->postJson("/api/v1/campaigns/{$campaign->uuid}/cancel");

            $response->assertOk();
            $response->assertJsonPath('status', CampaignStatus::Cancelled->value);
        }
    }

    public function test_cancel_is_rejected_from_completed(): void
    {
        $operator = User::factory()->withRole(RoleName::MarketingOperator)->create();
        $template = $this->createTemplate();
        $list = $this->createStaticList(1);

        $campaign = app(CampaignService::class)->create($this->draftData($template, $list), $operator);
        $campaign->update(['status' => CampaignStatus::Completed->value]);

        $this->actingAs($operator)->postJson("/api/v1/campaigns/{$campaign->uuid}/cancel")->assertStatus(422);
    }

    public function test_invalid_transition_pause_from_draft_is_rejected(): void
    {
        $operator = User::factory()->withRole(RoleName::MarketingOperator)->create();
        $template = $this->createTemplate();
        $list = $this->createStaticList(1);

        $campaign = app(CampaignService::class)->create($this->draftData($template, $list), $operator);

        $this->actingAs($operator)->postJson("/api/v1/campaigns/{$campaign->uuid}/pause")->assertStatus(422);
    }

    public function test_invalid_transition_resume_from_scheduled_is_rejected(): void
    {
        // §15.1's graph allows scheduled -> running (the dispatcher-triggered
        // transition), but CampaignService::resume() is a user action that
        // must only apply to a *paused* campaign — resuming a merely
        // "scheduled" one makes no sense and must be rejected.
        $operator = User::factory()->withRole(RoleName::MarketingOperator)->create();
        $template = $this->createTemplate();
        $list = $this->createStaticList(1);

        $campaign = app(CampaignService::class)->create($this->draftData($template, $list), $operator);
        $campaign->update(['status' => CampaignStatus::Scheduled->value]);

        $this->actingAs($operator)->postJson("/api/v1/campaigns/{$campaign->uuid}/resume")->assertStatus(422);
    }

    public function test_operator_with_campaigns_send_can_send_own_campaign_but_not_others(): void
    {
        $owner = User::factory()->withRole(RoleName::MarketingOperator)->create();
        $otherOperator = User::factory()->withRole(RoleName::MarketingOperator)->create();
        $template = $this->createTemplate();

        $campaign = app(CampaignService::class)->create($this->draftData($template, $this->createStaticList(1)), $owner);

        Bus::fake();

        // Owner (holds campaigns.send, not campaigns.send_any) can send their own.
        $this->actingAs($owner)->postJson("/api/v1/campaigns/{$campaign->uuid}/send")->assertOk();

        $campaign2 = app(CampaignService::class)->create($this->draftData($template, $this->createStaticList(1)), $owner);

        // A different operator (also only campaigns.send, not send_any)
        // cannot send someone else's campaign.
        $this->actingAs($otherOperator)->postJson("/api/v1/campaigns/{$campaign2->uuid}/send")->assertForbidden();
    }

    public function test_marketing_manager_with_send_any_can_send_any_campaign(): void
    {
        $operator = User::factory()->withRole(RoleName::MarketingOperator)->create();
        $manager = User::factory()->withRole(RoleName::MarketingManager)->create();
        $this->assertTrue($manager->hasPermission(PermissionName::CampaignsSendAny->value));
        $this->assertFalse($operator->hasPermission(PermissionName::CampaignsSendAny->value));

        $template = $this->createTemplate();
        $campaign = app(CampaignService::class)->create($this->draftData($template, $this->createStaticList(1)), $operator);

        Bus::fake();

        $this->actingAs($manager)->postJson("/api/v1/campaigns/{$campaign->uuid}/send")->assertOk();
    }

    public function test_viewer_cannot_create_a_campaign(): void
    {
        $viewer = User::factory()->withRole(RoleName::Viewer)->create();
        $template = $this->createTemplate();
        $list = $this->createStaticList(1);

        $this->actingAs($viewer)->postJson('/api/v1/campaigns', $this->apiPayload($template, $list))
            ->assertForbidden();
    }

    public function test_clone_copies_content_and_audience_but_resets_scheduling(): void
    {
        $operator = User::factory()->withRole(RoleName::MarketingOperator)->create();
        $template = $this->createTemplate();
        $list = $this->createStaticList(2);

        $campaign = app(CampaignService::class)->create($this->draftData($template, $list), $operator);
        $campaign->update([
            'status' => CampaignStatus::Scheduled->value,
            'scheduled_at' => now()->addDay(),
        ]);

        $response = $this->actingAs($operator)->postJson("/api/v1/campaigns/{$campaign->uuid}/clone");

        $response->assertCreated();
        $response->assertJsonPath('status', CampaignStatus::Draft->value);
        $response->assertJsonPath('scheduled_at', null);
        $response->assertJsonPath('subject', $campaign->subject);
        $response->assertJsonPath('name', 'Copie de '.$campaign->name);

        $this->assertDatabaseHas('campaigns', [
            'cloned_from_campaign_id' => $campaign->id,
            'recipient_list_id' => $campaign->recipient_list_id,
        ]);
    }

    public function test_segment_based_campaign_resolves_target_recipients(): void
    {
        $operator = User::factory()->withRole(RoleName::MarketingOperator)->create();
        $template = $this->createTemplate();
        $segment = $this->createSegment();

        $campaign = app(CampaignService::class)->create([
            'name' => 'Campagne Segment',
            'template_id' => $template->id,
            'subject' => 'Objet',
            'html_body' => '<p>Bonjour</p>',
            'segment_id' => $segment->id,
        ], $operator);

        $targeted = app(CampaignService::class)->resolveTargetRecipients($campaign);

        $this->assertSame(2, $targeted->count());
    }

    public function test_update_is_rejected_once_campaign_is_no_longer_draft(): void
    {
        $operator = User::factory()->withRole(RoleName::MarketingOperator)->create();
        $template = $this->createTemplate();
        $list = $this->createStaticList(1);

        $campaign = app(CampaignService::class)->create($this->draftData($template, $list), $operator);
        $campaign->update(['status' => CampaignStatus::Running->value]);

        $this->actingAs($operator)->patchJson("/api/v1/campaigns/{$campaign->uuid}", ['name' => 'Nouveau nom'])
            ->assertStatus(422);
    }
}
