<?php

namespace Tests\Feature\Modules\Dashboard;

use App\Domain\Enums\RoleName;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\DeliveryEngine\Models\SmtpAccount;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Recipients\Models\Recipient;
use App\Modules\Tracking\Models\Message;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * docs/09-dashboard.md §9.2, docs/42-parallel-execution-plan.md §42.8
 * (WP-33) — `GET /api/v1/dashboard/widgets`.
 */
class DashboardWidgetsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    /**
     * `created_at` is forced explicitly (rather than left to the
     * migration's second-precision default timestamp) so ordering between
     * campaigns created within the same test is deterministic — two
     * `Campaign::create()` calls in quick succession can otherwise land in
     * the same second.
     */
    private function createCampaign(string $name, string $status, ?Carbon $createdAt = null): Campaign
    {
        $campaign = Campaign::query()->create([
            'name' => $name,
            'status' => $status,
        ]);

        if ($createdAt !== null) {
            $campaign->forceFill(['created_at' => $createdAt])->save();
        }

        return $campaign;
    }

    private function createSmtpAccount(string $name, ?int $dailyQuota = 100): SmtpAccount
    {
        return SmtpAccount::query()->create([
            'name' => $name,
            'provider' => 'smtp',
            'host' => 'smtp.example.test',
            'port' => 587,
            'encryption' => 'tls',
            'username' => 'user@example.test',
            'password_encrypted' => 'secret',
            'from_email' => 'noreply@example.test',
            'daily_quota' => $dailyQuota,
            'is_active' => true,
        ]);
    }

    public function test_returns_send_volume_and_deliverability_from_seeded_messages(): void
    {
        $user = User::factory()->withRole(RoleName::Administrator)->create();

        $delivered = Message::factory()->create([
            'recipient_id' => Recipient::factory()->create()->id,
            'status' => 'delivered',
            'queued_at' => now(),
            'sent_at' => now(),
            'delivered_at' => now(),
        ]);
        $sentOnly = Message::factory()->create([
            'recipient_id' => Recipient::factory()->create()->id,
            'status' => 'accepted',
            'queued_at' => now(),
            'sent_at' => now(),
        ]);
        // Not yet sent — must not count towards `sent`/`delivered`.
        Message::factory()->create([
            'recipient_id' => Recipient::factory()->create()->id,
            'status' => 'queued',
            'queued_at' => now(),
        ]);

        $response = $this->actingAs($user)->getJson('/api/v1/dashboard/widgets');

        $response->assertOk();
        $response->assertJsonPath('send_volume.days', 7);
        $response->assertJsonCount(7, 'send_volume.series');
        $response->assertJsonPath('send_volume.series.6.date', now()->toDateString());
        $response->assertJsonPath('send_volume.series.6.count', 3);
        $response->assertJsonPath('deliverability.sent', 2);
        $response->assertJsonPath('deliverability.delivered', 1);
        $response->assertJsonPath('deliverability.rate', 0.5);

        $this->assertNotNull($delivered->delivered_at);
        $this->assertNotNull($sentOnly->sent_at);
    }

    public function test_returns_recent_campaigns_and_quota_usage_for_administrator(): void
    {
        $admin = User::factory()->withRole(RoleName::Administrator)->create();
        $this->createCampaign('Campagne A', 'running', now()->subMinutes(10));
        $this->createCampaign('Campagne B', 'draft', now());
        $this->createSmtpAccount('Compte Principal', 100);

        $response = $this->actingAs($admin)->getJson('/api/v1/dashboard/widgets');

        $response->assertOk();
        $response->assertJsonCount(2, 'recent_campaigns');
        $response->assertJsonPath('recent_campaigns.0.name', 'Campagne B');
        $response->assertJsonCount(1, 'quota_usage');
        $response->assertJsonPath('quota_usage.0.name', 'Compte Principal');
        $response->assertJsonPath('quota_usage.0.daily_quota', 100);
        $response->assertJsonPath('quota_usage.0.used_today', 0);
    }

    public function test_viewer_sees_recent_campaigns_but_not_quota_usage(): void
    {
        // docs/26-rbac.md §26.6 seed matrix: Viewer holds `campaigns.view`
        // but not `smtp.view`, so the quota-usage widget must be omitted.
        $viewer = User::factory()->withRole(RoleName::Viewer)->create();
        $this->createCampaign('Campagne Viewer', 'draft');
        $this->createSmtpAccount('Compte Caché');

        $response = $this->actingAs($viewer)->getJson('/api/v1/dashboard/widgets');

        $response->assertOk();
        $response->assertJsonStructure(['send_volume', 'deliverability', 'recent_campaigns']);
        $response->assertJsonMissingPath('quota_usage');
    }

    public function test_dashboard_view_permission_is_required(): void
    {
        // All four seeded roles (including Viewer, the least privileged)
        // grant `dashboard.view` — a bare `User::factory()->create()` would
        // default to the Viewer role and pass authorization. Use a role
        // with no permissions at all to exercise the negative case.
        $roleWithNoPermissions = Role::factory()->create();
        $user = User::factory()->create(['role_id' => $roleWithNoPermissions->id]);

        $this->actingAs($user)->getJson('/api/v1/dashboard/widgets')->assertForbidden();
    }

    public function test_days_query_parameter_controls_series_length(): void
    {
        $user = User::factory()->withRole(RoleName::Administrator)->create();

        $response = $this->actingAs($user)->getJson('/api/v1/dashboard/widgets?days=30');

        $response->assertOk();
        $response->assertJsonPath('send_volume.days', 30);
        $response->assertJsonCount(30, 'send_volume.series');
    }
}
