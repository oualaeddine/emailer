<?php

namespace Tests\Feature\Modules\PageJaunesIntegration;

use App\Modules\PageJaunesIntegration\Models\PageJaunesCompanyCache;
use App\Modules\PageJaunesIntegration\Services\PageJaunesSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * docs/06-pagejaunes-integration.md §6.5 — Synchronization Strategy.
 */
class PageJaunesSyncServiceTest extends TestCase
{
    use RefreshDatabase;

    private const CONNECTION = 'pagejaunes';

    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanFixtures();
    }

    protected function tearDown(): void
    {
        $this->cleanFixtures();
        parent::tearDown();
    }

    private function cleanFixtures(): void
    {
        DB::connection(self::CONNECTION)->table('emails')->where('company_id', '>=', 900000000)->delete();
        DB::connection(self::CONNECTION)->table('companies')->where('id', '>=', 900000000)->delete();
    }

    public function test_syncing_a_company_upserts_the_cache_and_its_emails(): void
    {
        DB::connection(self::CONNECTION)->table('companies')->insert([
            'id' => 900000010,
            'code' => 'SYNC010',
            'company_name' => 'Entreprise Sync Test',
            'Address' => 'Rue de synchronisation',
            'distributor' => 1,
            'exportation' => 0,
            'importation' => 0,
            'producer' => 0,
            'customer' => 0,
            'completion_percentage' => 0,
            'created_at' => now(),
        ]);
        DB::connection(self::CONNECTION)->table('emails')->insert([
            'id' => 9000000100,
            'company_id' => 900000010,
            'mail' => 'sync@test.dz',
            'created_at' => now(),
        ]);

        $service = app(PageJaunesSyncService::class);
        $result = $service->syncBatch(900000009, 10);

        $this->assertSame(1, $result['synced']);
        $this->assertDatabaseHas('pagejaunes_company_cache', [
            'pagejaunes_company_id' => 900000010,
            'pagejaunes_code' => 'SYNC010',
            'company_name' => 'Entreprise Sync Test',
            'is_distributor' => true,
        ]);

        $cache = PageJaunesCompanyCache::query()->where('pagejaunes_company_id', 900000010)->firstOrFail();
        $this->assertCount(1, $cache->emails);
        $this->assertSame('sync@test.dz', $cache->emails->first()->email);
    }

    public function test_syncing_the_same_company_twice_does_not_duplicate_it(): void
    {
        DB::connection(self::CONNECTION)->table('companies')->insert([
            'id' => 900000011,
            'code' => 'SYNC011',
            'company_name' => 'Idempotence Test',
            'distributor' => 0,
            'exportation' => 0,
            'importation' => 0,
            'producer' => 0,
            'customer' => 0,
            'completion_percentage' => 0,
            'created_at' => now(),
        ]);

        $service = app(PageJaunesSyncService::class);
        $service->syncBatch(900000010, 10);
        $service->syncBatch(900000010, 10);

        $this->assertSame(
            1,
            PageJaunesCompanyCache::query()->where('pagejaunes_company_id', 900000011)->count(),
        );
    }
}
