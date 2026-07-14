<?php

namespace Tests\Feature\Modules\Identity;

use App\Domain\Enums\RoleName;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * docs/29-api-specification.md §29.2 — GET/POST /api/v1/users, PATCH /api/v1/users/{uuid}.
 * docs/26-rbac.md §26.3 — gated by `users.manage`.
 */
class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_administrator_can_list_users(): void
    {
        $admin = User::factory()->withRole(RoleName::Administrator)->create();
        User::factory()->count(3)->withRole(RoleName::Viewer)->create();

        $response = $this->actingAs($admin)->getJson('/api/v1/users');

        $response->assertOk();
        $response->assertJsonCount(4, 'data'); // 3 + the admin itself
    }

    public function test_non_administrator_cannot_list_users(): void
    {
        $viewer = User::factory()->withRole(RoleName::Viewer)->create();

        $this->actingAs($viewer)->getJson('/api/v1/users')->assertForbidden();
    }

    public function test_administrator_can_create_a_user(): void
    {
        $admin = User::factory()->withRole(RoleName::Administrator)->create();
        $role = Role::query()->where('name', RoleName::MarketingOperator->value)->firstOrFail();

        $response = $this->actingAs($admin)->postJson('/api/v1/users', [
            'name' => 'Nouvel Opérateur',
            'email' => 'operateur@example.com',
            'password' => 'SuperSecret123',
            'role_id' => $role->id,
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('users', ['email' => 'operateur@example.com', 'role_id' => $role->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.created']);
    }

    public function test_creating_a_user_with_a_duplicate_email_fails_validation(): void
    {
        $admin = User::factory()->withRole(RoleName::Administrator)->create();
        $existing = User::factory()->withRole(RoleName::Viewer)->create(['email' => 'taken@example.com']);
        $role = Role::query()->where('name', RoleName::Viewer->value)->firstOrFail();

        $response = $this->actingAs($admin)->postJson('/api/v1/users', [
            'name' => 'Doublon',
            'email' => $existing->email,
            'password' => 'SuperSecret123',
            'role_id' => $role->id,
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('email');
    }

    public function test_operator_cannot_create_a_user(): void
    {
        $operator = User::factory()->withRole(RoleName::MarketingOperator)->create();
        $role = Role::query()->where('name', RoleName::Viewer->value)->firstOrFail();

        $response = $this->actingAs($operator)->postJson('/api/v1/users', [
            'name' => 'Sans Droit',
            'email' => 'sansdroit@example.com',
            'password' => 'SuperSecret123',
            'role_id' => $role->id,
        ]);

        $response->assertForbidden();
    }

    public function test_changing_a_users_role_fires_the_documented_side_effects(): void
    {
        $admin = User::factory()->withRole(RoleName::Administrator)->create();
        $target = User::factory()->withRole(RoleName::Viewer)->create();
        $newRole = Role::query()->where('name', RoleName::MarketingOperator->value)->firstOrFail();

        $response = $this->actingAs($admin)->patchJson("/api/v1/users/{$target->uuid}", [
            'role_id' => $newRole->id,
        ]);

        $response->assertOk();
        $this->assertSame($newRole->id, $target->fresh()->role_id);

        // docs/27-audit-logs.md §27.2 — user.role_changed
        $this->assertDatabaseHas('audit_logs', [
            'auditable_id' => $target->id,
            'action' => 'user.role_changed',
        ]);

        // docs/41-notification-center.md §41.2 — the affected user is notified in-app.
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $target->id,
            'notifiable_type' => $target::class,
        ]);
    }

    public function test_deactivating_a_user_fires_the_documented_audit_event(): void
    {
        $admin = User::factory()->withRole(RoleName::Administrator)->create();
        $target = User::factory()->withRole(RoleName::Viewer)->create(['is_active' => true]);

        $response = $this->actingAs($admin)->patchJson("/api/v1/users/{$target->uuid}", [
            'is_active' => false,
        ]);

        $response->assertOk();
        $this->assertFalse($target->fresh()->is_active);
        $this->assertDatabaseHas('audit_logs', [
            'auditable_id' => $target->id,
            'action' => 'user.deactivated',
        ]);
    }

    public function test_an_administrator_cannot_delete_their_own_account_via_policy(): void
    {
        $admin = User::factory()->withRole(RoleName::Administrator)->create();

        $this->assertFalse($admin->can('delete', $admin));
    }
}
