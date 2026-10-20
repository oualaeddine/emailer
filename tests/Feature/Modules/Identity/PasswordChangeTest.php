<?php

namespace Tests\Feature\Modules\Identity;

use App\Domain\Enums\RoleName;
use App\Modules\Identity\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * PUT /api/v1/me/password (self-service) and PATCH /api/v1/users/{uuid} with `password` (admin reset).
 */
class PasswordChangeTest extends TestCase
{
    use RefreshDatabase;

    private const CURRENT = 'OldSecret12345';

    private const NEW = 'BrandNewSecret99';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function viewer(): User
    {
        return User::factory()->withRole(RoleName::Viewer)->withTwoFactor()->create(['password' => self::CURRENT]);
    }

    /** @return array<string, string> */
    private function payload(array $override = []): array
    {
        return array_merge([
            'current_password' => self::CURRENT,
            'password' => self::NEW,
            'password_confirmation' => self::NEW,
        ], $override);
    }

    public function test_user_can_change_own_password(): void
    {
        $user = $this->viewer();

        $this->actingAs($user)->putJson('/api/v1/me/password', $this->payload())
            ->assertOk()
            ->assertJson(['message' => 'Mot de passe modifié.']);

        $this->assertTrue(Hash::check(self::NEW, $user->fresh()->password));
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.password_changed']);
    }

    public function test_wrong_current_password_is_rejected_in_french(): void
    {
        $user = $this->viewer();

        $this->actingAs($user)->putJson('/api/v1/me/password', $this->payload(['current_password' => 'nope']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('current_password')
            ->assertJsonPath('errors.current_password.0', 'Le mot de passe actuel est incorrect.');

        $this->assertTrue(Hash::check(self::CURRENT, $user->fresh()->password));
    }

    public function test_weak_password_is_rejected(): void
    {
        $this->actingAs($this->viewer())
            ->putJson('/api/v1/me/password', $this->payload(['password' => 'short', 'password_confirmation' => 'short']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('password');
    }

    public function test_confirmation_mismatch_is_rejected_in_french(): void
    {
        $this->actingAs($this->viewer())
            ->putJson('/api/v1/me/password', $this->payload(['password_confirmation' => 'Different12345']))
            ->assertUnprocessable()
            ->assertJsonPath('errors.password.0', 'La confirmation du mot de passe ne correspond pas.');
    }

    public function test_new_password_must_differ_from_current(): void
    {
        $this->actingAs($this->viewer())
            ->putJson('/api/v1/me/password', $this->payload(['password' => self::CURRENT, 'password_confirmation' => self::CURRENT]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('password');
    }

    public function test_guest_is_unauthenticated(): void
    {
        $this->putJson('/api/v1/me/password', $this->payload())->assertUnauthorized();
    }

    public function test_endpoint_is_throttled(): void
    {
        $user = $this->viewer();

        for ($i = 0; $i < 5; $i++) {
            $this->actingAs($user)->putJson('/api/v1/me/password', $this->payload(['current_password' => 'nope']))
                ->assertUnprocessable();
        }

        $this->actingAs($user)->putJson('/api/v1/me/password', $this->payload())->assertStatus(429);
    }

    public function test_admin_can_reset_another_users_password(): void
    {
        $admin = User::factory()->withRole(RoleName::Administrator)->withTwoFactor()->create();
        $target = $this->viewer();

        $this->actingAs($admin)->patchJson("/api/v1/users/{$target->uuid}", [
            'password' => self::NEW,
            'password_confirmation' => self::NEW,
        ])->assertOk();

        $this->assertTrue(Hash::check(self::NEW, $target->fresh()->password));
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.password_reset']);
    }

    public function test_empty_password_leaves_it_unchanged(): void
    {
        $admin = User::factory()->withRole(RoleName::Administrator)->withTwoFactor()->create();
        $target = $this->viewer();

        $this->actingAs($admin)->patchJson("/api/v1/users/{$target->uuid}", [
            'name' => 'Renommé',
            'password' => '',
            'password_confirmation' => '',
        ])->assertOk();

        $target->refresh();
        $this->assertSame('Renommé', $target->name);
        $this->assertTrue(Hash::check(self::CURRENT, $target->password));
        $this->assertDatabaseMissing('audit_logs', ['action' => 'user.password_reset']);
    }

    public function test_admin_reset_requires_confirmation_and_strength(): void
    {
        $admin = User::factory()->withRole(RoleName::Administrator)->withTwoFactor()->create();
        $target = $this->viewer();

        $this->actingAs($admin)->patchJson("/api/v1/users/{$target->uuid}", ['password' => self::NEW])
            ->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->actingAs($admin)->patchJson("/api/v1/users/{$target->uuid}", ['password' => 'weak', 'password_confirmation' => 'weak'])
            ->assertUnprocessable()->assertJsonValidationErrors('password');
    }

    public function test_non_admin_cannot_reset_a_password(): void
    {
        $operator = User::factory()->withRole(RoleName::MarketingOperator)->withTwoFactor()->create();
        $target = $this->viewer();

        $this->actingAs($operator)->patchJson("/api/v1/users/{$target->uuid}", [
            'password' => self::NEW,
            'password_confirmation' => self::NEW,
        ])->assertForbidden();

        $this->assertTrue(Hash::check(self::CURRENT, $target->fresh()->password));
    }

    public function test_admin_cannot_reset_own_password_through_user_update(): void
    {
        $admin = User::factory()->withRole(RoleName::Administrator)->withTwoFactor()->create(['password' => self::CURRENT]);

        $this->actingAs($admin)->patchJson("/api/v1/users/{$admin->uuid}", [
            'password' => self::NEW,
            'password_confirmation' => self::NEW,
        ])->assertUnprocessable()->assertJsonValidationErrors('password');

        $this->assertTrue(Hash::check(self::CURRENT, $admin->fresh()->password));
    }
}
