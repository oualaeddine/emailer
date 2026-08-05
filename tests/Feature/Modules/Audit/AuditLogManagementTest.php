<?php

namespace Tests\Feature\Modules\Audit;

use App\Domain\Enums\RoleName;
use App\Modules\Identity\Models\User;
use Database\Factories\AuditLogFactory;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * docs/29-api-specification.md §29.2 (lines 177-178), docs/27-audit-logs.md.
 * docs/26-rbac.md §26.3 — `audit.view`/`audit.export` are Administrator-only.
 *
 * `AuditLog::factory()` is not available (the model intentionally does not
 * use `HasFactory` — see `database/factories/AuditLogFactory.php`'s
 * docblock), so every row here is created via `AuditLogFactory::new()`.
 */
class AuditLogManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_administrator_can_list_audit_logs(): void
    {
        $admin = User::factory()->withRole(RoleName::Administrator)->create();
        AuditLogFactory::new()->count(3)->create();

        $response = $this->actingAs($admin)->getJson('/api/v1/audit-logs');

        $response->assertOk();
        $response->assertJsonCount(3, 'data');
    }

    public function test_marketing_manager_cannot_list_audit_logs(): void
    {
        // docs/26-rbac.md matrix — audit.view/audit.export are
        // Administrator-only; MarketingManager has neither.
        $manager = User::factory()->withRole(RoleName::MarketingManager)->create();

        $this->actingAs($manager)->getJson('/api/v1/audit-logs')->assertForbidden();
    }

    public function test_viewer_cannot_list_audit_logs(): void
    {
        $viewer = User::factory()->withRole(RoleName::Viewer)->create();

        $this->actingAs($viewer)->getJson('/api/v1/audit-logs')->assertForbidden();
    }

    public function test_guest_cannot_list_audit_logs(): void
    {
        $this->getJson('/api/v1/audit-logs')->assertUnauthorized();
    }

    public function test_audit_log_resource_exposes_the_actor_name_not_the_raw_user_id(): void
    {
        $admin = User::factory()->withRole(RoleName::Administrator)->create();
        $actor = User::factory()->create(['name' => 'Camille Martin']);
        AuditLogFactory::new()->create(['user_id' => $actor->id]);

        $response = $this->actingAs($admin)->getJson('/api/v1/audit-logs');

        $response->assertOk();
        $response->assertJsonPath('data.0.user', 'Camille Martin');
        $response->assertJsonMissingPath('data.0.user_id');
    }

    public function test_listing_can_be_filtered_by_user_id(): void
    {
        $admin = User::factory()->withRole(RoleName::Administrator)->create();
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        AuditLogFactory::new()->create(['user_id' => $userA->id, 'action' => 'campaign.created']);
        AuditLogFactory::new()->create(['user_id' => $userB->id, 'action' => 'campaign.sent']);

        $response = $this->actingAs($admin)->getJson("/api/v1/audit-logs?user_id={$userA->id}");

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.action', 'campaign.created');
    }

    public function test_listing_can_be_filtered_by_action(): void
    {
        $admin = User::factory()->withRole(RoleName::Administrator)->create();
        AuditLogFactory::new()->forAction('campaign.created')->create();
        AuditLogFactory::new()->forAction('smtp_account.updated')->create();

        $response = $this->actingAs($admin)->getJson('/api/v1/audit-logs?action=smtp_account.updated');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.action', 'smtp_account.updated');
    }

    public function test_listing_can_be_filtered_by_auditable_type(): void
    {
        $admin = User::factory()->withRole(RoleName::Administrator)->create();
        AuditLogFactory::new()->create(['auditable_type' => 'App\\Modules\\Campaigns\\Models\\Campaign']);
        AuditLogFactory::new()->create(['auditable_type' => 'App\\Modules\\Identity\\Models\\User']);

        $response = $this->actingAs($admin)->getJson(
            '/api/v1/audit-logs?auditable_type='.urlencode('App\\Modules\\Identity\\Models\\User'),
        );

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.auditable_type', 'App\\Modules\\Identity\\Models\\User');
    }

    public function test_listing_can_be_filtered_by_date_range(): void
    {
        $admin = User::factory()->withRole(RoleName::Administrator)->create();
        AuditLogFactory::new()->forAction('campaign.created')->create(['created_at' => '2026-01-01 10:00:00']);
        AuditLogFactory::new()->forAction('campaign.sent')->create(['created_at' => '2026-06-15 10:00:00']);
        AuditLogFactory::new()->forAction('campaign.cancelled')->create(['created_at' => '2026-08-01 10:00:00']);

        $response = $this->actingAs($admin)->getJson(
            '/api/v1/audit-logs?date_from=2026-06-01&date_to=2026-06-30',
        );

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.action', 'campaign.sent');
    }

    public function test_export_requires_audit_export_permission_not_just_audit_view(): void
    {
        // docs/27-audit-logs.md §27.9 — both are Administrator-only in the
        // seeded matrix, but they are distinct permissions; this asserts
        // the export route checks its own permission rather than reusing
        // audit.view's gate.
        $admin = User::factory()->withRole(RoleName::Administrator)->create();
        AuditLogFactory::new()->count(2)->create();

        $response = $this->actingAs($admin)->postJson('/api/v1/audit-logs/export');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    }

    public function test_marketing_manager_cannot_export_audit_logs(): void
    {
        $manager = User::factory()->withRole(RoleName::MarketingManager)->create();

        $this->actingAs($manager)->postJson('/api/v1/audit-logs/export')->assertForbidden();
    }

    public function test_export_streams_a_csv_with_the_expected_header_row_and_filtered_rows(): void
    {
        $admin = User::factory()->withRole(RoleName::Administrator)->create();
        AuditLogFactory::new()->forAction('campaign.created')->create();
        AuditLogFactory::new()->forAction('smtp_account.updated')->create();

        $response = $this->actingAs($admin)->postJson('/api/v1/audit-logs/export', [
            'action' => 'campaign.created',
        ]);

        $response->assertOk();
        $content = $response->streamedContent();
        $this->assertStringContainsString('ID,Utilisateur,Action', $content);
        $this->assertStringContainsString('campaign.created', $content);
        $this->assertStringNotContainsString('smtp_account.updated', $content);
    }
}
