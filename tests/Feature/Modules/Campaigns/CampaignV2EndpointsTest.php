<?php

namespace Tests\Feature\Modules\Campaigns;

use App\Domain\Enums\RoleName;
use App\Modules\Campaigns\Events\CampaignDesignSaved;
use App\Modules\Campaigns\Events\CampaignTestSent;
use App\Modules\Campaigns\Jobs\DispatchCampaignJob;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Campaigns\Models\CampaignAudience;
use App\Modules\Campaigns\Models\CampaignFolder;
use App\Modules\Campaigns\Models\CampaignRecipient;
use App\Modules\Campaigns\Services\CampaignService;
use App\Modules\DeliveryEngine\Jobs\SendEmailJob;
use App\Modules\Identity\Models\User;
use App\Modules\Templates\Models\Template;
use App\Modules\Tracking\Models\Message;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Tests\Feature\Modules\Campaigns\Concerns\BuildsCampaignFixtures;
use Tests\TestCase;

/**
 * docs/45-brevo-api-contracts.md §45.4.2 — Campaigns v2 endpoints.
 */
class CampaignV2EndpointsTest extends TestCase
{
    use BuildsCampaignFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    // ---- list ------------------------------------------------------------

    public function test_list_filters_by_status_tab_and_returns_status_counts(): void
    {
        $user = $this->operator();
        Campaign::factory()->count(2)->create(['status' => 'draft']);
        Campaign::factory()->create(['status' => 'scheduled']);
        Campaign::factory()->create(['status' => 'running']);
        Campaign::factory()->create(['status' => 'paused']);
        Campaign::factory()->create(['status' => 'suspended']);
        Campaign::factory()->create(['status' => 'completed']);
        Campaign::factory()->create(['status' => 'cancelled']);
        Campaign::factory()->archived()->create();

        $response = $this->actingAs($user)->getJson('/api/v1/campaigns')->assertOk();
        $response->assertJsonCount(8, 'data');
        $response->assertJsonPath('meta.status_counts', [
            'all' => 8, 'draft' => 2, 'scheduled' => 1, 'running' => 2, 'sent' => 1, 'suspended' => 1, 'archived' => 1,
        ]);
        $response->assertJsonPath('meta.total', 8);

        $this->actingAs($user)->getJson('/api/v1/campaigns?status_tab=running')->assertJsonCount(2, 'data');
        $this->actingAs($user)->getJson('/api/v1/campaigns?status_tab=archived')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.brevo_status', 'archive')
            ->assertJsonPath('data.0.is_archived', true);
        $this->actingAs($user)->getJson('/api/v1/campaigns?status=draft')->assertJsonCount(2, 'data');
    }

    public function test_list_supports_folder_tag_type_search_and_sort(): void
    {
        $user = $this->operator();
        $folder = CampaignFolder::factory()->create();
        Campaign::factory()->create(['name' => 'Rentrée 2026', 'folder_id' => $folder->id, 'tags' => ['promo']]);
        Campaign::factory()->create(['name' => 'Autre', 'type' => 'ab_test']);

        $this->actingAs($user)->getJson("/api/v1/campaigns?folder_id={$folder->uuid}")->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.folder_id', $folder->uuid);
        $this->actingAs($user)->getJson('/api/v1/campaigns?folder_id=none')->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Autre');
        $this->actingAs($user)->getJson('/api/v1/campaigns?tag=promo')->assertJsonCount(1, 'data');
        $this->actingAs($user)->getJson('/api/v1/campaigns?type=ab_test')->assertJsonCount(1, 'data');
        $this->actingAs($user)->getJson('/api/v1/campaigns?q=rentr')->assertJsonCount(1, 'data');
        $this->actingAs($user)->getJson('/api/v1/campaigns?sort=name')->assertJsonPath('data.0.name', 'Autre');
        $this->actingAs($user)->getJson('/api/v1/campaigns?sort=-bogus')->assertStatus(422);
    }

