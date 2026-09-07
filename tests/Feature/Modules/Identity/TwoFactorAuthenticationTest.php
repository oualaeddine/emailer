<?php

namespace Tests\Feature\Modules\Identity;

use App\Domain\Enums\RoleName;
use App\Modules\Identity\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

/**
 * docs/28-security.md §28.1 — TOTP two-factor authentication.
 */
class TwoFactorAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    private Google2FA $google2fa;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->google2fa = new Google2FA();
    }

    private function twoFactorUser(): User
    {
        return User::factory()->withRole(RoleName::Viewer)->withTwoFactor()->create([
            'email' => 'secure@example.com',
            'password' => Hash::make('password123'),
        ]);
    }

    public function test_password_alone_does_not_authenticate_a_2fa_user(): void
    {
        $this->twoFactorUser();

        $response = $this->post('/login', [
            'email' => 'secure@example.com',
            'password' => 'password123',
        ]);

        $response->assertRedirect(route('two-factor.challenge'));
        $this->assertGuest();
    }

    public function test_valid_totp_code_completes_login(): void
    {
        $user = $this->twoFactorUser();

        $this->post('/login', ['email' => 'secure@example.com', 'password' => 'password123']);

        $code = $this->google2fa->getCurrentOtp($user->two_factor_secret);

        $response = $this->post('/two-factor/challenge', ['code' => $code]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_invalid_totp_code_is_rejected(): void
    {
        $this->twoFactorUser();

        $this->post('/login', ['email' => 'secure@example.com', 'password' => 'password123']);

        $response = $this->post('/two-factor/challenge', ['code' => '000000']);

        $response->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_recovery_code_completes_login_and_is_consumed(): void
    {
        $user = $this->twoFactorUser();

        $this->post('/login', ['email' => 'secure@example.com', 'password' => 'password123']);

        $response = $this->post('/two-factor/challenge', ['recovery_code' => 'AAAAA-BBBBB']);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);

        // The used code must no longer be present.
        $this->assertNotContains('AAAAA-BBBBB', $user->fresh()->two_factor_recovery_codes);
    }

    public function test_challenge_is_inaccessible_without_a_pending_login(): void
    {
        $this->get('/two-factor/challenge')->assertRedirect(route('login'));
    }

    public function test_two_factor_challenge_failure_is_audited(): void
    {
        $user = $this->twoFactorUser();

        $this->post('/login', ['email' => 'secure@example.com', 'password' => 'password123']);
        $this->post('/two-factor/challenge', ['code' => '000000']);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'action' => 'auth.two_factor_failed',
        ]);
    }

    public function test_enrolled_user_challenge_completion_is_audited(): void
    {
        $user = $this->twoFactorUser();

        $this->post('/login', ['email' => 'secure@example.com', 'password' => 'password123']);
        $code = $this->google2fa->getCurrentOtp($user->two_factor_secret);
        $this->post('/two-factor/challenge', ['code' => $code]);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'action' => 'auth.login_succeeded',
        ]);
    }

    // --- Mandatory enrolment ---------------------------------------------

    public function test_unenrolled_user_is_forced_to_setup(): void
    {
        User::factory()->withRole(RoleName::Viewer)->withoutTwoFactor()->create([
            'email' => 'new@example.com',
            'password' => Hash::make('password123'),
        ]);

        $this->post('/login', ['email' => 'new@example.com', 'password' => 'password123']);

        // Authenticated, but any app page bounces to enrolment.
        $this->assertAuthenticated();
        $this->get('/dashboard')->assertRedirect(route('two-factor.setup'));
    }

    public function test_unenrolled_user_is_blocked_from_the_api(): void
    {
        $user = User::factory()->withRole(RoleName::Viewer)->withoutTwoFactor()->create();

        $this->actingAs($user)
            ->getJson('/api/v1/me')
            ->assertStatus(403)
            ->assertJson(['code' => 'two_factor_enrollment_required']);
    }

    public function test_enrolled_user_reaches_the_dashboard(): void
    {
        $user = User::factory()->withRole(RoleName::Viewer)->withTwoFactor()->create();

        $this->actingAs($user)->get('/dashboard')->assertOk();
    }

    public function test_user_can_confirm_enrolment_with_a_valid_code(): void
    {
        $user = User::factory()->withRole(RoleName::Viewer)->withoutTwoFactor()->create([
            'password' => Hash::make('password123'),
        ]);

        // Visiting setup provisions an unconfirmed secret.
        $this->actingAs($user)->get('/two-factor/setup')->assertOk();
        $secret = $user->fresh()->two_factor_secret;
        $this->assertNotNull($secret);
        $this->assertFalse($user->fresh()->hasTwoFactorEnabled());

        $code = $this->google2fa->getCurrentOtp($secret);
        $response = $this->actingAs($user)->post('/two-factor/setup', ['code' => $code]);

        $response->assertRedirect(route('two-factor.setup'));
        $this->assertTrue($user->fresh()->hasTwoFactorEnabled());
        $this->assertNotEmpty($user->fresh()->two_factor_recovery_codes);
    }

    public function test_enrolment_confirmation_is_audited(): void
    {
        $user = User::factory()->withRole(RoleName::Viewer)->withoutTwoFactor()->create([
            'password' => Hash::make('password123'),
        ]);

        $this->actingAs($user)->get('/two-factor/setup');
        $secret = $user->fresh()->two_factor_secret;
        $this->actingAs($user)->post('/two-factor/setup', [
            'code' => $this->google2fa->getCurrentOtp($secret),
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'action' => 'auth.two_factor_enabled',
        ]);
    }

    public function test_enrolment_rejects_an_invalid_code(): void
    {
        $user = User::factory()->withRole(RoleName::Viewer)->withoutTwoFactor()->create();

        $this->actingAs($user)->get('/two-factor/setup');
        $response = $this->actingAs($user)->post('/two-factor/setup', ['code' => '000000']);

        $response->assertSessionHasErrors('code');
        $this->assertFalse($user->fresh()->hasTwoFactorEnabled());
    }

    // --- Brute-force ceiling (IP-rotation bypass fix) --------------------

    public function test_failed_logins_are_capped_per_email_across_rotating_ips(): void
    {
        User::factory()->withRole(RoleName::Viewer)->create([
            'email' => 'target@example.com',
            'password' => Hash::make('password123'),
        ]);

        // Each attempt from a different source IP would dodge the per-IP
        // limit; the per-email ceiling (10) must still trip.
        for ($i = 1; $i <= 10; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => "203.0.113.$i"])
                ->post('/login', ['email' => 'target@example.com', 'password' => 'wrong']);
        }

        $response = $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.250'])
            ->postJson('/login', ['email' => 'target@example.com', 'password' => 'wrong']);

        $response->assertStatus(429);

        RateLimiter::clear('login-email|target@example.com');
    }
}
