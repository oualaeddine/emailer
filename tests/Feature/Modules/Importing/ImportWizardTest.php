<?php

namespace Tests\Feature\Modules\Importing;

use App\Domain\Enums\RoleName;
use App\Modules\Identity\Models\User;
use App\Modules\Importing\Models\ImportJob;
use App\Modules\Recipients\Models\Recipient;
use App\Modules\Recipients\Models\RecipientList;
use App\Modules\Suppression\Models\SuppressionEntry;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * docs/45-brevo-api-contracts.md §45.5.7 — import wizard + history.
 */
class ImportWizardTest extends TestCase
{
    use RefreshDatabase;

    private User $operator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        Storage::fake('local');
        $this->operator = User::factory()->withRole(RoleName::MarketingOperator)->create();
    }

    private function csv(): string
    {
        return implode("\n", [
            'Courriel;Prénom;Nom de famille;Ville',
            'alice@example.com;Alice;Martin;Alger',
            'bob@example.com;Bob;Durand;Oran',
            'bad-email;Carla;Petit;Blida',
            'alice@example.com;Alice;Martin;Alger',
        ]);
    }

    /** @return array{0: string, 1: array<string, mixed>} */
    private function upload(?string $content = null, string $name = 'contacts.csv'): array
    {
        $response = $this->actingAs($this->operator)->postJson('/api/v1/import-jobs', [
            'source_type' => 'csv',
            'file' => UploadedFile::fake()->createWithContent($name, $content ?? $this->csv()),
        ])->assertCreated();

        return [$response->json('data.id'), $response->json('data.preview')];
    }

    private function mapping(array $overrides = []): array
    {
        return array_replace([
            ['column' => 0, 'header' => 'Courriel', 'target' => ['type' => 'attribute', 'attribute' => 'EMAIL']],
            ['column' => 1, 'header' => 'Prénom', 'target' => ['type' => 'attribute', 'attribute' => 'PRENOM']],
            ['column' => 2, 'header' => 'Nom de famille', 'target' => ['type' => 'attribute', 'attribute' => 'NOM']],
            ['column' => 3, 'header' => 'Ville', 'target' => ['type' => 'attribute', 'attribute' => 'SMS']],
        ], $overrides);
    }

    private function prepare(array $settings, ?array $mapping = null, ?string $content = null): string
    {
        [$id] = $this->upload($content);
        $this->actingAs($this->operator)->putJson("/api/v1/import-jobs/{$id}/mapping", ['mapping' => $mapping ?? $this->mapping()])
            ->assertOk();
        $this->actingAs($this->operator)->putJson("/api/v1/import-jobs/{$id}/settings", $settings)->assertOk();

        return $id;
    }

    public function test_upload_returns_preview_with_suggested_mapping(): void
    {
        [, $preview] = $this->upload();

        $this->assertSame(['Courriel', 'Prénom', 'Nom de famille', 'Ville'], $preview['headers']);
        $this->assertSame(4, $preview['total_rows']);
        $this->assertSame(';', $preview['delimiter']);
        $this->assertCount(4, $preview['rows']);
        $this->assertSame('EMAIL', $preview['suggested_mapping'][0]['target']['attribute']);
        $this->assertSame('PRENOM', $preview['suggested_mapping'][1]['target']['attribute']);
        $this->assertSame('NOM', $preview['suggested_mapping'][2]['target']['attribute']);
        $this->assertSame('ignore', $preview['suggested_mapping'][3]['target']['type']);
    }

    public function test_paste_source_creates_a_job_with_preview(): void
    {
        $response = $this->actingAs($this->operator)->postJson('/api/v1/import-jobs', [
            'source_type' => 'paste',
            'content' => "email,prenom\na@exemple.dz,Amina\nb@exemple.dz,Yacine",
        ])->assertCreated();

        $this->assertSame(2, $response->json('data.preview.total_rows'));
        $this->assertSame('paste', $response->json('data.source_type'));
    }

    public function test_mapping_requires_exactly_one_email_column(): void
    {
        [$id] = $this->upload();

        $this->actingAs($this->operator)->putJson("/api/v1/import-jobs/{$id}/mapping", [
            'mapping' => [['column' => 1, 'header' => 'Prénom', 'target' => ['type' => 'attribute', 'attribute' => 'PRENOM']]],
        ])->assertUnprocessable()->assertJsonValidationErrors('mapping');
    }

    public function test_unknown_attribute_is_rejected(): void
    {
        [$id] = $this->upload();

        $this->actingAs($this->operator)->putJson("/api/v1/import-jobs/{$id}/mapping", [
            'mapping' => $this->mapping([3 => ['column' => 3, 'header' => 'Ville', 'target' => ['type' => 'attribute', 'attribute' => 'VILLE']]]),
        ])->assertUnprocessable()->assertJsonValidationErrors('mapping.3.target.attribute');
    }

    public function test_inline_attribute_creation_requires_attributes_manage(): void
    {
        [$id] = $this->upload();
        $payload = [
            'mapping' => $this->mapping([3 => ['column' => 3, 'header' => 'Ville', 'target' => ['type' => 'attribute', 'attribute' => 'VILLE']]]),
            'create_attributes' => [['name' => 'VILLE', 'type' => 'text']],
        ];

        $this->actingAs($this->operator)->putJson("/api/v1/import-jobs/{$id}/mapping", $payload)->assertForbidden();

        $manager = User::factory()->withRole(RoleName::MarketingManager)->create();
        $this->actingAs($manager)->putJson("/api/v1/import-jobs/{$id}/mapping", $payload)->assertOk();
    }

    public function test_mapping_is_applied_to_staged_rows_and_unmapped_columns_are_ignored(): void
    {
        $id = $this->prepare(['list_ids' => [RecipientList::query()->create(['name' => 'L', 'type' => 'static'])->uuid], 'update_existing' => true],
            $this->mapping([3 => ['column' => 3, 'header' => 'Ville', 'target' => ['type' => 'ignore']]]));

        $rows = $this->actingAs($this->operator)->getJson("/api/v1/import-jobs/{$id}/rows")->json('data');
        $alice = collect($rows)->firstWhere('row_number', 1);

        $this->assertSame('Alice', $alice['raw_data']['first_name']);
        $this->assertSame('Martin', $alice['raw_data']['last_name']);
        $this->assertArrayNotHasKey('custom_fields', $alice['raw_data']);
        $this->assertSame('ready', ImportJob::query()->where('uuid', $id)->value('status'));
    }

    public function test_full_wizard_commit_creates_contacts_lists_and_summary(): void
    {
        $existingList = RecipientList::query()->create(['name' => 'Clients', 'type' => 'static']);
        $id = $this->prepare([
            'list_ids' => [$existingList->uuid],
            'new_list' => ['name' => 'Salon 2026', 'folder_id' => null],
            'update_existing' => true,
        ]);

        $response = $this->actingAs($this->operator)->postJson("/api/v1/import-jobs/{$id}/commit");
        $response->assertStatus(202);

        $job = $this->actingAs($this->operator)->getJson("/api/v1/import-jobs/{$id}")->assertOk();
        $job->assertJsonPath('data.status', 'completed_with_errors');
        $job->assertJsonPath('data.summary.created', 2);
        $job->assertJsonPath('data.summary.updated', 0);
        $job->assertJsonPath('data.summary.skipped', 1);
        $job->assertJsonPath('data.summary.failed', 1);
        $job->assertJsonPath('data.summary.added_to_lists', 4);
        $job->assertJsonPath('data.user.name', $this->operator->name);

        $alice = Recipient::query()->where('email', 'alice@example.com')->firstOrFail();
        $this->assertSame('Alice', $alice->first_name);
        $this->assertSame('Alger', $alice->custom_fields['SMS']);

        $salon = RecipientList::query()->where('name', 'Salon 2026')->firstOrFail();
        $this->assertSame(2, $salon->recipients()->count());
        $this->assertSame(2, $existingList->recipients()->count());
        $this->assertSame($salon->id, ImportJob::query()->where('uuid', $id)->value('created_list_id'));
    }

    public function test_update_existing_merges_values_and_skips_empty_cells_by_default(): void
    {
        $list = RecipientList::query()->create(['name' => 'L', 'type' => 'static']);
        $bob = Recipient::factory()->create(['email' => 'bob@example.com', 'first_name' => 'Old', 'last_name' => 'Keep']);

        $csv = "Courriel;Prénom;Nom de famille;Ville\nbob@example.com;Robert;;Oran";
        $id = $this->prepare(['list_ids' => [$list->uuid], 'update_existing' => true], null, $csv);

        $this->actingAs($this->operator)->postJson("/api/v1/import-jobs/{$id}/commit")->assertStatus(202);

        $bob->refresh();
        $this->assertSame('Robert', $bob->first_name);
        $this->assertSame('Keep', $bob->last_name);
        $this->assertSame('Oran', $bob->custom_fields['SMS']);
        $this->assertSame(1, $list->recipients()->count());

        $job = ImportJob::query()->where('uuid', $id)->firstOrFail();
        $this->assertSame(1, $job->summary['updated']);
        $this->assertSame(0, $job->summary['created']);
        $this->assertSame('completed', $job->status);
    }

    public function test_empty_values_overwrite_clears_existing_values(): void
    {
        $list = RecipientList::query()->create(['name' => 'L', 'type' => 'static']);
        $bob = Recipient::factory()->create(['email' => 'bob@example.com', 'last_name' => 'Gone']);

        $csv = "Courriel;Prénom;Nom de famille;Ville\nbob@example.com;Robert;;Oran";
        $id = $this->prepare(['list_ids' => [$list->uuid], 'update_existing' => true, 'empty_values_overwrite' => true], null, $csv);
        $this->actingAs($this->operator)->postJson("/api/v1/import-jobs/{$id}/commit")->assertStatus(202);

        $this->assertNull($bob->fresh()->last_name);
    }

    public function test_without_update_existing_existing_contacts_are_skipped_but_still_listed(): void
    {
        $list = RecipientList::query()->create(['name' => 'L', 'type' => 'static']);
        $bob = Recipient::factory()->create(['email' => 'bob@example.com', 'first_name' => 'Old']);

        $csv = "Courriel;Prénom;Nom de famille;Ville\nbob@example.com;Robert;X;Oran\nnew@example.com;Nina;Y;Blida";
        $id = $this->prepare(['list_ids' => [$list->uuid], 'update_existing' => false], null, $csv);
        $this->actingAs($this->operator)->postJson("/api/v1/import-jobs/{$id}/commit")->assertStatus(202);

        $this->assertSame('Old', $bob->fresh()->first_name);
        $summary = ImportJob::query()->where('uuid', $id)->firstOrFail()->summary;
        $this->assertSame(1, $summary['created']);
        $this->assertSame(1, $summary['skipped']);
        $this->assertSame(2, $list->recipients()->count());
    }

    public function test_mark_blocklisted_writes_suppression_entries(): void
    {
        $list = RecipientList::query()->create(['name' => 'L', 'type' => 'static']);
        $id = $this->prepare(['list_ids' => [$list->uuid], 'update_existing' => true, 'mark_blocklisted' => true]);
        $this->actingAs($this->operator)->postJson("/api/v1/import-jobs/{$id}/commit")->assertStatus(202);

        $this->assertDatabaseHas('suppression_entries', ['email' => 'alice@example.com', 'reason' => 'manual_block']);
        $this->assertSame(2, SuppressionEntry::query()->count());
        $this->assertSame(2, ImportJob::query()->where('uuid', $id)->firstOrFail()->summary['blocklisted']);
        $this->assertSame('suppressed', Recipient::query()->where('email', 'bob@example.com')->value('status'));
    }

    public function test_settings_require_a_list(): void
    {
        [$id] = $this->upload();

        $this->actingAs($this->operator)->putJson("/api/v1/import-jobs/{$id}/settings", ['list_ids' => [], 'update_existing' => true])
            ->assertUnprocessable()->assertJsonValidationErrors('list_ids');
    }

    public function test_commit_before_mapping_is_a_conflict_for_wizard_jobs(): void
    {
        $list = RecipientList::query()->create(['name' => 'L', 'type' => 'static']);
        $id = $this->prepare(['list_ids' => [$list->uuid], 'update_existing' => true]);
        ImportJob::query()->where('uuid', $id)->update(['status' => 'importing']);

        $this->actingAs($this->operator)->postJson("/api/v1/import-jobs/{$id}/commit")->assertStatus(409);
    }

    public function test_errors_csv_lists_failed_rows(): void
    {
        $list = RecipientList::query()->create(['name' => 'L', 'type' => 'static']);
        $id = $this->prepare(['list_ids' => [$list->uuid], 'update_existing' => true]);
        $this->actingAs($this->operator)->postJson("/api/v1/import-jobs/{$id}/commit")->assertStatus(202);

        $url = $this->actingAs($this->operator)->getJson("/api/v1/import-jobs/{$id}")->json('data.errors_url');
        $this->assertNotNull($url);
        $this->assertStringContainsString('signature=', $url);

        $response = $this->actingAs($this->operator)->get($url)->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
        $body = $response->streamedContent();
        $this->assertStringContainsString('row_number;email;column;error', $body);
        $this->assertStringContainsString('3;bad-email;EMAIL;', $body);
    }

    public function test_history_is_paginated_filtered_and_omits_preview(): void
    {
        $list = RecipientList::query()->create(['name' => 'L', 'type' => 'static']);
        $first = $this->prepare(['list_ids' => [$list->uuid], 'update_existing' => true]);
        $this->actingAs($this->operator)->postJson("/api/v1/import-jobs/{$first}/commit")->assertStatus(202);
        $this->upload();

        $all = $this->actingAs($this->operator)->getJson('/api/v1/import-jobs?per_page=1')->assertOk();
        $all->assertJsonCount(1, 'data');
        $this->assertSame(2, $all->json('meta.total'));
        $this->assertNull($all->json('data.0.preview'));

        $done = $this->actingAs($this->operator)->getJson('/api/v1/import-jobs?status=completed_with_errors')->assertOk();
        $done->assertJsonCount(1, 'data');
        $done->assertJsonPath('data.0.id', $first);
        $done->assertJsonPath('data.0.summary.created', 2);

        $this->actingAs($this->operator)->getJson('/api/v1/import-jobs?sort=bogus')->assertUnprocessable();
    }

    public function test_viewer_cannot_read_history_or_download_errors(): void
    {
        [$id] = $this->upload();
        $viewer = User::factory()->withRole(RoleName::Viewer)->create();

        $this->actingAs($viewer)->getJson('/api/v1/import-jobs')->assertForbidden();
        $this->actingAs($viewer)->getJson("/api/v1/import-jobs/{$id}")->assertForbidden();
        $this->actingAs($viewer)->getJson("/api/v1/import-jobs/{$id}/preview")->assertForbidden();
        $this->actingAs($viewer)->get("/api/v1/import-jobs/{$id}/errors.csv")->assertForbidden();
        $this->actingAs($viewer)->putJson("/api/v1/import-jobs/{$id}/settings", ['list_ids' => [], 'update_existing' => true])->assertForbidden();
    }

    public function test_web_pages_require_import_permission(): void
    {
        $viewer = User::factory()->withRole(RoleName::Viewer)->create();

        $this->actingAs($viewer)->get('/contacts/imports')->assertForbidden();
        $this->actingAs($viewer)->get('/contacts/imports/new')->assertForbidden();
    }
}