    public function test_list_item_exposes_v2_keys_and_rates_from_stats_counters(): void
    {
        $user = $this->operator();
        Campaign::factory()->create([
            'status' => 'completed', 'created_by' => $user->id,
            'recipients_count' => 1000, 'sent_count' => 1000, 'delivered_count' => 1000,
            'unique_opens' => 410, 'unique_clicks' => 97,
        ]);
        Campaign::factory()->create(['status' => 'draft']);

        $response = $this->actingAs($user)->getJson('/api/v1/campaigns?status_tab=sent')->assertOk();
        $response->assertJsonPath('data.0.brevo_status', 'sent');
        $response->assertJsonPath('data.0.stats_summary.open_rate', 0.41);
        $response->assertJsonPath('data.0.stats_summary.click_rate', 0.097);
        $response->assertJsonPath('data.0.owner.id', $user->uuid);
        $response->assertJsonPath('data.0.available_actions', ['report', 'send_to_new_contacts', 'archive', 'duplicate', 'move']);
        $response->assertJsonPath('data.0.smtp_strategy', 'rotation');
        $response->assertJsonPath('data.0.send_mode', 'now');
        $response->assertJsonPath('data.0.timezone', 'Africa/Algiers');

        $this->actingAs($user)->getJson('/api/v1/campaigns?status_tab=draft')->assertJsonPath('data.0.stats_summary', null);
    }

    // ---- create / show / update -------------------------------------------

