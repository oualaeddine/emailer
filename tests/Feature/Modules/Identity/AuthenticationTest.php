<?php

namespace Tests\Feature\Modules\Identity;

use App\Domain\Enums\RoleName;
use App\Modules\Identity\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * docs/29-api-specification.md §29.2 — POST /login, POST /logout.
 * docs/28-security.md §28.1 — lockout policy.
 */
class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_login_page_renders_for_guests(): void
    {
        $this->get('/login')->assertOk();
    }

    public function test_a_user_can_log_in_with_correct_credentials(): void
    {
        $user = User::factory()->withRole(RoleName::Viewer)->create([
            'email' => 'user@example.com',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->post('/login', [
            'email' => 'user@example.com',
            'password' => 'password123',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->last_login_at);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        User::factory()->withRole(RoleName::Viewer)->create([
            'email' => 'user@example.com',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->post('/login', [
            'email' => 'user@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_a_deactivated_user_cannot_log_in(): void
    {
        User::factory()->withRole(RoleName::Viewer)->inactive()->create([
            'email' => 'inactive@example.com',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->post('/login', [
            'email' => 'inactive@example.com',
            'password' => 'password123',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_too_many_failed_attempts_are_rate_limited(): void
    {
        User::factory()->withRole(RoleName::Viewer)->create([
            'email' => 'user@example.com',
            'password' => Hash::make('password123'),
        ]);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => 'user@example.com', 'password' => 'wrong']);
        }

        $response = $this->postJson('/login', ['email' => 'user@example.com', 'password' => 'wrong']);

        $response->assertStatus(429);

        RateLimiter::clear('user@example.com|127.0.0.1');
    }

    public function test_an_authenticated_user_can_log_out(): void
    {
        $user = User::factory()->withRole(RoleName::Viewer)->create();

        $response = $this->actingAs($user)->post('/logout');

        $response->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_successful_login_is_audited(): void
    {
        // phpunit.xml sets QUEUE_CONNECTION=sync, so the queued
        // AuditListener (docs/31-events.md §31.4) runs inline here —
        // no queue worker needed to observe its effect.
        $user = User::factory()->withRole(RoleName::Viewer)->create([
            'email' => 'user@example.com',
            'password' => Hash::make('password123'),
        ]);

        $this->post('/login', ['email' => 'user@example.com', 'password' => 'password123']);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'action' => 'auth.login_succeeded',
        ]);
    }
}
