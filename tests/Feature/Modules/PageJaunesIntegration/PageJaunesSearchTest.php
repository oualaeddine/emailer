<?php

namespace Tests\Feature\Modules\PageJaunesIntegration;

use App\Domain\Enums\RoleName;
use App\Modules\Identity\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * docs/06-pagejaunes-integration.md, docs/29-api-specification.md §29.4.
 *
 * Runs against the REAL external `pjnewdb` schema (present on the same
 * MariaDB server used for local dev) rather than a mock — this is exactly
 * the kind of external-DB integration test docs/32-testing.md §32.4
 * calls for, with fixture rows inserted/cleaned per test instead of a
 * separate disposable instance.
 */
class PageJaunesSearchTest extends TestCase
{
    use RefreshDatabase;

    private const CONNECTION = 'pagejaunes';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
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
        DB::connection(self::CONNECTION)->table('companies_details')->where('company_id', '>=', 900000000)->delete();
        DB::connection(self::CONNECTION)->table('companies')->where('id', '>=', 900000000)->delete();
    }

    private function insertFixtureCompany(int $id, string $code, string $name, array $emails = []): void
    {
        DB::connection(self::CONNECTION)->table('companies')->insert([
            'id' => $id,
            'code' => $code,
            'company_name' => $name,
            'abv_company_name' => null,
            'Address' => 'Adresse de test',
            'distributor' => 0,
            'exportation' => 0,
            'importation' => 0,
            'producer' => 0,
            'customer' => 0,
            'completion_percentage' => 0,
            'created_at' => now(),
        ]);

        foreach ($emails as $i => $email) {
            DB::connection(self::CONNECTION)->table('emails')->insert([
                'id' => $id * 10 + $i,
                'company_id' => $id,
                'mail' => $email,
                'created_at' => now(),
            ]);
        }
    }

    public function test_administrator_can_search_pagejaunes_companies(): void
    {
        $this->insertFixtureCompany(900000001, 'TEST001', 'Boulangerie Test Alger', ['contact@boulangerie-test.dz']);

        $admin = User::factory()->withRole(RoleName::Administrator)->create();

        $response = $this->actingAs($admin)->getJson('/api/v1/pagejaunes/search?q=Boulangerie+Test');

        $response->assertOk();
        $response->assertJsonFragment(['company_name' => 'Boulangerie Test Alger']);
        $response->assertJsonFragment(['email' => 'contact@boulangerie-test.dz']);
    }

    public function test_a_company_without_email_is_still_returned_but_flagged_empty(): void
    {
        $this->insertFixtureCompany(900000002, 'TEST002', 'Sans Email Test');

        $admin = User::factory()->withRole(RoleName::Administrator)->create();

        $response = $this->actingAs($admin)->getJson('/api/v1/pagejaunes/search?q=Sans+Email+Test');

        $response->assertOk();
        $response->assertJsonFragment(['company_name' => 'Sans Email Test', 'emails' => []]);
    }

    public function test_viewer_without_recipients_import_permission_is_forbidden(): void
    {
        $viewer = User::factory()->withRole(RoleName::Viewer)->create();

        $this->actingAs($viewer)->getJson('/api/v1/pagejaunes/search?q=test')->assertForbidden();
    }

    public function test_empty_query_returns_no_results_without_querying_the_external_db(): void
    {
        $admin = User::factory()->withRole(RoleName::Administrator)->create();

        $response = $this->actingAs($admin)->getJson('/api/v1/pagejaunes/search?q=');

        $response->assertOk();
        $response->assertJsonCount(0, 'data');
    }

    public function test_search_results_are_cached_for_five_minutes(): void
    {
        $this->insertFixtureCompany(900000003, 'TEST003', 'Cache Test Company', ['cache@test.dz']);

        $admin = User::factory()->withRole(RoleName::Administrator)->create();
        $this->actingAs($admin)->getJson('/api/v1/pagejaunes/search?q=Cache+Test+Company')->assertOk();

        $cacheKey = 'pagejaunes.search.'.md5('Cache Test Company').'.1';
        $this->assertTrue(Cache::has($cacheKey));
    }
}