    public function test_create_with_name_type_and_folder_only(): void
    {
        $user = $this->operator();
        $folder = CampaignFolder::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/v1/campaigns', [
            'name' => 'Newsletter octobre', 'type' => 'classic', 'folder_id' => $folder->uuid,
        ])->assertCreated();

        $response->assertJsonPath('data.status', 'draft');
        $response->assertJsonPath('data.brevo_status', 'draft');
        $response->assertJsonPath('data.folder_id', $folder->uuid);
        $response->assertJsonPath('data.audience.materialised_count', null);
        $response->assertJsonPath('data.checklist_status', 'incomplete');
        $response->assertJsonPath('data.available_actions', ['edit', 'test_send', 'schedule', 'send_now', 'delete', 'duplicate', 'move']);
    }

    public function test_patch_updates_setup_fields_and_audience(): void
    {
        $user = $this->operator();
        $listA = $this->listWith($this->recipients(3), 'Clients');
        $listX = $this->listWith($this->recipients(1), 'Exclus');
        $segment = $this->segmentForCompany('Acme', 'Actifs');
        $campaign = Campaign::factory()->create(['created_by' => $user->id, 'recipient_list_id' => $listA->id]);

        $response = $this->actingAs($user)->patchJson("/api/v1/campaigns/{$campaign->uuid}", [
            'subject' => 'Nos nouveautés',
            'preview_text' => 'Découvrez…',
            'reply_to' => 'contact@pagesjaunes.dz',
            'to_field' => '{{ contact.PRENOM }}',
            'tags' => ['newsletter'],
            'audience' => ['lists' => [$listA->uuid], 'exclusion_lists' => [$listX->uuid], 'segments' => [$segment->uuid]],
            'utm' => ['enabled' => true, 'source' => 'pagesjaunes', 'medium' => 'email', 'campaign' => 'oct'],
            'mirror_link_enabled' => false,
            'inline_images' => true,
            'email_expiration' => ['duration' => 30, 'unit' => 'days'],
            'tracking_enabled' => false,
            'smtp_strategy' => 'rotation',
            'send_window' => ['days' => [1, 2, 3], 'start' => '08:00', 'end' => '17:00'],
            'requires_approval' => true,
        ])->assertOk();

        $response->assertJsonPath('data.subject', 'Nos nouveautés');
        $response->assertJsonPath('data.preview_text', 'Découvrez…');
        $response->assertJsonPath('data.tags', ['newsletter']);
        $response->assertJsonPath('data.recipient_list_id', null);
        $response->assertJsonPath('data.audience.lists.0.name', 'Clients');
        $response->assertJsonPath('data.audience.lists.0.contact_count', 3);
        $response->assertJsonPath('data.audience.exclusion_lists.0.id', $listX->uuid);
        $response->assertJsonPath('data.audience.segments.0.name', 'Actifs');
        $response->assertJsonPath('data.utm.source', 'pagesjaunes');
        $response->assertJsonPath('data.utm.enabled', true);
        $response->assertJsonPath('data.mirror_link_enabled', false);
        $response->assertJsonPath('data.email_expiration', ['duration' => 30, 'unit' => 'days']);
        $response->assertJsonPath('data.tracking_enabled', false);
        $response->assertJsonPath('data.send_window', ['days' => [1, 2, 3], 'start' => '08:00', 'end' => '17:00']);
        $response->assertJsonPath('data.requires_approval', true);
        $response->assertJsonPath('data.approval_required', true);

        $this->assertSame(3, CampaignAudience::query()->where('campaign_id', $campaign->id)->count());
    }

    public function test_patch_validation_rules(): void
    {
        $user = $this->operator();
        $list = $this->listWith($this->recipients(1));
        $campaign = Campaign::factory()->create(['created_by' => $user->id]);

        $this->actingAs($user)->patchJson("/api/v1/campaigns/{$campaign->uuid}", ['subject' => str_repeat('a', 256)])
            ->assertStatus(422)->assertJsonValidationErrors('subject');
        $this->actingAs($user)->patchJson("/api/v1/campaigns/{$campaign->uuid}", ['tags' => array_fill(0, 21, 'x')])
            ->assertStatus(422)->assertJsonValidationErrors('tags');
        $this->actingAs($user)->patchJson("/api/v1/campaigns/{$campaign->uuid}", ['email_expiration' => ['duration' => 400, 'unit' => 'days']])
            ->assertStatus(422)->assertJsonValidationErrors('email_expiration.duration');
        $this->actingAs($user)->patchJson("/api/v1/campaigns/{$campaign->uuid}", ['audience' => ['lists' => [$list->uuid], 'exclusion_lists' => [$list->uuid]]])
            ->assertStatus(422)->assertJsonValidationErrors('audience.exclusion_lists');
        $this->actingAs($user)->patchJson("/api/v1/campaigns/{$campaign->uuid}", ['sender_id' => '00000000-0000-0000-0000-000000000000'])
            ->assertStatus(422)->assertJsonValidationErrors('sender_id');
    }

    public function test_patch_is_allowed_on_scheduled_and_suspended_but_not_completed(): void
    {
        $user = $this->operator();

        foreach (['scheduled' => 200, 'suspended' => 200, 'completed' => 409, 'running' => 409] as $status => $code) {
            // Ready campaign: edits of scheduled / suspended ones re-assert the checklist.
            $campaign = $this->campaignWithAudience($user, ['lists' => [$this->listWith($this->recipients(1))->id]]);
            $campaign->update(['status' => $status]);
            $this->actingAs($user)->patchJson("/api/v1/campaigns/{$campaign->uuid}", ['name' => 'X'])->assertStatus($code);
        }

        $archived = Campaign::factory()->archived()->create(['created_by' => $user->id, 'status' => 'suspended']);
        $this->actingAs($user)->patchJson("/api/v1/campaigns/{$archived->uuid}", ['name' => 'X'])->assertStatus(409);
    }

    public function test_patch_on_someone_elses_campaign_needs_send_any(): void
    {
        $owner = $this->operator();
        $campaign = Campaign::factory()->create(['created_by' => $owner->id]);

        $this->actingAs($this->operator())->patchJson("/api/v1/campaigns/{$campaign->uuid}", ['name' => 'X'])->assertForbidden();
        $this->actingAs($this->manager())->patchJson("/api/v1/campaigns/{$campaign->uuid}", ['name' => 'X'])->assertOk();
    }

    public function test_delete_only_when_delete_is_an_available_action(): void
    {
        $user = $this->operator();
        $draft = Campaign::factory()->create(['created_by' => $user->id]);
        $running = Campaign::factory()->create(['created_by' => $user->id, 'status' => 'running']);

        $this->actingAs($user)->deleteJson("/api/v1/campaigns/{$draft->uuid}")->assertNoContent();
        $this->assertDatabaseMissing('campaigns', ['id' => $draft->id]);
        $this->actingAs($user)->deleteJson("/api/v1/campaigns/{$running->uuid}")->assertStatus(409);
    }

    // ---- design -------------------------------------------------------------

    public function test_design_put_stores_editor_type_json_and_html(): void
    {
        Event::fake([CampaignDesignSaved::class]);
        $user = $this->operator();
        $campaign = Campaign::factory()->create(['created_by' => $user->id]);

        $this->actingAs($user)->putJson("/api/v1/campaigns/{$campaign->uuid}/design", [
            'editor_type' => 'dnd', 'html_body' => '<html>Salut</html>', 'design_json' => ['rows' => [['id' => 1]]],
        ])->assertOk()
            ->assertJsonPath('data.editor_type', 'dnd')
            ->assertJsonPath('data.html_body', '<html>Salut</html>')
            ->assertJsonPath('data.design_json.rows.0.id', 1);

        $this->actingAs($user)->putJson("/api/v1/campaigns/{$campaign->uuid}/design", [
            'editor_type' => 'html', 'html_body' => '<p>code</p>', 'design_json' => ['ignored' => true],
        ])->assertOk()->assertJsonPath('data.design_json', null);

        $this->actingAs($user)->putJson("/api/v1/campaigns/{$campaign->uuid}/design", ['editor_type' => 'word'])->assertStatus(422);
        Event::assertDispatched(CampaignDesignSaved::class, 2);
    }

    public function test_design_copy_from_template_campaign_and_blank(): void
    {
        $user = $this->operator();
        $campaign = Campaign::factory()->create(['created_by' => $user->id]);
        $template = Template::query()->create(['name' => 'Modèle', 'html_content' => '<p>Modèle</p>']);
        $source = Campaign::factory()->create(['editor_type' => 'dnd', 'html_body' => '<p>Source</p>', 'design_json' => ['a' => 1]]);

        $this->actingAs($user)->postJson("/api/v1/campaigns/{$campaign->uuid}/design/copy", ['source_type' => 'template', 'source_id' => $template->uuid])
            ->assertOk()->assertJsonPath('data.html_body', '<p>Modèle</p>')->assertJsonPath('data.editor_type', 'html')
            ->assertJsonPath('data.template_id', $template->uuid);

        $this->actingAs($user)->postJson("/api/v1/campaigns/{$campaign->uuid}/design/copy", ['source_type' => 'campaign', 'source_id' => $source->uuid])
            ->assertOk()->assertJsonPath('data.design_json', ['a' => 1])->assertJsonPath('data.editor_type', 'dnd');

        $this->actingAs($user)->postJson("/api/v1/campaigns/{$campaign->uuid}/design/copy", ['source_type' => 'blank'])
            ->assertOk()->assertJsonPath('data.html_body', null)->assertJsonPath('data.editor_type', 'dnd');

        $this->actingAs($user)->postJson("/api/v1/campaigns/{$campaign->uuid}/design/copy", ['source_type' => 'template'])
            ->assertStatus(422);
    }

    // ---- audience estimate --------------------------------------------------

    public function test_estimate_endpoint_for_saved_and_proposed_audience(): void
    {
        $user = $this->operator();
        $listA = $this->listWith($this->recipients(4), 'A');
        $listB = $this->listWith($this->recipients(2), 'B');
        $campaign = $this->campaignWithAudience($user, ['lists' => [$listA->id]]);

        $this->actingAs($user)->postJson("/api/v1/campaigns/{$campaign->uuid}/audience/estimate")
            ->assertOk()
            ->assertJsonPath('data.total', 4)
            ->assertJsonPath('data.by_target.0.name', 'A')
            ->assertJsonPath('data.by_target.0.mode', 'include')
            ->assertJsonStructure(['data' => ['included', 'excluded', 'suppressed', 'duplicates', 'total', 'unengaged', 'unengaged_percent', 'unengaged_warning', 'by_target', 'computed_at']]);

        $this->actingAs($user)->postJson("/api/v1/campaigns/{$campaign->uuid}/audience/estimate", [
            'audience' => ['lists' => [$listA->uuid, $listB->uuid]],
        ])->assertOk()->assertJsonPath('data.total', 6);

        $this->actingAs($user)->getJson("/api/v1/campaigns/{$campaign->uuid}")
            ->assertJsonPath('data.audience.estimated_count', 4);

        $viewer = User::factory()->withRole(RoleName::Viewer)->create();
        $this->actingAs($viewer)->postJson("/api/v1/campaigns/{$campaign->uuid}/audience/estimate")->assertForbidden();
    }

    // ---- test send --------------------------------------------------------

    public function test_test_send_goes_through_pipeline_with_is_test(): void
    {
        Bus::fake();
        Event::fake([CampaignTestSent::class]);
        $user = $this->operator();
        $contact = $this->recipients(1, ['first_name' => 'Amina'])->first();
        $campaign = Campaign::factory()->create(['created_by' => $user->id, 'subject' => 'Bonjour {{recipient.first_name|toi}}']);

        $response = $this->actingAs($user)->postJson("/api/v1/campaigns/{$campaign->uuid}/test-send", [
            'emails' => ['a@exemple.dz', 'b@exemple.dz'], 'contact_id' => $contact->uuid,
        ])->assertStatus(202);

        $response->assertJsonPath('data.queued', 2);
        $this->assertCount(2, $response->json('data.message_ids'));
        $this->assertNotNull($campaign->fresh()->test_sent_at);
        $this->assertSame(2, Message::query()->where('campaign_id', $campaign->id)->where('is_test', true)->count());
        $this->assertSame('Bonjour Amina', Message::query()->where('is_test', true)->first()->subject);
        Bus::assertDispatched(SendEmailJob::class, 2);
        Event::assertDispatched(CampaignTestSent::class);

        $this->actingAs($user)->getJson("/api/v1/campaigns/{$campaign->uuid}/recipients")->assertJsonCount(0, 'data');
    }

    public function test_test_send_limits(): void
    {
        Bus::fake();
        $user = $this->operator();
        $campaign = Campaign::factory()->create(['created_by' => $user->id]);

        $this->actingAs($user)->postJson("/api/v1/campaigns/{$campaign->uuid}/test-send", [
            'emails' => ['1@x.dz', '2@x.dz', '3@x.dz', '4@x.dz', '5@x.dz', '6@x.dz'],
        ])->assertStatus(422);

        for ($i = 0; $i < 10; $i++) {
            $this->actingAs($user)->postJson("/api/v1/campaigns/{$campaign->uuid}/test-send", ['emails' => ['t@x.dz']])->assertStatus(202);
        }
        $this->actingAs($user)->postJson("/api/v1/campaigns/{$campaign->uuid}/test-send", ['emails' => ['t@x.dz']])->assertStatus(429);
    }

    // ---- schedule ---------------------------------------------------------

    public function test_schedule_mode_scheduled_stores_timezone(): void
    {
        Bus::fake();
        $user = $this->operator();
        $campaign = $this->campaignWithAudience($user, ['lists' => [$this->listWith($this->recipients(1))->id]]);

        $this->actingAs($user)->postJson("/api/v1/campaigns/{$campaign->uuid}/schedule", [
            'mode' => 'scheduled', 'scheduled_at' => now()->addDay()->format('Y-m-d\TH:i:sP'), 'timezone' => 'Europe/Paris',
        ])->assertOk()
            ->assertJsonPath('data.status', 'scheduled')
            ->assertJsonPath('data.brevo_status', 'queued')
            ->assertJsonPath('data.timezone', 'Europe/Paris')
            ->assertJsonPath('data.send_mode', 'scheduled');

        Bus::assertDispatched(DispatchCampaignJob::class, fn (DispatchCampaignJob $j) => $j->scheduledFor !== null);
    }

    public function test_schedule_mode_now_best_time_and_batches(): void
    {
        Bus::fake();
        $user = $this->operator();
        $list = $this->listWith($this->recipients(1));

        $now = $this->campaignWithAudience($user, ['lists' => [$list->id]]);
        $this->actingAs($user)->postJson("/api/v1/campaigns/{$now->uuid}/schedule", ['mode' => 'now'])
            ->assertOk()->assertJsonPath('data.status', 'running')->assertJsonPath('data.send_mode', 'now');

        $best = $this->campaignWithAudience($user, ['lists' => [$list->id]]);
        $this->actingAs($user)->postJson("/api/v1/campaigns/{$best->uuid}/schedule", [
            'mode' => 'best_time', 'scheduled_at' => now()->addHours(2)->toIso8601String(),
        ])->assertOk()->assertJsonPath('data.status', 'scheduled')->assertJsonPath('data.send_time_optimized', true)
            ->assertJsonPath('data.send_mode', 'best_time');

        $batches = $this->campaignWithAudience($user, ['lists' => [$list->id]]);
        $this->actingAs($user)->postJson("/api/v1/campaigns/{$batches->uuid}/schedule", ['mode' => 'batches'])
            ->assertStatus(422)->assertJsonValidationErrors(['batch_size', 'batch_interval_minutes']);
        $this->actingAs($user)->postJson("/api/v1/campaigns/{$batches->uuid}/schedule", [
            'mode' => 'batches', 'batch_size' => 500, 'batch_interval_minutes' => 30,
        ])->assertOk()->assertJsonPath('data.batch', ['size' => 500, 'interval_minutes' => 30])
            ->assertJsonPath('data.status', 'running');

        $this->actingAs($user)->postJson("/api/v1/campaigns/{$batches->uuid}/schedule", ['mode' => 'scheduled', 'scheduled_at' => now()->addDay()->toIso8601String()])
            ->assertStatus(409);
    }

    public function test_schedule_without_audience_is_refused(): void
    {
        $user = $this->operator();
        $campaign = Campaign::factory()->create(['created_by' => $user->id]);

        $this->actingAs($user)->postJson("/api/v1/campaigns/{$campaign->uuid}/schedule", ['mode' => 'now'])->assertStatus(422);
    }

    public function test_schedule_refused_when_checklist_has_blocking_failures(): void
    {
        Bus::fake();
        $user = $this->operator();
        $campaign = $this->campaignWithAudience($user, ['lists' => [$this->listWith($this->recipients(1))->id]]);

        $this->app->instance(CampaignService::CHECKLIST_SERVICE, new class
        {
            public function blockingFailures($campaign): array
            {
                return ['sender_verified', 'unsubscribe_link'];
            }

            public function evaluate($campaign): array
            {
                return ['status' => 'blocking'];
            }
        });

        $this->actingAs($user)->postJson("/api/v1/campaigns/{$campaign->uuid}/schedule", ['mode' => 'now'])
            ->assertStatus(422)
            ->assertJsonPath('errors.checklist', ['sender_verified', 'unsubscribe_link']);

        $this->assertSame('draft', $campaign->fresh()->status);
        $this->actingAs($user)->getJson("/api/v1/campaigns/{$campaign->uuid}")->assertJsonPath('data.checklist_status', 'blocking');
    }

    public function test_unschedule_returns_to_draft(): void
    {
        Bus::fake();
        $user = $this->operator();
        $campaign = $this->campaignWithAudience($user, ['lists' => [$this->listWith($this->recipients(1))->id]]);
        app(CampaignService::class)->schedule($campaign, now()->addDay());

        $this->actingAs($user)->postJson("/api/v1/campaigns/{$campaign->uuid}/unschedule")
            ->assertOk()->assertJsonPath('data.status', 'draft')->assertJsonPath('data.scheduled_at', null);
        $this->actingAs($user)->postJson("/api/v1/campaigns/{$campaign->uuid}/unschedule")->assertStatus(409);
    }

    public function test_resume_from_suspended_clears_reason(): void
    {
        Bus::fake();
        $user = $this->operator();
        $campaign = Campaign::factory()->create(['status' => 'suspended', 'suspended_reason' => 'quota_exhausted']);

        $this->actingAs($user)->postJson("/api/v1/campaigns/{$campaign->uuid}/resume")
            ->assertOk()->assertJsonPath('data.status', 'running')->assertJsonPath('data.suspended_reason', null);
        Bus::assertDispatched(DispatchCampaignJob::class);
    }

    // ---- archive / duplicate / send to new contacts --------------------------

    public function test_archive_and_unarchive(): void
    {
        $user = $this->operator();
        $completed = Campaign::factory()->create(['status' => 'completed']);
        $draft = Campaign::factory()->create(['status' => 'draft']);

        $this->actingAs($user)->postJson("/api/v1/campaigns/{$completed->uuid}/archive")
            ->assertOk()->assertJsonPath('data.is_archived', true)->assertJsonPath('data.brevo_status', 'archive')
            ->assertJsonPath('data.status', 'completed');
        $this->actingAs($user)->postJson("/api/v1/campaigns/{$draft->uuid}/archive")->assertStatus(409);
        $this->actingAs($user)->postJson("/api/v1/campaigns/{$completed->uuid}/unarchive")
            ->assertOk()->assertJsonPath('data.is_archived', false)->assertJsonPath('data.brevo_status', 'sent');

        $viewer = User::factory()->withRole(RoleName::Viewer)->create();
        $this->actingAs($viewer)->postJson("/api/v1/campaigns/{$completed->uuid}/archive")->assertForbidden();
    }

    public function test_duplicate_copies_audience_design_and_settings_and_resets_stats(): void
    {
        $user = $this->operator();
        $folder = CampaignFolder::factory()->create();
        $list = $this->listWith($this->recipients(1));
        $campaign = $this->campaignWithAudience($user, ['lists' => [$list->id]], [
            'editor_type' => 'dnd', 'design_json' => ['x' => 1], 'tags' => ['t'], 'utm_source' => 'pj',
        ]);
        $campaign->update(['status' => 'completed', 'sent_count' => 10, 'delivered_count' => 9, 'test_sent_at' => now()]);

        $response = $this->actingAs($user)->postJson("/api/v1/campaigns/{$campaign->uuid}/duplicate", ['folder_id' => $folder->uuid])
            ->assertCreated();

        $response->assertJsonPath('data.name', 'Copie de '.$campaign->name);
        $response->assertJsonPath('data.status', 'draft');
        $response->assertJsonPath('data.folder_id', $folder->uuid);
        $response->assertJsonPath('data.design_json', ['x' => 1]);
        $response->assertJsonPath('data.tags', ['t']);
        $response->assertJsonPath('data.utm.source', 'pj');
        $response->assertJsonPath('data.audience.lists.0.id', $list->uuid);
        $response->assertJsonPath('data.stats_summary', null);
        $response->assertJsonPath('data.test_sent_at', null);
        $response->assertJsonPath('data.cloned_from_campaign_id', $campaign->uuid);

        $this->actingAs($user)->postJson("/api/v1/campaigns/{$campaign->uuid}/duplicate", ['name' => 'Ma copie'])
            ->assertCreated()->assertJsonPath('data.name', 'Ma copie');
    }

    public function test_send_to_new_contacts_creates_child_on_audience_delta(): void
    {
        Bus::fake();
        $user = $this->operator();
        $list = $this->listWith($this->recipients(2));
        $campaign = $this->campaignWithAudience($user, ['lists' => [$list->id]]);

        $this->actingAs($user)->postJson("/api/v1/campaigns/{$campaign->uuid}/send-to-new-contacts")->assertStatus(409);

        $campaign->update(['status' => 'running']);
        app(CampaignService::class)->runDispatchCycle($campaign);
        $this->assertSame('completed', $campaign->fresh()->status);

        $newcomer = $this->recipients(1)->first();
        $list->recipients()->syncWithoutDetaching([$newcomer->id => ['added_at' => now()]]);

        $response = $this->actingAs($user)->postJson("/api/v1/campaigns/{$campaign->uuid}/send-to-new-contacts", ['name' => 'Relance'])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Relance')
            ->assertJsonPath('data.parent_campaign_id', $campaign->uuid)
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.audience.estimated_count', 1);

        $child = Campaign::query()->where('uuid', $response->json('data.id'))->first();
        $child->update(['status' => 'running']);
        app(CampaignService::class)->runDispatchCycle($child);

        $this->assertSame([$newcomer->id], CampaignRecipient::query()->where('campaign_id', $child->id)->pluck('recipient_id')->all());
    }

    // ---- bulk / preview ------------------------------------------------------

    public function test_bulk_archive_move_and_delete(): void
    {
        $user = $this->operator();
        $folder = CampaignFolder::factory()->create();
        $completed = Campaign::factory()->create(['status' => 'completed', 'created_by' => $user->id]);
        $draft = Campaign::factory()->create(['status' => 'draft', 'created_by' => $user->id]);

        $this->actingAs($user)->postJson('/api/v1/campaigns/bulk', ['action' => 'archive', 'ids' => [$completed->uuid, $draft->uuid]])
            ->assertOk()
            ->assertJsonPath('data.processed', 1)
            ->assertJsonPath('data.skipped.0.id', $draft->uuid)
            ->assertJsonPath('data.skipped.0.reason', 'Statut incompatible');

        $this->actingAs($user)->postJson('/api/v1/campaigns/bulk', ['action' => 'move', 'ids' => [$completed->uuid, $draft->uuid], 'folder_id' => $folder->uuid])
            ->assertOk()->assertJsonPath('data.processed', 2);
        $this->assertSame($folder->id, $draft->fresh()->folder_id);

        $this->actingAs($user)->postJson('/api/v1/campaigns/bulk', ['action' => 'delete', 'ids' => [$draft->uuid]])
            ->assertOk()->assertJsonPath('data.processed', 1);
        $this->assertDatabaseMissing('campaigns', ['id' => $draft->id]);
    }

    public function test_preview_renders_for_a_contact(): void
    {
        $user = $this->operator();
        $contact = $this->recipients(1, ['first_name' => 'Amina', 'last_name' => 'Benali'])->first();
        $campaign = Campaign::factory()->create(['subject' => 'Bonjour {{recipient.first_name|toi}}', 'html_body' => '<p>{{recipient.first_name|toi}}</p>']);

        $this->actingAs($user)->getJson("/api/v1/campaigns/{$campaign->uuid}/preview?contact_id={$contact->uuid}")
            ->assertOk()
            ->assertJsonPath('data.subject', 'Bonjour Amina')
            ->assertJsonPath('data.html', '<p>Amina</p>')
            ->assertJsonPath('data.to', "Amina Benali <{$contact->email}>")
            ->assertJsonPath('data.contact_id', $contact->uuid);
    }

    /** gap-campaign-ux: without `contact_id` the preview renders merge tags with the first audience member. */
    public function test_preview_without_contact_uses_a_sample_audience_member(): void
    {
        $user = $this->operator();
        $members = $this->recipients(2, ['company_name' => 'Acme']);
        $list = $this->listWith($members);
        $campaign = $this->campaignWithAudience($user, ['lists' => [$list->id]]);
        $campaign->update(['subject' => 'Offre pour {{ contact.SOCIETE }}', 'html_body' => '<p>{{ contact.SOCIETE }}</p>']);

        $this->actingAs($user)->getJson("/api/v1/campaigns/{$campaign->uuid}/preview")
            ->assertOk()
            ->assertJsonPath('data.subject', 'Offre pour Acme')
            ->assertJsonPath('data.html', '<p>Acme</p>')
            ->assertJsonPath('data.sample', true)
            ->assertJsonPath('data.contact_id', $members->sortBy('id')->first()->uuid);

        $empty = Campaign::factory()->create(['subject' => 'Offre pour {{ contact.SOCIETE }}']);
        $this->actingAs($user)->getJson("/api/v1/campaigns/{$empty->uuid}/preview")
            ->assertOk()
            ->assertJsonPath('data.subject', 'Offre pour {{ contact.SOCIETE }}')
            ->assertJsonPath('data.sample', false)
            ->assertJsonPath('data.contact_id', null);
    }
}
