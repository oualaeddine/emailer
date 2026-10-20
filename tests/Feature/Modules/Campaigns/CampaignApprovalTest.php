<?php

namespace Tests\Feature\Modules\Campaigns;

use App\Modules\Campaigns\Jobs\DispatchCampaignJob;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Campaigns\Services\CampaignService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\Feature\Modules\Campaigns\Concerns\BuildsCampaignFixtures;
use Tests\TestCase;

/**
 * gap-approval — « Approbation requise avant l'envoi »: the approve endpoint
 * (campaigns.approve), scheduling blocked until approved, approval withdrawn
 * when the approved content changes, audit trail, checklist item.
 */
class CampaignApprovalTest extends TestCase
{
    use BuildsCampaignFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        Bus::fake();
    }

    private function approvalCampaign(?object $owner = null, array $attributes = []): Campaign
    {
        $owner ??= $this->operator();

        return $this->campaignWithAudience($owner, ['lists' => [$this->listWith($this->recipients(2))->id]], [
            'approval_required' => true,
            ...$attributes,
        ]);
    }

    public function test_manager_can_approve_and_operator_gets_403(): void
    {
        $operator = $this->operator();
        $campaign = $this->approvalCampaign($operator);

        $this->actingAs($operator)->postJson("/api/v1/campaigns/{$campaign->uuid}/approve")->assertForbidden();
        $this->assertNull($campaign->fresh()->approved_by);

        $manager = $this->manager();
        $this->actingAs($manager)->postJson("/api/v1/campaigns/{$campaign->uuid}/approve")
            ->assertOk()
            ->assertJsonPath('data.approval_status', 'approved')
            ->assertJsonPath('data.approved_by', $manager->uuid)
            ->assertJsonPath('data.approver.name', $manager->name);

        $this->assertSame($manager->id, $campaign->fresh()->approved_by);
        $this->assertNotNull($campaign->fresh()->approved_at);
    }

    public function test_resource_exposes_the_approval_status(): void
    {
        $operator = $this->operator();
        $plain = $this->campaignWithAudience($operator, ['lists' => [$this->listWith($this->recipients(1))->id]]);
        $pending = $this->approvalCampaign($operator);

        $this->actingAs($operator)->getJson("/api/v1/campaigns/{$plain->uuid}")->assertJsonPath('data.approval_status', 'not_required');
        $this->actingAs($operator)->getJson("/api/v1/campaigns/{$pending->uuid}")
            ->assertJsonPath('data.approval_status', 'pending')
            ->assertJsonPath('data.approver', null);
    }

    public function test_approving_a_campaign_that_needs_no_approval_or_is_not_a_draft_is_refused(): void
    {
        $manager = $this->manager();
        $plain = $this->campaignWithAudience($manager, ['lists' => [$this->listWith($this->recipients(1))->id]]);
        $this->actingAs($manager)->postJson("/api/v1/campaigns/{$plain->uuid}/approve")->assertStatus(409);

        $running = $this->approvalCampaign($manager);
        $running->update(['status' => 'running']);
        $this->actingAs($manager)->postJson("/api/v1/campaigns/{$running->uuid}/approve")->assertStatus(422);
    }

    public function test_scheduling_is_blocked_before_approval_and_allowed_after(): void
    {
        $operator = $this->operator();
        $campaign = $this->approvalCampaign($operator);
        $payload = ['mode' => 'scheduled', 'scheduled_at' => now()->addDay()->toIso8601String()];

        $this->actingAs($operator)->postJson("/api/v1/campaigns/{$campaign->uuid}/schedule", $payload)
            ->assertStatus(422)
            ->assertJsonFragment(['message' => 'Cette campagne doit être approuvée avant de pouvoir être envoyée.']);
        $this->assertSame('draft', $campaign->fresh()->status);

        $this->actingAs($this->manager())->postJson("/api/v1/campaigns/{$campaign->uuid}/approve")->assertOk();

        $this->actingAs($operator)->postJson("/api/v1/campaigns/{$campaign->uuid}/schedule", $payload)
            ->assertOk()->assertJsonPath('data.status', 'scheduled');
    }

    public function test_approval_is_audited(): void
    {
        $manager = $this->manager();
        $campaign = $this->approvalCampaign($manager);

        $this->actingAs($manager)->postJson("/api/v1/campaigns/{$campaign->uuid}/approve")->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'campaign.approved',
            'auditable_type' => Campaign::class,
            'auditable_id' => $campaign->id,
            'user_id' => $manager->id,
        ]);

        // Approving twice is a no-op: no second entry, same approver.
        $this->actingAs($this->manager())->postJson("/api/v1/campaigns/{$campaign->uuid}/approve")->assertOk();
        $this->assertSame(1, \App\Modules\Audit\Models\AuditLog::query()->where('action', 'campaign.approved')->count());
        $this->assertSame($manager->id, $campaign->fresh()->approved_by);
    }

    public function test_editing_the_approved_content_withdraws_the_approval(): void
    {
        $manager = $this->manager();

        foreach ([
            ['subject' => 'Nouvel objet'],
            ['preview_text' => 'Nouveau préheader'],
            ['reply_to' => 'autre@exemple.dz'],
        ] as $edit) {
            $campaign = $this->approvalCampaign($manager);
            app(CampaignService::class)->approve($campaign, $manager);

            $this->actingAs($manager)->patchJson("/api/v1/campaigns/{$campaign->uuid}", $edit)
                ->assertOk()
                ->assertJsonPath('data.approval_status', 'pending')
                ->assertJsonPath('data.approved_by', null);
        }

        $this->assertDatabaseHas('audit_logs', ['action' => 'campaign.approval_reset', 'user_id' => $manager->id]);
    }

    public function test_editing_the_design_or_the_audience_withdraws_the_approval(): void
    {
        $manager = $this->manager();
        $service = app(CampaignService::class);

        $campaign = $this->approvalCampaign($manager);
        $service->approve($campaign, $manager);
        $this->actingAs($manager)->putJson("/api/v1/campaigns/{$campaign->uuid}/design", [
            'editor_type' => 'html', 'html_body' => '<p>Autre contenu</p><a href="{{ unsubscribe }}">x</a>', 'design_json' => null,
        ])->assertOk()->assertJsonPath('data.approval_status', 'pending');

        $campaign = $this->approvalCampaign($manager);
        $service->approve($campaign, $manager);
        $other = $this->listWith($this->recipients(1), 'Autre liste');
        $this->actingAs($manager)->patchJson("/api/v1/campaigns/{$campaign->uuid}", ['audience' => ['lists' => [$other->uuid]]])
            ->assertOk()->assertJsonPath('data.approval_status', 'pending');
    }

    public function test_unrelated_edits_keep_the_approval(): void
    {
        $manager = $this->manager();
        $campaign = $this->approvalCampaign($manager);
        app(CampaignService::class)->approve($campaign, $manager);

        $this->actingAs($manager)->patchJson("/api/v1/campaigns/{$campaign->uuid}", ['name' => 'Autre nom', 'tracking_enabled' => false])
            ->assertOk()->assertJsonPath('data.approval_status', 'approved');
        // Same subject re-saved: not a change.
        $this->actingAs($manager)->patchJson("/api/v1/campaigns/{$campaign->uuid}", ['subject' => $campaign->subject])
            ->assertOk()->assertJsonPath('data.approval_status', 'approved');
    }

    public function test_editing_a_scheduled_approved_campaign_returns_it_to_draft_pending_approval(): void
    {
        $manager = $this->manager();
        $campaign = $this->approvalCampaign($manager);
        $service = app(CampaignService::class);
        $service->approve($campaign, $manager);
        $service->schedule($campaign->fresh(), now()->addDay());
        $this->assertSame('scheduled', $campaign->fresh()->status);

        $this->actingAs($manager)->patchJson("/api/v1/campaigns/{$campaign->uuid}", ['subject' => 'Changé'])
            ->assertOk()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.scheduled_at', null)
            ->assertJsonPath('data.approval_status', 'pending');

        // The delayed dispatch created by the schedule is stale and does nothing.
        $this->assertSame('draft', $campaign->fresh()->status);
    }

    public function test_enabling_the_requirement_on_a_scheduled_campaign_returns_it_to_draft(): void
    {
        $manager = $this->manager();
        $campaign = $this->approvalCampaign($manager, ['approval_required' => false]);
        app(CampaignService::class)->schedule($campaign, now()->addDay());

        $this->actingAs($manager)->patchJson("/api/v1/campaigns/{$campaign->uuid}", ['requires_approval' => true])
            ->assertOk()->assertJsonPath('data.status', 'draft')->assertJsonPath('data.approval_status', 'pending');
    }

    public function test_editing_a_suspended_campaign_is_allowed_but_resume_needs_the_approval(): void
    {
        $manager = $this->manager();
        $campaign = $this->approvalCampaign($manager);
        app(CampaignService::class)->approve($campaign, $manager);
        $campaign->update(['status' => 'suspended', 'suspended_reason' => 'quota_exhausted']);

        $this->actingAs($manager)->patchJson("/api/v1/campaigns/{$campaign->uuid}", ['subject' => 'Changé'])
            ->assertOk()->assertJsonPath('data.status', 'suspended')->assertJsonPath('data.approval_status', 'pending');

        $this->actingAs($manager)->postJson("/api/v1/campaigns/{$campaign->uuid}/resume")->assertStatus(422);

        $this->actingAs($manager)->postJson("/api/v1/campaigns/{$campaign->uuid}/approve")->assertOk();
        $this->actingAs($manager)->postJson("/api/v1/campaigns/{$campaign->uuid}/resume")
            ->assertOk()->assertJsonPath('data.status', 'running');
        Bus::assertDispatched(DispatchCampaignJob::class);
    }

    public function test_checklist_lists_the_approval_item_only_when_required(): void
    {
        $manager = $this->manager();
        $plain = $this->campaignWithAudience($manager, ['lists' => [$this->listWith($this->recipients(1))->id]]);
        $campaign = $this->approvalCampaign($manager);

        $keys = fn (Campaign $c) => collect($this->actingAs($manager)->getJson("/api/v1/campaigns/{$c->uuid}/checklist")->assertOk()->json('data.items'))->keyBy('key');

        $this->assertArrayNotHasKey('approval', $keys($plain)->all());

        $item = $keys($campaign)->get('approval');
        $this->assertSame('error', $item['status']);
        $this->assertTrue($item['blocking']);

        app(CampaignService::class)->approve($campaign, $manager);
        $this->assertSame('ok', $keys($campaign->fresh())->get('approval')['status']);
    }

    public function test_a_duplicate_of_an_approved_campaign_needs_a_new_approval(): void
    {
        $manager = $this->manager();
        $campaign = $this->approvalCampaign($manager);
        app(CampaignService::class)->approve($campaign, $manager);

        $this->actingAs($manager)->postJson("/api/v1/campaigns/{$campaign->uuid}/duplicate")
            ->assertCreated()->assertJsonPath('data.approval_status', 'pending');
    }
}
