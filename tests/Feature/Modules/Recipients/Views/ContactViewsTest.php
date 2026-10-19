<?php

namespace Tests\Feature\Modules\Recipients\Views;

use App\Domain\Enums\RoleName;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Identity\Models\User;
use App\Modules\Recipients\Attributes\Models\ContactAttribute;
use App\Modules\Recipients\Models\Recipient;
use App\Modules\Recipients\Models\RecipientList;
use App\Modules\Recipients\Views\Contracts\RuleTreeCompilerName;
use App\Modules\Recipients\Views\Models\AsyncOperation;
use App\Modules\Recipients\Views\Models\ContactView;
use App\Modules\Suppression\Models\SuppressionEntry;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * docs/45 §45.5.4 — WP-57 index / views / bulk / export.
 */
class ContactViewsTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        Storage::fake('local');
        $this->manager = User::factory()->withRole(RoleName::MarketingManager)->create();
    }

    private function bindStubCompiler(): void
    {
        $this->app->bind(RuleTreeCompilerName::CONTRACT, fn () => new class
        {
            public function validate(array $tree): void {}

            public function apply(Builder $q, array $tree): Builder
            {
                foreach ($tree['children'] ?? [] as $c) {
                    $field = $c['field'];
                    if (str_starts_with($field, 'attribute.')) {
                        $q->where('custom_fields->'.substr($field, 10), $c['value']);
                    } else {
                        $q->where(substr($field, 8), $c['value']);
                    }
                }

                return $q;
            }
        });
    }

    private function tree(string $field, string $value): array
    {
        return ['version' => 2, 'type' => 'group', 'operator' => 'and', 'children' => [
            ['type' => 'condition', 'field' => $field, 'operator' => 'equals', 'value' => $value],
        ]];
    }

    public function test_cursor_index_paginates_sorts_and_selects_columns(): void
    {
        foreach (['a', 'b', 'c'] as $i => $n) {
            Recipient::factory()->create(['email' => "$n@x.dz", 'custom_fields' => ['VILLE' => ['oran', 'alger', 'blida'][$i]]]);
        }

        $r = $this->actingAs($this->manager)->getJson('/api/v1/recipients?pagination=cursor&per_page=2&sort=attribute.VILLE&columns=attribute.VILLE,tags');
        $r->assertOk()->assertJsonPath('meta.total', 3)->assertJsonPath('meta.columns', ['attribute.VILLE', 'tags']);
        $this->assertSame(['alger', 'blida'], array_column(array_column($r->json('data'), 'attributes'), 'VILLE'));

        $next = $r->json('meta.next_cursor');
        $this->assertNotNull($next);
        $r2 = $this->actingAs($this->manager)->getJson('/api/v1/recipients?pagination=cursor&per_page=2&sort=attribute.VILLE&columns=attribute.VILLE&cursor='.$next);
        $r2->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('meta.next_cursor', null);
        $this->assertSame('oran', $r2->json('data.0.attributes.VILLE'));
    }

    public function test_unknown_sort_and_column_are_rejected(): void
    {
        $this->actingAs($this->manager)->getJson('/api/v1/recipients?pagination=cursor&sort=password')->assertStatus(422);
        $this->actingAs($this->manager)->getJson('/api/v1/recipients?pagination=cursor&columns=contact.password')->assertStatus(422);
    }

    public function test_filters_use_compiler_and_scoping_works(): void
    {
        $this->bindStubCompiler();
        $inList = Recipient::factory()->create(['email' => 'in@x.dz', 'custom_fields' => ['VILLE' => 'oran']]);
        Recipient::factory()->create(['email' => 'out@x.dz', 'custom_fields' => ['VILLE' => 'alger']]);
        $list = RecipientList::query()->create(['name' => 'L', 'type' => 'static']);
        $list->recipients()->attach($inList->id);

        $filters = urlencode(json_encode($this->tree('attribute.VILLE', 'oran')));
        $this->actingAs($this->manager)->getJson("/api/v1/recipients?pagination=cursor&filters=$filters")
            ->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.email', 'in@x.dz');

        $this->actingAs($this->manager)->getJson("/api/v1/recipients?pagination=cursor&list_id={$list->uuid}")
            ->assertOk()->assertJsonPath('meta.total', 1);

        $this->actingAs($this->manager)->postJson('/api/v1/recipients/search', ['pagination' => 'cursor', 'q' => 'out'])
            ->assertOk()->assertJsonPath('data.0.email', 'out@x.dz');
    }

    public function test_filters_use_the_real_compiler_end_to_end(): void
    {
        ContactAttribute::factory()->create(['name' => 'VILLE']);
        Recipient::factory()->create(['email' => 'in@x.dz', 'custom_fields' => ['VILLE' => 'oran']]);
        Recipient::factory()->create(['email' => 'out@x.dz', 'custom_fields' => ['VILLE' => 'alger']]);

        $filters = urlencode(json_encode($this->tree('attribute.VILLE', 'oran')));
        $this->actingAs($this->manager)->getJson("/api/v1/recipients?pagination=cursor&filters=$filters")
            ->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.email', 'in@x.dz');
    }

    public function test_invalid_filter_tree_returns_422(): void
    {
        $filters = urlencode(json_encode($this->tree('attribute.UNDEFINED_ATTR', 'oran')));
        $this->actingAs($this->manager)->getJson("/api/v1/recipients?pagination=cursor&filters=$filters")
            ->assertStatus(422)->assertJsonValidationErrors('rules.children.0.field');
    }

    public function test_legacy_index_is_unchanged(): void
    {
        Recipient::factory()->count(2)->create();
        $this->actingAs($this->manager)->getJson('/api/v1/recipients')->assertOk()->assertJsonCount(2, 'data')->assertJsonStructure(['meta' => ['total']]);
    }

    public function test_show_exposes_detail_fields(): void
    {
        $r = Recipient::factory()->create(['custom_fields' => ['VILLE' => 'oran']]);
        $this->actingAs($this->manager)->getJson('/api/v1/recipients/'.$r->uuid)
            ->assertOk()->assertJsonPath('data.blocklisted', false)->assertJsonPath('data.attributes.VILLE', 'oran')
            ->assertJsonPath('data.subscription_status', 'subscribed');
    }

    public function test_views_crud_and_system_view_protection(): void
    {
        $r = $this->actingAs($this->manager)->postJson('/api/v1/contact-views', [
            'name' => 'Alger', 'is_shared' => true, 'columns' => [['key' => 'attribute.VILLE', 'width' => 120]], 'sort' => 'attribute.VILLE',
        ])->assertCreated();
        $id = $r->json('data.id');

        $list = $this->actingAs($this->manager)->getJson('/api/v1/contact-views')->assertOk();
        $this->assertSame(['all', 'mine', $id], array_column($list->json('data'), 'id'));

        $this->actingAs($this->manager)->patchJson("/api/v1/contact-views/$id", ['name' => 'Alger 2'])->assertOk()->assertJsonPath('data.name', 'Alger 2');
        $this->actingAs($this->manager)->patchJson('/api/v1/contact-views/all', ['name' => 'x'])->assertStatus(409);
        $this->actingAs($this->manager)->deleteJson('/api/v1/contact-views/mine')->assertStatus(409);

        $operator = User::factory()->withRole(RoleName::Viewer)->create();
        $this->actingAs($operator)->patchJson("/api/v1/contact-views/$id", ['name' => 'hack'])->assertForbidden();

        $this->actingAs($this->manager)->postJson('/api/v1/contact-views', ['name' => 'Bad', 'sort' => 'secret'])->assertStatus(422);
        $this->actingAs($this->manager)->deleteJson("/api/v1/contact-views/$id")->assertNoContent();
        $this->assertSame(0, ContactView::query()->count());
    }

    public function test_view_id_applies_view_defaults(): void
    {
        $this->bindStubCompiler();
        Recipient::factory()->create(['custom_fields' => ['VILLE' => 'oran']]);
        Recipient::factory()->create(['custom_fields' => ['VILLE' => 'alger']]);
        $view = ContactView::factory()->for($this->manager, 'owner')->create(['filters' => $this->tree('attribute.VILLE', 'oran')]);

        $this->actingAs($this->manager)->getJson("/api/v1/recipients?pagination=cursor&view_id={$view->uuid}")
            ->assertOk()->assertJsonPath('meta.total', 1);
    }

    public function test_bulk_list_actions_and_all_matching_selection(): void
    {
        $this->bindStubCompiler();
        $a = Recipient::factory()->create(['custom_fields' => ['VILLE' => 'oran']]);
        $b = Recipient::factory()->create(['custom_fields' => ['VILLE' => 'oran']]);
        Recipient::factory()->create(['custom_fields' => ['VILLE' => 'alger']]);
        $list = RecipientList::query()->create(['name' => 'L', 'type' => 'static']);

        $this->actingAs($this->manager)->postJson('/api/v1/recipients/bulk', [
            'action' => 'add_to_list', 'selection' => ['ids' => [$a->uuid]], 'params' => ['list_id' => $list->uuid],
        ])->assertOk()->assertJsonPath('data.processed', 1);

        $this->actingAs($this->manager)->postJson('/api/v1/recipients/bulk', [
            'action' => 'add_to_list',
            'selection' => ['all_matching' => ['filters' => $this->tree('attribute.VILLE', 'oran')]],
            'params' => ['list_id' => $list->uuid],
        ])->assertOk()->assertJsonPath('data.processed', 1)->assertJsonPath('data.skipped.0.id', $a->uuid);
        $this->assertSame(2, $list->recipients()->count());

        $this->actingAs($this->manager)->postJson('/api/v1/recipients/bulk', [
            'action' => 'remove_from_list',
            'selection' => ['all_matching' => ['list_id' => $list->uuid], 'exclude_ids' => [$b->uuid]],
            'params' => ['list_id' => $list->uuid],
        ])->assertOk()->assertJsonPath('data.processed', 1);
        $this->assertSame(1, $list->recipients()->count());
    }

    public function test_bulk_selection_requires_exactly_one_mode(): void
    {
        $this->actingAs($this->manager)->postJson('/api/v1/recipients/bulk', ['action' => 'delete', 'selection' => []])->assertStatus(422);
        $this->actingAs($this->manager)->postJson('/api/v1/recipients/bulk', [
            'action' => 'delete', 'selection' => ['ids' => ['x'], 'all_matching' => ['q' => 'a']],
        ])->assertStatus(422);
    }

    public function test_bulk_blocklist_unblocklist_update_attribute_and_delete(): void
    {
        $a = Recipient::factory()->create();
        $b = Recipient::factory()->create();

        $this->actingAs($this->manager)->postJson('/api/v1/recipients/bulk', [
            'action' => 'blocklist', 'selection' => ['ids' => [$a->uuid]],
        ])->assertOk()->assertJsonPath('data.processed', 1);
        $this->assertTrue(SuppressionEntry::query()->where('email', $a->email)->exists());
        $this->assertDatabaseHas('audit_logs', ['action' => 'contacts.bulk_blocklisted']);

        $this->actingAs($this->manager)->postJson('/api/v1/recipients/bulk', [
            'action' => 'unblocklist', 'selection' => ['ids' => [$a->uuid]],
        ])->assertOk()->assertJsonPath('data.processed', 1);
        $this->assertFalse(SuppressionEntry::query()->where('email', $a->email)->exists());
        $this->assertSame('active', $a->fresh()->status);

        $this->actingAs($this->manager)->postJson('/api/v1/recipients/bulk', [
            'action' => 'update_attribute', 'selection' => ['ids' => [$a->uuid, $b->uuid]], 'params' => ['attribute' => 'ville', 'value' => 'oran'],
        ])->assertOk()->assertJsonPath('data.processed', 2);
        $this->assertSame('oran', $a->fresh()->custom_fields['VILLE']);

        $this->actingAs($this->manager)->postJson('/api/v1/recipients/bulk', [
            'action' => 'delete', 'selection' => ['ids' => [$a->uuid]],
        ])->assertOk()->assertJsonPath('data.processed', 1);
        $this->assertSoftDeleted('recipients', ['id' => $a->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'contacts.bulk_deleted']);
    }

    public function test_bulk_over_sync_limit_returns_async_operation(): void
    {
        Recipient::factory()->count(1001)->create();
        $r = $this->actingAs($this->manager)->postJson('/api/v1/recipients/bulk', [
            'action' => 'update_attribute', 'selection' => ['all_matching' => ['scope' => 'all']], 'params' => ['attribute' => 'TAG', 'value' => 'x'],
        ]);
        $r->assertStatus(202)->assertJsonPath('data.type', 'contacts_bulk');
        $op = AsyncOperation::query()->firstOrFail();
        $this->assertSame('completed', $op->status); // sync queue in tests
        $this->assertSame(1001, $op->processed);
        $this->actingAs($this->manager)->getJson('/api/v1/operations/'.$op->uuid)->assertOk()->assertJsonPath('data.status', 'completed');
    }

    public function test_bulk_permissions(): void
    {
        $operator = User::factory()->withRole(RoleName::MarketingOperator)->create();
        $viewer = User::factory()->withRole(RoleName::Viewer)->create();
        $r = Recipient::factory()->create();

        $this->actingAs($viewer)->postJson('/api/v1/recipients/bulk', ['action' => 'delete', 'selection' => ['ids' => [$r->uuid]]])->assertForbidden();
        $this->actingAs($operator)->postJson('/api/v1/recipients/bulk', ['action' => 'blocklist', 'selection' => ['ids' => [$r->uuid]]])->assertForbidden();
    }

    public function test_export_csv_job_download_and_audit(): void
    {
        Recipient::factory()->create(['email' => 'amina@x.dz', 'first_name' => 'Amina', 'custom_fields' => ['VILLE' => 'oran']]);

        $r = $this->actingAs($this->manager)->postJson('/api/v1/recipients/export', [
            'selection' => ['all_matching' => ['q' => 'amina']], 'columns' => ['attribute.EMAIL', 'attribute.PRENOM', 'attribute.VILLE'], 'format' => 'csv',
        ])->assertStatus(202)->assertJsonPath('data.type', 'contacts_export');

        $op = AsyncOperation::query()->firstOrFail();
        $this->assertSame('completed', $op->status);
        $url = $op->result['download_url'];
        $this->assertSame(1, $op->result['row_count']);

        $download = $this->get($url)->assertOk();
        $content = $download->streamedContent() ?: file_get_contents($download->baseResponse->getFile()->getPathname());
        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);
        $this->assertStringContainsString('amina@x.dz;Amina;oran', $content);
        $this->assertDatabaseHas('audit_logs', ['action' => 'contacts.exported', 'user_id' => $this->manager->id]);
        $this->get('/api/v1/exports/'.$op->uuid.'/download')->assertForbidden(); // unsigned
    }

    public function test_export_xlsx_and_permissions(): void
    {
        Recipient::factory()->create();
        $this->actingAs($this->manager)->postJson('/api/v1/recipients/export', [
            'selection' => ['all_matching' => ['scope' => 'all']], 'format' => 'xlsx',
        ])->assertStatus(202);
        $op = AsyncOperation::query()->firstOrFail();
        $this->assertSame('completed', $op->status);
        Storage::disk('local')->assertExists($op->payload['path']);

        $viewer = User::factory()->withRole(RoleName::Viewer)->create();
        $this->actingAs($viewer)->postJson('/api/v1/recipients/export', ['selection' => ['all_matching' => ['scope' => 'all']]])->assertForbidden();
        $this->actingAs($viewer)->getJson('/api/v1/operations/'.$op->uuid)->assertForbidden();
    }

    public function test_column_preferences(): void
    {
        $this->actingAs($this->manager)->patchJson('/api/v1/me/column-preferences', [
            'page' => 'contacts', 'columns' => [['key' => 'attribute.VILLE', 'width' => 100]],
        ])->assertOk()->assertJsonPath('data.columns.0.key', 'attribute.VILLE');
    }
}
