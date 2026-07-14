<?php

namespace Tests\Feature\Modules\Importing;

use App\Domain\Enums\RoleName;
use App\Modules\Identity\Models\User;
use App\Modules\Importing\Models\ImportJob;
use App\Modules\Recipients\Models\Recipient;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * docs/14-import-export.md §14.2 — full pipeline: Upload → Parse →
 * Column Mapping → Validate → Review → Commit.
 */
class CsvImportPipelineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        Storage::fake('local');
    }

    private function csvContent(): string
    {
        return implode("\n", [
            'Email,Prenom,Nom,Ville',
            'alice@example.com,Alice,Martin,Alger',
            'bob@example.com,Bob,Durand,Oran',
            'invalid-email,Carla,Petit,Blida',
            'alice@example.com,Alice,Martin,Alger', // duplicate within batch
        ]);
    }

    public function test_full_csv_import_pipeline_creates_recipients(): void
    {
        $operator = User::factory()->withRole(RoleName::MarketingOperator)->create();
        $file = UploadedFile::fake()->createWithContent('contacts.csv', $this->csvContent());

        $uploadResponse = $this->actingAs($operator)->postJson('/api/v1/import-jobs', [
            'source_type' => 'csv',
            'file' => $file,
        ]);

        $uploadResponse->assertCreated();
        $uploadResponse->assertJsonPath('detected_headers', ['Email', 'Prenom', 'Nom', 'Ville']);
        $jobUuid = $uploadResponse->json('data.id');

        $mappingResponse = $this->actingAs($operator)->putJson("/api/v1/import-jobs/{$jobUuid}/mapping", [
            'column_mapping' => [
                'email' => 'Email',
                'first_name' => 'Prenom',
                'last_name' => 'Nom',
            ],
        ]);

        $mappingResponse->assertOk();
        $mappingResponse->assertJsonPath('data.total_rows', 4);

        $rowsResponse = $this->actingAs($operator)->getJson("/api/v1/import-jobs/{$jobUuid}/rows");
        $rowsResponse->assertOk();
        $rowsResponse->assertJsonCount(4, 'data');

        $statuses = collect($rowsResponse->json('data'))->pluck('validation_status');
        $this->assertSame(2, $statuses->filter(fn ($s) => $s === 'valid')->count());
        $this->assertSame(1, $statuses->filter(fn ($s) => $s === 'invalid')->count());
        $this->assertSame(1, $statuses->filter(fn ($s) => $s === 'duplicate')->count());

        $commitResponse = $this->actingAs($operator)->postJson("/api/v1/import-jobs/{$jobUuid}/commit");
        $commitResponse->assertOk();
        $commitResponse->assertJsonPath('data.imported_count', 2);
        $commitResponse->assertJsonPath('data.status', 'completed_with_errors');

        $this->assertDatabaseHas('recipients', ['email' => 'alice@example.com', 'source' => 'csv_import']);
        $this->assertDatabaseHas('recipients', ['email' => 'bob@example.com', 'source' => 'csv_import']);
        $this->assertSame(2, Recipient::query()->count());
    }

    public function test_duplicate_against_an_existing_recipient_is_flagged(): void
    {
        $operator = User::factory()->withRole(RoleName::MarketingOperator)->create();
        Recipient::factory()->create(['email' => 'bob@example.com']);

        $file = UploadedFile::fake()->createWithContent('contacts.csv', $this->csvContent());
        $jobUuid = $this->actingAs($operator)->postJson('/api/v1/import-jobs', [
            'source_type' => 'csv',
            'file' => $file,
        ])->json('data.id');

        $this->actingAs($operator)->putJson("/api/v1/import-jobs/{$jobUuid}/mapping", [
            'column_mapping' => ['email' => 'Email', 'first_name' => 'Prenom', 'last_name' => 'Nom'],
        ]);

        $rows = $this->actingAs($operator)->getJson("/api/v1/import-jobs/{$jobUuid}/rows")->json('data');
        $bobRow = collect($rows)->firstWhere('parsed_email', 'bob@example.com');

        $this->assertSame('duplicate', $bobRow['validation_status']);
    }

    public function test_viewer_cannot_upload_an_import(): void
    {
        $viewer = User::factory()->withRole(RoleName::Viewer)->create();
        $file = UploadedFile::fake()->createWithContent('contacts.csv', $this->csvContent());

        $this->actingAs($viewer)->postJson('/api/v1/import-jobs', [
            'source_type' => 'csv',
            'file' => $file,
        ])->assertForbidden();
    }
}
