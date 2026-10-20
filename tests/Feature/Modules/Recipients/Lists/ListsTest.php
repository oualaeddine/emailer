<?php

namespace Tests\Feature\Modules\Recipients\Lists;

use App\Domain\Enums\RoleName;
use App\Modules\Campaigns\Enums\CampaignStatus;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Identity\Models\User;
use App\Modules\Recipients\Lists\Contracts\ListMembershipService;
use App\Modules\Recipients\Lists\Models\ListFolder;
use App\Modules\Recipients\Models\Recipient;
use App\Modules\Recipients\Events\RecipientDeleted;
use App\Modules\Recipients\Models\RecipientList;
use App\Modules\Suppression\Listeners\RecipientBlocklistChanged;
use Database\Factories\ListFolderFactory;
use Database\Factories\RecipientListFactory;
use Database\Factories\SegmentFactory;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** docs/45-brevo-api-contracts.md §45.5.6 — WP-53. */
class ListsTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->manager = User::factory()->withRole(RoleName::MarketingManager)->create();
    }

    public function test_create_list_in_folder_and_show(): void
    {
        $folder = ListFolderFactory::new()->create();

        $res = $this->actingAs($this->manager)->postJson('/api/v1/recipient-lists', [
            'name' => 'Clients 2025', 'description' => 'VIP', 'folder_id' => $folder->uuid,
        ])->assertCreated()->assertJsonPath('data.folder_id', $folder->uuid)
            ->assertJsonPath('data.contact_count', 0)->assertJsonPath('data.type', 'static');

        $this->getJson('/api/v1/recipient-lists/'.$res->json('data.id'))->assertOk()
            ->assertJsonPath('data.name', 'Clients 2025')->assertJsonPath('data.created_by.id', $this->manager->uuid);
    }

    public function test_viewer_cannot_create_but_can_list(): void
    {
        $viewer = User::factory()->withRole(RoleName::Viewer)->create();
        RecipientListFactory::new()->count(2)->create();

        $this->actingAs($viewer)->postJson('/api/v1/recipient-lists', ['name' => 'X'])->assertForbidden();
        $this->actingAs($viewer)->getJson('/api/v1/recipient-lists')->assertOk()
            ->assertJsonCount(2, 'data')->assertJsonStructure(['meta' => ['total']]);
    }

    public function test_index_filters_sorts_and_rejects_bad_sort(): void
    {
        $folder = ListFolderFactory::new()->create();
        RecipientListFactory::new()->create(['name' => 'Alpha', 'folder_id' => $folder->id, 'contact_count' => 5]);
        RecipientListFactory::new()->create(['name' => 'Beta', 'contact_count' => 9]);

        $this->actingAs($this->manager);
        $this->getJson('/api/v1/recipient-lists?folder_id='.$folder->uuid)->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Alpha');
        $this->getJson('/api/v1/recipient-lists?folder_id=none')->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Beta');
        $this->getJson('/api/v1/recipient-lists?q=alp')->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/recipient-lists?sort=-contact_count')->assertJsonPath('data.0.name', 'Beta');
        $this->getJson('/api/v1/recipient-lists?sort=email')->assertStatus(422);
    }

    public function test_patch_renames_and_moves(): void
    {
        $folder = ListFolderFactory::new()->create();
        $list = RecipientListFactory::new()->create();

        $this->actingAs($this->manager)->patchJson('/api/v1/recipient-lists/'.$list->uuid, [
            'name' => 'Renommée', 'folder_id' => $folder->uuid,
        ])->assertOk()->assertJsonPath('data.name', 'Renommée')->assertJsonPath('data.folder_id', $folder->uuid);

        $this->patchJson('/api/v1/recipient-lists/'.$list->uuid, ['folder_id' => null])
            ->assertOk()->assertJsonPath('data.folder_id', null);
    }

    public function test_membership_add_remove_updates_count_cache(): void
    {
        $list = RecipientListFactory::new()->create();
        $recipients = Recipient::factory()->count(3)->create();
        $this->actingAs($this->manager);

        $this->postJson("/api/v1/recipient-lists/{$list->uuid}/contacts", [
            'action' => 'add', 'recipient_ids' => $recipients->pluck('uuid')->all(),
        ])->assertOk()->assertJsonPath('data.added', 3);
        $this->assertSame(3, $list->fresh()->contact_count);

        $this->postJson("/api/v1/recipient-lists/{$list->uuid}/contacts", [
            'action' => 'add', 'emails' => [$recipients[0]->email, 'inconnu@exemple.dz'],
        ])->assertOk()->assertJsonPath('data.added', 0)->assertJsonPath('data.already', 1)
            ->assertJsonPath('data.not_found', ['inconnu@exemple.dz']);
        $this->assertDatabaseMissing('recipients', ['email' => 'inconnu@exemple.dz']);

        $this->postJson("/api/v1/recipient-lists/{$list->uuid}/contacts", [
            'action' => 'remove', 'recipient_ids' => [$recipients[0]->uuid],
        ])->assertOk()->assertJsonPath('data.removed', 1);
        $this->assertSame(2, $list->fresh()->contact_count);
        $this->assertSame('manual', DB::table('recipient_list_recipient')->value('source'));

        $this->postJson("/api/v1/recipient-lists/{$list->uuid}/contacts", ['action' => 'add'])->assertStatus(422);
    }

    public function test_membership_service_contract_is_resolvable(): void
    {
        $list = RecipientListFactory::new()->create();
        $r = Recipient::factory()->create();
        $service = app(ListMembershipService::class);

        $this->assertSame(1, $service->add($list, [$r->id], 'api'));
        $this->assertSame(1, $list->fresh()->contact_count);
        $service->recount($list);
    }

    public function test_selection_ids_work_and_all_matching_goes_async(): void
    {
        $list = RecipientListFactory::new()->create();
        $r = Recipient::factory()->create();
        $this->actingAs($this->manager);

        $this->postJson("/api/v1/recipient-lists/{$list->uuid}/contacts", [
            'action' => 'add', 'selection' => ['ids' => [$r->uuid]],
        ])->assertOk()->assertJsonPath('data.added', 1);

        $this->postJson("/api/v1/recipient-lists/{$list->uuid}/contacts", [
            'action' => 'remove', 'selection' => ['all_matching' => ['list_id' => $list->uuid]],
        ])->assertStatus(202)->assertJsonPath('data.type', 'contacts_bulk');
        // Sync queue in tests: the job already ran.
        $this->assertSame(0, $list->fresh()->contact_count);
    }

    public function test_delete_list_and_conflict_when_used_by_scheduled_campaign(): void
    {
        $used = RecipientListFactory::new()->create();
        $free = RecipientListFactory::new()->create();
        $campaign = Campaign::factory()->status(CampaignStatus::Scheduled)->create();
        DB::table('campaign_audiences')->insert([
            'campaign_id' => $campaign->id, 'target_type' => 'list', 'target_id' => $used->id,
            'mode' => 'include', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->actingAs($this->manager);

        $this->deleteJson('/api/v1/recipient-lists/'.$used->uuid)->assertStatus(409);
        $this->deleteJson('/api/v1/recipient-lists/'.$free->uuid)->assertNoContent();
        $this->assertSoftDeleted('recipient_lists', ['id' => $free->id]);

        $campaign->update(['status' => CampaignStatus::Draft->value]);
        $this->deleteJson('/api/v1/recipient-lists/'.$used->uuid)->assertNoContent();
    }

    public function test_delete_with_contacts_removes_members(): void
    {
        $list = RecipientListFactory::new()->create();
        $r = Recipient::factory()->create();
        app(ListMembershipService::class)->add($list, [$r->id], 'manual');

        $this->actingAs($this->manager)->deleteJson('/api/v1/recipient-lists/'.$list->uuid.'?delete_contacts=1')->assertNoContent();
        $this->assertSoftDeleted('recipients', ['id' => $r->id]);
    }

    public function test_folders_crud_and_children_fall_back_to_no_folder(): void
    {
        $this->actingAs($this->manager);
        $id = $this->postJson('/api/v1/list-folders', ['name' => 'Clients'])->assertCreated()
            ->assertJsonPath('data.kind', 'list')->json('data.id');
        $this->postJson('/api/v1/list-folders', ['name' => 'Clients'])->assertStatus(422);
        $this->postJson('/api/v1/list-folders', ['name' => 'Clients', 'kind' => 'segment'])->assertCreated();

        $folder = ListFolder::query()->where('uuid', $id)->first();
        $list = RecipientListFactory::new()->create(['folder_id' => $folder->id]);

        $this->getJson('/api/v1/list-folders')->assertJsonCount(1, 'data')->assertJsonPath('data.0.items_count', 1);
        $this->getJson('/api/v1/list-folders?kind=segment')->assertJsonCount(1, 'data');
        $this->patchJson("/api/v1/list-folders/{$id}", ['name' => 'VIP'])->assertOk()->assertJsonPath('data.name', 'VIP');
        $this->deleteJson("/api/v1/list-folders/{$id}")->assertNoContent();
        $this->assertNull($list->fresh()->folder_id);
        $this->assertNull($list->fresh()->deleted_at);
    }

    public function test_folder_delete_children_is_blocked_when_in_use(): void
    {
        $folder = ListFolderFactory::new()->create();
        $list = RecipientListFactory::new()->create(['folder_id' => $folder->id]);
        $campaign = Campaign::factory()->status(CampaignStatus::Running)->create();
        DB::table('campaign_audiences')->insert([
            'campaign_id' => $campaign->id, 'target_type' => 'list', 'target_id' => $list->id,
            'mode' => 'include', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($this->manager)->deleteJson("/api/v1/list-folders/{$folder->uuid}?delete_children=1")->assertStatus(409);
        $this->assertNotNull(ListFolder::query()->find($folder->id));
    }

    public function test_export_job_writes_csv_and_serves_signed_link(): void
    {
        Storage::fake('local');
        $list = RecipientListFactory::new()->create();
        $r = Recipient::factory()->create(['email' => 'export@exemple.dz']);
        app(ListMembershipService::class)->add($list, [$r->id], 'manual');
        $this->actingAs($this->manager);

        $res = $this->postJson("/api/v1/recipient-lists/{$list->uuid}/export")->assertStatus(202)
            ->assertJsonPath('data.type', 'contacts_export');
        $this->getJson($res->json('data.status_url'))->assertOk()->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.result.row_count', 1)->assertJsonMissingPath('data.result.path');

        $url = $this->getJson($res->json('data.status_url'))->json('data.result.download_url');
        $this->get($url)->assertOk();
        $this->get(preg_replace('/signature=[^&]+/', 'signature=bad', $url))->assertForbidden();
        $this->assertDatabaseHas('audit_logs', ['action' => 'contacts.exported']);
        $this->assertStringContainsString('export@exemple.dz', Storage::disk('local')->get(
            collect(Storage::disk('local')->files('exports'))->first(),
        ));
    }

    public function test_web_pages_render_with_permission(): void
    {
        $this->actingAs($this->manager)->get('/contacts/lists')->assertOk();
        $this->get('/contacts/lists/abc')->assertOk();
    }

    public function test_export_requires_contacts_export_permission(): void
    {
        $viewer = User::factory()->withRole(RoleName::Viewer)->create();
        $list = RecipientListFactory::new()->create();

        $this->actingAs($viewer)->postJson("/api/v1/recipient-lists/{$list->uuid}/export")->assertForbidden();
    }

    public function test_export_neutralises_formula_injection(): void
    {
        Storage::fake('local');
        $list = RecipientListFactory::new()->create();
        $r = Recipient::factory()->create(['email' => 'f@exemple.dz', 'first_name' => '=HYPERLINK("http://x")', 'last_name' => '@cmd']);
        app(ListMembershipService::class)->add($list, [$r->id], 'manual');

        $this->actingAs($this->manager)->postJson("/api/v1/recipient-lists/{$list->uuid}/export")->assertStatus(202);

        $csv = Storage::disk('local')->get(collect(Storage::disk('local')->files('exports'))->first());
        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertStringContainsString("'@cmd", $csv);
    }

    public function test_selection_with_segment_only_adds_the_segments_members(): void
    {
        $list = RecipientListFactory::new()->create();
        $in = Recipient::factory()->create(['company_name' => 'Atlas']);
        Recipient::factory()->create(['company_name' => 'Other']);
        $this->seed(\Database\Seeders\ContactAttributeSeeder::class);
        $segment = SegmentFactory::new()->withRules(['version' => 2, 'type' => 'group', 'operator' => 'and', 'children' => [
            ['type' => 'condition', 'field' => 'attribute.SOCIETE', 'operator' => 'equals', 'value' => 'Atlas'],
        ]])->create();

        $this->actingAs($this->manager)->postJson("/api/v1/recipient-lists/{$list->uuid}/contacts", [
            'action' => 'add', 'selection' => ['all_matching' => ['segment_id' => $segment->uuid]],
        ])->assertStatus(202)->assertJsonPath('data.status_url', fn ($u) => str_starts_with($u, '/api/v1/operations/'));

        $this->assertSame([$in->id], DB::table('recipient_list_recipient')->where('recipient_list_id', $list->id)->pluck('recipient_id')->all());
    }

    public function test_rules_are_rejected_on_create_and_dynamic_lists_hidden_from_index(): void
    {
        $this->actingAs($this->manager)->postJson('/api/v1/recipient-lists', ['name' => 'Dyn', 'rules' => ['x' => 1]])
            ->assertStatus(422)->assertJsonValidationErrors('rules');

        RecipientListFactory::new()->create(['name' => 'Legacy', 'type' => 'dynamic_segment']);
        RecipientListFactory::new()->create(['name' => 'Static']);
        $this->getJson('/api/v1/recipient-lists')->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Static');
        $this->getJson('/api/v1/recipient-lists?include_legacy=1')->assertJsonCount(2, 'data');
    }

    public function test_deleting_or_blocklisting_a_contact_recounts_its_lists(): void
    {
        $list = RecipientListFactory::new()->create();
        $keep = Recipient::factory()->create();
        $gone = Recipient::factory()->create();
        app(ListMembershipService::class)->add($list, [$keep->id, $gone->id], 'manual');
        $this->assertSame(2, $list->fresh()->contact_count);

        $keep->forceFill(['blocklisted_at' => now()])->saveQuietly();
        RecipientBlocklistChanged::dispatch($keep->id, true, 'manual');
        $this->assertSame(1, $list->fresh()->blocklisted_count);

        $gone->delete();
        event(new RecipientDeleted($gone->id, $gone->email));
        $this->assertSame(1, $list->fresh()->contact_count);
    }

    public function test_delete_with_contacts_recounts_other_lists(): void
    {
        $a = RecipientListFactory::new()->create();
        $b = RecipientListFactory::new()->create();
        $r = Recipient::factory()->create();
        app(ListMembershipService::class)->add($a, [$r->id], 'manual');
        app(ListMembershipService::class)->add($b, [$r->id], 'manual');

        $this->actingAs($this->manager)->deleteJson('/api/v1/recipient-lists/'.$a->uuid.'?delete_contacts=1')->assertNoContent();

        $this->assertSame(0, $b->fresh()->contact_count);
    }
}
