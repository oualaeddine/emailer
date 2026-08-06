<?php

namespace Tests\Feature\Modules\Queues;

use App\Domain\Enums\RoleName;
use App\Modules\Identity\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * docs/24-queue-management.md §24.6/§24.7, docs/29-api-specification.md —
 * GET /api/v1/queues/metrics, gated by `queues.view` (Administrator and
 * Marketing Manager only). Kept light per docs/42-parallel-execution-plan.md
 * §42.7 (WP-22) — there's little business logic here beyond permission
 * gating and reading straight through to the queue driver/`failed_jobs`
 * table, so this only asserts the gate and the response shape.
 */
class QueueStatsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_administrator_can_read_queue_stats(): void
    {
        $admin = User::factory()->withRole(RoleName::Administrator)->create();

        $response = $this->actingAs($admin)->getJson('/api/v1/queues/metrics');

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'horizon_available',
                'queues' => [
                    '*' => ['name', 'size'],
                ],
                'failed_jobs' => [
                    'count',
                    'recent',
                ],
            ],
        ]);
        // Horizon is a real installed dependency (composer.json/lock) in
        // this repo, so QueueStatsService::horizonAvailable()'s
        // class_exists() check reports it present.
        $response->assertJsonPath('data.horizon_available', true);
        $response->assertJsonPath('data.failed_jobs.count', 0);
        $response->assertJsonPath('data.failed_jobs.recent', []);
    }

    public function test_marketing_manager_can_read_queue_stats(): void
    {
        $manager = User::factory()->withRole(RoleName::MarketingManager)->create();

        $this->actingAs($manager)->getJson('/api/v1/queues/metrics')->assertOk();
    }

    public function test_marketing_operator_cannot_read_queue_stats(): void
    {
        $operator = User::factory()->withRole(RoleName::MarketingOperator)->create();

        $this->actingAs($operator)->getJson('/api/v1/queues/metrics')->assertForbidden();
    }

    public function test_viewer_cannot_read_queue_stats(): void
    {
        $viewer = User::factory()->withRole(RoleName::Viewer)->create();

        $this->actingAs($viewer)->getJson('/api/v1/queues/metrics')->assertForbidden();
    }

    public function test_queue_sizes_reflect_jobs_pushed_via_the_faked_queue(): void
    {
        Queue::fake();

        $admin = User::factory()->withRole(RoleName::Administrator)->create();

        Queue::pushOn('imports', new \stdClass());

        $response = $this->actingAs($admin)->getJson('/api/v1/queues/metrics');

        $response->assertOk();
        $response->assertJsonFragment(['name' => 'imports', 'size' => 1]);
        $response->assertJsonFragment(['name' => 'smtp-send-high', 'size' => 0]);
    }

    public function test_failed_jobs_summary_reflects_the_failed_jobs_table(): void
    {
        DB::table('failed_jobs')->insert([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'connection' => 'redis',
            'queue' => 'smtp-send-high',
            'payload' => '{}',
            'exception' => 'Symfony\\Component\\Mailer\\Exception\\TransportException: Connection refused',
            'failed_at' => now(),
        ]);

        $admin = User::factory()->withRole(RoleName::Administrator)->create();

        $response = $this->actingAs($admin)->getJson('/api/v1/queues/metrics');

        $response->assertOk();
        $response->assertJsonPath('data.failed_jobs.count', 1);
        $response->assertJsonPath('data.failed_jobs.recent.0.queue', 'smtp-send-high');
        $response->assertJsonPath('data.failed_jobs.recent.0.connection', 'redis');
    }
}
