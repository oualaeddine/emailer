<?php

namespace Tests\Feature\Modules\Recipients\Segments;

use App\Domain\Enums\RoleName;
use App\Modules\Campaigns\Enums\CampaignStatus;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Identity\Models\User;
use App\Modules\Recipients\Models\Recipient;
use App\Modules\Recipients\Models\RecipientList;
use App\Modules\Recipients\Models\Segment;
use App\Modules\Recipients\Services\RecipientListService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * docs/45-brevo-api-contracts.md §45.5.2 — /api/v1/segments endpoints,
 * permissions (§45.2), legacy compatibility (§45.10).
 */
class SegmentApiTest extends TestCase
{
    use BuildsRuleTrees;
    use RefreshDatabase;

    private User $admin;

    private User $viewer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->defineSegmentAttributes();
        $this->seed(RolePermissionSeeder::class);
        $this->admin = User::factory()->withRole(RoleName::Administrator)->create();
        $this->viewer = User::factory()->withRole(RoleName::Viewer)->create();

        foreach (['alger', 'alger', 'alger', 'oran'] as $i => $city) {
            Recipient::factory()->create(['company_name' => 'R'.$i, 'custom_fields' => ['VILLE' => $city]]);
        }
        Recipient::factory()->suppressed()->create(['company_name' => 'S', 'custom_fields' => ['VILLE' => 'alger']]);
    }

    /**
     * @return array<string, mixed>
     */
    private function algerTree(): array
    {
        return $this->tree([$this->cond('attribute.VILLE', 'equals', 'alger', ['field_type' => 'text'])]);
    }

    private function makeSegment(array $attributes = []): Segment
    {
        return Segment::factory()->create($attributes + ['rules' => $this->algerTree(), 'created_by' => $this->admin->id]);
    }

    public function test_admin_creates_a_standalone_segment(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/api/v1/segments', [
            'name' => 'Algérois',
            'description' => 'Contacts à Alger',
            'folder_id' => null,
            'rules' => $this->algerTree(),
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Algérois')
            ->assertJsonPath('data.description', 'Contacts à Alger')
            ->assertJsonPath('data.folder_id', null)
            ->assertJsonPath('data.recipient_list_id', null)
            ->assertJsonPath('data.contact_count', 4) // blocklisted/suppressed contacts are not excluded by the compiler
            ->assertJsonPath('data.rules.version', 2)
            ->assertJsonPath('data.used_in', ['campaigns' => 0, 'automations' => 0])
            ->assertJsonPath('data.created_by.id', $this->admin->uuid)
            ->assertJsonPath('data.created_by.name', $this->admin->name)
            ->assertJsonStructure(['data' => ['id', 'name', 'description', 'folder_id', 'rules', 'contact_count', 'count_computed_at', 'recipient_list_id', 'used_in', 'created_by', 'created_at', 'updated_at']]);

        $segment = Segment::query()->where('uuid', $response->json('data.id'))->firstOrFail();
        $this->assertNull($segment->recipient_list_id);
        $this->assertSame(2, $segment->rules_version);
        $this->assertDatabaseHas('audit_logs', ['action' => 'segment.created', 'auditable_id' => $segment->id, 'user_id' => $this->admin->id]);
    }

    public function test_viewer_reads_but_cannot_write(): void
    {
        $segment = $this->makeSegment();

        $this->actingAs($this->viewer)->getJson('/api/v1/segments')->assertOk();
        $this->actingAs($this->viewer)->getJson("/api/v1/segments/{$segment->uuid}")->assertOk();
        $this->actingAs($this->viewer)->postJson('/api/v1/segments/count', ['rules' => $this->algerTree()])->assertOk();
        $this->actingAs($this->viewer)->postJson("/api/v1/segments/{$segment->uuid}/count")->assertOk();
        $this->actingAs($this->viewer)->getJson("/api/v1/segments/{$segment->uuid}/members")->assertOk();
        $this->actingAs($this->viewer)->getJson('/api/v1/segments/fields')->assertOk();

        $this->actingAs($this->viewer)->postJson('/api/v1/segments', ['name' => 'X', 'rules' => $this->algerTree()])->assertForbidden();
        $this->actingAs($this->viewer)->patchJson("/api/v1/segments/{$segment->uuid}", ['name' => 'Y'])->assertForbidden();
        $this->actingAs($this->viewer)->deleteJson("/api/v1/segments/{$segment->uuid}")->assertForbidden();
        $this->actingAs($this->viewer)->postJson("/api/v1/segments/{$segment->uuid}/duplicate")->assertForbidden();
        $this->actingAs($this->viewer)->postJson('/api/v1/segments/preview', ['rules' => $this->algerTree()])->assertForbidden();
    }

    public function test_unauthenticated_is_rejected_and_unknown_segment_is_404(): void
    {
        $this->getJson('/api/v1/segments')->assertUnauthorized();
        $this->actingAs($this->admin)->getJson('/api/v1/segments/'.Str::uuid())->assertNotFound();
    }

    public function test_index_filters_sorts_and_paginates(): void
    {
        $this->makeSegment(['name' => 'Bêta']);
        $this->makeSegment(['name' => 'Alpha']);
        $this->makeSegment(['name' => 'Gamma prospects']);

        $this->actingAs($this->viewer)->getJson('/api/v1/segments?sort=name')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Alpha')
            ->assertJsonPath('data.2.name', 'Gamma prospects')
            ->assertJsonPath('meta.total', 3);

        $this->actingAs($this->viewer)->getJson('/api/v1/segments?q=PROSP')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Gamma prospects');

        $this->actingAs($this->viewer)->getJson('/api/v1/segments?folder_id=none&per_page=2')
            ->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('meta.last_page', 2);

        $this->actingAs($this->viewer)->getJson('/api/v1/segments?sort=email')->assertStatus(422)->assertJsonValidationErrors(['sort']);
    }

    public function test_folder_must_be_a_segment_folder(): void
    {
        if (! Schema::hasTable('list_folders')) {
            $this->actingAs($this->admin)->postJson('/api/v1/segments', [
                'name' => 'X', 'folder_id' => (string) Str::uuid(), 'rules' => $this->algerTree(),
            ])->assertStatus(422)->assertJsonValidationErrors(['folder_id']);

            return;
        }

        $uuid = (string) Str::uuid();
        DB::table('list_folders')->insert(['uuid' => $uuid, 'name' => 'Dossier', 'kind' => 'segment', 'created_at' => now(), 'updated_at' => now()]);

        $id = $this->actingAs($this->admin)->postJson('/api/v1/segments', ['name' => 'X', 'folder_id' => $uuid, 'rules' => $this->algerTree()])
            ->assertCreated()->assertJsonPath('data.folder_id', $uuid)->json('data.id');

        $this->actingAs($this->admin)->getJson("/api/v1/segments?folder_id={$uuid}")->assertJsonPath('data.0.id', $id);
    }

    public function test_update_is_partial_and_recomputes_count_on_rule_change(): void
    {
        $segment = $this->makeSegment(['name' => 'Avant']);

        $this->actingAs($this->admin)->patchJson("/api/v1/segments/{$segment->uuid}", ['name' => 'Après'])
            ->assertOk()->assertJsonPath('data.name', 'Après')->assertJsonPath('data.rules.children.0.value', 'alger');

        $this->actingAs($this->admin)->patchJson("/api/v1/segments/{$segment->uuid}", [
            'rules' => $this->tree([$this->cond('attribute.VILLE', 'equals', 'oran')]),
        ])->assertOk()->assertJsonPath('data.contact_count', 1)->assertJsonPath('data.name', 'Après');

        $this->assertDatabaseHas('audit_logs', ['action' => 'segment.updated', 'auditable_id' => $segment->id]);
    }

    public function test_count_endpoints(): void
    {
        $this->actingAs($this->viewer)->postJson('/api/v1/segments/count', ['rules' => $this->algerTree()])
            ->assertOk()
            ->assertJsonPath('data.count', 4)
            ->assertJsonStructure(['data' => ['count', 'computed_at', 'duration_ms']]);

        $this->actingAs($this->viewer)->postJson('/api/v1/segments/count', ['rules' => $this->tree([])])
            ->assertOk()->assertJsonPath('data.count', 5);

        $segment = $this->makeSegment();
        $this->assertNull($segment->contact_count);

        $this->actingAs($this->viewer)->postJson("/api/v1/segments/{$segment->uuid}/count")
            ->assertOk()->assertJsonPath('data.count', 4);
        $this->assertSame(4, $segment->fresh()->contact_count);
        $this->assertNotNull($segment->fresh()->count_computed_at);
    }

    public function test_members_are_cursor_paginated(): void
    {
        $segment = $this->makeSegment();

        $first = $this->actingAs($this->viewer)->getJson("/api/v1/segments/{$segment->uuid}/members?per_page=3")
            ->assertOk()->assertJsonCount(3, 'data')->assertJsonStructure(['data' => [['id', 'email']], 'links', 'meta' => ['next_cursor', 'per_page']]);

        $cursor = $first->json('meta.next_cursor');
        $this->assertNotNull($cursor);

        $second = $this->actingAs($this->viewer)->getJson("/api/v1/segments/{$segment->uuid}/members?per_page=3&cursor={$cursor}")
            ->assertOk()->assertJsonCount(1, 'data');
        $this->assertNull($second->json('meta.next_cursor'));

        $all = array_merge(array_column($first->json('data'), 'id'), array_column($second->json('data'), 'id'));
        $this->assertCount(4, array_unique($all));
    }

    public function test_duplicate(): void
    {
        $segment = $this->makeSegment(['name' => 'Source', 'description' => 'd']);

        $this->actingAs($this->admin)->postJson("/api/v1/segments/{$segment->uuid}/duplicate")
            ->assertCreated()->assertJsonPath('data.name', 'Source (copie)')->assertJsonPath('data.description', 'd')
            ->assertJsonPath('data.rules', $this->algerTree());

        $this->actingAs($this->admin)->postJson("/api/v1/segments/{$segment->uuid}/duplicate", ['name' => 'Copie 2'])
            ->assertCreated()->assertJsonPath('data.name', 'Copie 2');

        $this->assertSame(3, Segment::query()->count());
    }

    public function test_delete_is_blocked_by_scheduled_or_running_campaigns(): void
    {
        $segment = $this->makeSegment();
        $running = Campaign::factory()->status(CampaignStatus::Running)->create();
        DB::table('campaign_audiences')->insert(['campaign_id' => $running->id, 'target_type' => 'segment', 'target_id' => $segment->id, 'mode' => 'include', 'created_at' => now(), 'updated_at' => now()]);

        $this->actingAs($this->admin)->deleteJson("/api/v1/segments/{$segment->uuid}")->assertStatus(409)->assertJsonStructure(['message']);

        $legacy = $this->makeSegment();
        Campaign::factory()->status(CampaignStatus::Scheduled)->create(['segment_id' => $legacy->id]);
        $this->actingAs($this->admin)->deleteJson("/api/v1/segments/{$legacy->uuid}")->assertStatus(409);

        $free = $this->makeSegment();
        $draft = Campaign::factory()->create(['segment_id' => $free->id]);
        DB::table('campaign_audiences')->insert(['campaign_id' => $draft->id, 'target_type' => 'segment', 'target_id' => $free->id, 'mode' => 'include', 'created_at' => now(), 'updated_at' => now()]);

        $this->actingAs($this->viewer)->getJson("/api/v1/segments/{$free->uuid}")->assertJsonPath('data.used_in.campaigns', 1);

        $this->actingAs($this->admin)->deleteJson("/api/v1/segments/{$free->uuid}")->assertNoContent();
        $this->assertDatabaseMissing('segments', ['id' => $free->id]);
        $this->assertDatabaseMissing('campaign_audiences', ['target_type' => 'segment', 'target_id' => $free->id]);
        $this->assertNull($draft->fresh()->segment_id);
        $this->assertDatabaseHas('audit_logs', ['action' => 'segment.deleted', 'auditable_id' => $free->id]);
    }

    public function test_legacy_preview_routes_keep_working(): void
    {
        $legacyRules = ['operator' => 'AND', 'conditions' => [['field' => 'custom_fields.VILLE', 'operator' => 'equals', 'value' => 'alger']]];

        $response = $this->actingAs($this->admin)->postJson('/api/v1/segments/preview', ['rules' => $legacyRules])
            ->assertOk()->assertJsonStructure(['count', 'sample' => [['id', 'email']]]);
        $this->assertSame(3, $response->json('count')); // the audience preview excludes suppressed recipients (§13.7)
        $this->assertNotContains('S', array_column($response->json('sample'), 'company_name'));

        $segment = $this->makeSegment();
        $this->actingAs($this->admin)->postJson("/api/v1/segments/{$segment->uuid}/preview")
            ->assertOk()->assertJsonPath('count', 3);
        $this->actingAs($this->admin)->postJson("/api/v1/segments/{$segment->uuid}/preview", ['rules' => $this->tree([$this->cond('attribute.VILLE', 'equals', 'oran')])])
            ->assertOk()->assertJsonPath('count', 1);

        $this->actingAs($this->admin)->postJson('/api/v1/segments/preview', [])->assertStatus(422)->assertJsonValidationErrors(['rules']);
    }

    public function test_legacy_dynamic_list_segment_is_served_as_v2(): void
    {
        $list = app(RecipientListService::class)->createDynamicSegment('Liste dynamique', 'desc', [
            'operator' => 'AND',
            'conditions' => [['field' => 'status', 'operator' => 'equals', 'value' => 'active']],
        ], $this->admin);

        $segment = $list->segment;
        $this->assertSame('Liste dynamique', $segment->name);
        $this->assertSame(1, $segment->rules_version);

        $this->actingAs($this->viewer)->getJson("/api/v1/segments/{$segment->uuid}")
            ->assertOk()
            ->assertJsonPath('data.recipient_list_id', $list->uuid)
            ->assertJsonPath('data.rules.version', 2)
            ->assertJsonPath('data.rules.children.0', ['type' => 'condition', 'field' => 'contact.status', 'operator' => 'in', 'value' => ['active']]);

        // Saving stores it back as v2.
        $this->actingAs($this->admin)->patchJson("/api/v1/segments/{$segment->uuid}", ['rules' => $segment->rules])->assertOk();
        $this->assertSame(2, $segment->fresh()->rules['version']);
        $this->assertSame(2, $segment->fresh()->rules_version);
    }

    public function test_legacy_store_with_recipient_list_id(): void
    {
        $list = RecipientList::query()->create(['name' => 'Ancienne', 'type' => 'dynamic_segment']);

        $this->actingAs($this->admin)->postJson('/api/v1/segments', [
            'name' => 'Legacy', 'recipient_list_id' => $list->uuid, 'rules' => $this->algerTree(),
        ])->assertCreated()->assertJsonPath('data.recipient_list_id', $list->uuid);
    }

    public function test_fields_catalog(): void
    {
        $response = $this->actingAs($this->viewer)->getJson('/api/v1/segments/fields')->assertOk();
        $fields = collect($response->json('data.fields'))->keyBy('key');

        foreach (['attribute.EMAIL', 'attribute.PRENOM', 'contact.tags', 'contact.status', 'list', 'engagement.opened', 'engagement.complained', 'contact.created_at', 'pagejaunes.wilaya', 'pagejaunes.is_exporter'] as $key) {
            $this->assertTrue($fields->has($key), $key);
        }
        $this->assertSame(['key' => 'engagement.opened', 'label' => 'A ouvert', 'category' => 'engagement', 'type' => 'engagement'], $fields['engagement.opened']);
        $this->assertSame('category', $fields['contact.status']['type']);
        $this->assertContains('active', array_column($fields['contact.status']['options'], 'value'));
        $this->assertArrayNotHasKey('target', $fields['contact.status']);

        $this->actingAs($this->viewer)->getJson('/api/v1/segments/fields?category=pagejaunes')
            ->assertOk()->assertJsonCount(8, 'data.fields');
    }
}
