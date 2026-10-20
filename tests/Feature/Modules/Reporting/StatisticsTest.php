<?php

namespace Tests\Feature\Modules\Reporting;

use App\Domain\Enums\RoleName;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Identity\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Feature\Modules\Reporting\Support\RollupTables;
use Tests\Feature\Modules\Reporting\Support\SeedsReportData;
use Tests\TestCase;

/**
 * docs/45 §45.6.5 — `/statistics/*` and legacy `/reporting/*` regression.
 */
class StatisticsTest extends TestCase
{
    use RefreshDatabase, SeedsReportData;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-20 12:00:00');
        RollupTables::ensure();
        $this->seed(RolePermissionSeeder::class);
        $this->manager = User::factory()->withRole(RoleName::MarketingManager)->create();
    }

    private function seedPeriod(): void
    {
        $a = $this->sentCampaign(['name' => 'Sept', 'sent_at' => '2026-09-05 08:00:00', 'tags' => ['promo']]);
        $b = $this->sentCampaign(['name' => 'Oct', 'sent_at' => '2026-10-10 09:00:00', 'tags' => ['news'], 'unique_opens' => 20]);
        $old = $this->sentCampaign(['name' => 'Ancienne', 'sent_at' => '2025-01-01 09:00:00']);
        $list = $this->list('Clients', 300);
        $this->hourly($a, '2026-09-05 08:00:00', ['sent' => 100, 'unique_opens' => 5]);
        $this->hourly($b, '2026-10-10 09:00:00', ['sent' => 50, 'unique_opens' => 7, 'deferred' => 2]);
        $this->hourly($b, '2026-10-10 15:00:00', ['sent' => 50, 'unique_opens' => 3]);
        $this->byList($a, 'list', $list->id, ['sent' => 100, 'delivered' => 90, 'unique_opens' => 40]);
        $this->byList($b, 'list', $list->id, ['sent' => 100, 'delivered' => 90, 'unique_opens' => 20]);
        $this->byList($old, 'list', $list->id, ['sent' => 999]);
    }

    public function test_summary_with_period_and_previous(): void
    {
        $this->seedPeriod();

        $d = $this->actingAs($this->manager)->getJson('/api/v1/statistics/summary?date_from=2026-09-01&date_to=2026-10-31&compare=previous_period')
            ->assertOk()->json('data');
        $this->assertSame(['from' => '2026-09-01', 'to' => '2026-10-31'], $d['period']);
        $this->assertSame('marketing', $d['scope']);
        $this->assertSame(2, $d['campaigns_sent']);
        $this->assertSame(200, $d['globalStats']['sent']);
        $this->assertSame(65, $d['globalStats']['uniqueViews']);
        $this->assertSame(2, $d['globalStats']['deferred']);
        $this->assertEqualsWithDelta(65 / 180, $d['rates']['open_rate'], 0.001);
        $this->assertSame('2026-07-02', $d['previous']['period']['from']);
        $this->assertSame('2026-08-31', $d['previous']['period']['to']);
        $this->assertSame(0, $d['previous']['globalStats']['sent']);

        $d = $this->actingAs($this->manager)->getJson('/api/v1/statistics/overview?period=30d')->assertOk()->json('data');
        $this->assertSame('2026-09-21', $d['period']['from']);
        $this->assertSame(1, $d['campaigns_sent']);
        $this->assertNull($d['previous']);
    }

    public function test_period_validation(): void
    {
        $this->actingAs($this->manager);
        $this->getJson('/api/v1/statistics/summary?date_from=2026-10-10&date_to=2026-10-01')->assertStatus(422)->assertJsonValidationErrors('date_to');
        $this->getJson('/api/v1/statistics/summary?date_from=2025-01-01&date_to=2026-10-01')->assertStatus(422)->assertJsonValidationErrors('date_to');
        $this->getJson('/api/v1/statistics/summary?date_from=nope')->assertStatus(422)->assertJsonValidationErrors('date_from');
        $this->getJson('/api/v1/statistics/summary?period=5y')->assertStatus(422)->assertJsonValidationErrors('period');
        $this->getJson('/api/v1/statistics/summary?scope=other')->assertStatus(422)->assertJsonValidationErrors('scope');
        $this->getJson('/api/v1/statistics/timeseries?date_from=2026-01-01&date_to=2026-06-01&granularity=hour')->assertStatus(422)->assertJsonValidationErrors('granularity');
        $this->getJson('/api/v1/statistics/campaigns?sort=bogus')->assertStatus(422)->assertJsonValidationErrors('sort');
        $this->getJson('/api/v1/statistics/averages?days=0')->assertStatus(422);
    }

    public function test_timeseries_granularities(): void
    {
        $this->seedPeriod();

        $day = $this->actingAs($this->manager)->getJson('/api/v1/statistics/timeseries?date_from=2026-10-01&date_to=2026-10-31&granularity=day')->assertOk()->json('data');
        $this->assertSame('day', $day['granularity']);
        $this->assertCount(1, $day['points']);
        $this->assertSame('2026-10-10T00:00:00Z', $day['points'][0]['t']);
        $this->assertSame(100, $day['points'][0]['sent']);
        $this->assertSame(10, $day['points'][0]['uniqueViews']);

        $hour = $this->actingAs($this->manager)->getJson('/api/v1/statistics/timeseries?date_from=2026-10-01&date_to=2026-10-31&granularity=hour')->assertOk()->json('data');
        $this->assertCount(2, $hour['points']);

        $month = $this->actingAs($this->manager)->getJson('/api/v1/statistics/timeseries?date_from=2026-09-01&date_to=2026-10-31&granularity=month')->assertOk()->json('data');
        $this->assertSame(['2026-09-01T00:00:00Z', '2026-10-01T00:00:00Z'], array_column($month['points'], 't'));

        $week = $this->actingAs($this->manager)->getJson('/api/v1/statistics/timeseries?date_from=2026-10-01&date_to=2026-10-31&granularity=week')->assertOk()->json('data');
        $this->assertSame('2026-10-05T00:00:00Z', $week['points'][0]['t']);
    }

    public function test_campaigns_table_sort_tag_and_paging(): void
    {
        $this->seedPeriod();
        $this->actingAs($this->manager);

        $r = $this->getJson('/api/v1/statistics/campaigns?date_from=2026-09-01&date_to=2026-10-31')->assertOk();
        $this->assertSame(['Oct', 'Sept'], array_column($r->json('data'), 'name'));
        $this->assertArrayHasKey('meta', $r->json());
        $this->assertSame(20, $r->json('data.0.globalStats.uniqueViews'));
        $this->assertSame(['news'], $r->json('data.0.tags'));

        $r = $this->getJson('/api/v1/statistics/campaigns?date_from=2026-09-01&date_to=2026-10-31&sort=-open_rate');
        $this->assertSame(['Sept', 'Oct'], array_column($r->json('data'), 'name'));

        $r = $this->getJson('/api/v1/statistics/campaigns?date_from=2026-09-01&date_to=2026-10-31&tag=promo');
        $this->assertSame(['Sept'], array_column($r->json('data'), 'name'));

        $r = $this->getJson('/api/v1/statistics/campaigns?date_from=2026-09-01&date_to=2026-10-31&per_page=1&page=2');
        $this->assertSame(['Sept'], array_column($r->json('data'), 'name'));
    }

    public function test_lists_table(): void
    {
        $this->seedPeriod();

        $d = $this->actingAs($this->manager)->getJson('/api/v1/statistics/lists?date_from=2026-09-01&date_to=2026-10-31')->assertOk()->json('data');
        $this->assertCount(1, $d);
        $this->assertSame('Clients', $d[0]['name']);
        $this->assertSame(300, $d[0]['contact_count']);
        $this->assertSame(200, $d[0]['globalStats']['sent']);
        $this->assertSame(60, $d[0]['globalStats']['uniqueViews']);
    }

    public function test_averages_use_last_90_days_of_sent_campaigns(): void
    {
        $this->seedPeriod();

        $d = $this->actingAs($this->manager)->getJson('/api/v1/statistics/averages')->assertOk()->json('data');
        $this->assertEqualsWithDelta(0.9, $d['delivery_rate'], 0.0001);
        // Sept (45/90) + Oct (20/90) over the last 90 days; the 2025 campaign is excluded.
        $this->assertEqualsWithDelta(65 / 180, $d['open_rate'], 0.001);

        $this->getJson('/api/v1/statistics/averages?days=7')->assertOk();
    }

    public function test_export_permissions_audit_and_csv(): void
    {
        $this->seedPeriod();
        $viewer = User::factory()->withRole(RoleName::Viewer)->create();

        $this->actingAs($viewer)->getJson('/api/v1/statistics/summary')->assertOk();
        $this->actingAs($viewer)->getJson('/api/v1/statistics/export?table=campaigns')->assertForbidden();

        $r = $this->actingAs($this->manager)->get('/api/v1/statistics/export?table=campaigns&date_from=2026-09-01&date_to=2026-10-31')->assertOk();
        $csv = $r->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('Oct;', $csv);
        $this->assertStringNotContainsString('Ancienne', $csv);

        $csv = $this->actingAs($this->manager)->get('/api/v1/statistics/export?table=lists&date_from=2026-09-01&date_to=2026-10-31')->streamedContent();
        $this->assertStringContainsString('Clients;300', $csv);

        $this->actingAs($this->manager)->get('/api/v1/statistics/export')->assertStatus(302);
        $this->assertSame(2, AuditLog::query()->where('action', 'report.exported')->count());
    }

    public function test_statistics_require_authentication(): void
    {
        $this->getJson('/api/v1/statistics/summary')->assertUnauthorized();
    }

    public function test_legacy_reporting_endpoints_still_work(): void
    {
        $viewer = User::factory()->withRole(RoleName::Viewer)->create();
        $this->actingAs($viewer)->getJson('/api/v1/reporting/summary')->assertOk()->assertJsonStructure(['sent', 'delivered', 'delivery_rate']);
        $this->actingAs($viewer)->getJson('/api/v1/reporting/campaigns')->assertOk()->assertJsonStructure(['data']);
        $this->actingAs($viewer)->getJson('/api/v1/reporting/smtp-accounts')->assertOk();
        $this->actingAs($this->manager)->get('/api/v1/reporting/export')->assertOk();
    }
}
