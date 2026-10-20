<?php

namespace Tests\Feature\Modules\Identity;

use App\Domain\Enums\RoleName;
use App\Modules\Identity\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Admin reset of a user's two-factor enrolment (POST /api/v1/users/{uuid}/two-factor/reset)
 * and the CLI access-recovery commands (`users:reset-two-factor`, `users:reset-password`).
 */
class TwoFactorResetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function admin(): User
    {
        return User::factory()->withRole(RoleName::Administrator)->withTwoFactor()->create();
    }

    private function target(): User
    {
        return User::factory()->withRole(RoleName::Viewer)->withTwoFactor()->create([
            'email' => 'cible@example.com',
            'password' => Hash::make('password123'),
        ]);
    }

    private function url(User $user): string
    {
        return "/api/v1/users/{$user->uuid}/two-factor/reset";
    }

    private function insertSession(string $id, ?int $userId): void
    {
        DB::table('sessions')->insert([
            'id' => $id,
            'user_id' => $userId,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'phpunit',
            'payload' => 'x',
            'last_activity' => time(),
        ]);
    }

    // --- Admin endpoint ----------------------------------------------------

    public function test_admin_resets_two_factor_clears_fields_and_audits(): void
    {
        $admin = $this->admin();
        $target = $this->target();
        $secret = $target->two_factor_secret;

        $this->actingAs($admin)->postJson($this->url($target))
            ->assertOk()
            ->assertJson(['message' => 'Double authentification réinitialisée.']);

        $target->refresh();
        $this->assertNull($target->two_factor_secret);
        $this->assertNull($target->two_factor_recovery_codes);
        $this->assertNull($target->two_factor_confirmed_at);
        $this->assertFalse($target->hasTwoFactorEnabled());

        $log = DB::table('audit_logs')->where('action', 'user.two_factor_reset')->first();
        $this->assertNotNull($log);
        $this->assertSame($admin->id, (int) $log->user_id);
        $this->assertSame($target->id, (int) $log->auditable_id);
        // Never log secrets.
        $this->assertStringNotContainsString($secret, json_encode($log));
        $this->assertStringNotContainsString('AAAAA-BBBBB', json_encode($log));
    }

    public function test_reset_drops_target_sessions_and_tokens_but_keeps_other_users_sessions(): void
    {
        config(['session.driver' => 'database']);
        $admin = $this->admin();
        $target = $this->target();
        $target->createToken('erp');
        $admin->createToken('mine');
        $this->insertSession('target-session-1', $target->id);
        $this->insertSession('target-session-2', $target->id);
        $this->insertSession('admin-session', $admin->id);

        $this->actingAs($admin)->postJson($this->url($target))->assertOk();

        $this->assertDatabaseMissing('sessions', ['user_id' => $target->id]);
        $this->assertDatabaseHas('sessions', ['id' => 'admin-session']);
        $this->assertSame(0, $target->tokens()->count());
        $this->assertSame(1, $admin->tokens()->count());
    }

    public function test_non_admin_cannot_reset_two_factor(): void
    {
        $operator = User::factory()->withRole(RoleName::MarketingManager)->withTwoFactor()->create();
        $target = $this->target();

        $this->actingAs($operator)->postJson($this->url($target))->assertForbidden();

        $this->assertTrue($target->fresh()->hasTwoFactorEnabled());
        $this->assertDatabaseMissing('audit_logs', ['action' => 'user.two_factor_reset']);
    }

    public function test_admin_cannot_reset_own_two_factor(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->postJson($this->url($admin))
            ->assertUnprocessable()
            ->assertJsonPath('errors.user.0', 'Vous ne pouvez pas réinitialiser votre propre double authentification ; demandez à un autre administrateur.');

        $this->assertTrue($admin->fresh()->hasTwoFactorEnabled());
    }

    public function test_api_key_cannot_reach_the_reset_endpoint(): void
    {
        $admin = $this->admin();
        $target = $this->target();
        $plain = $admin->createToken('erp', ['*'])->plainTextToken;

        $this->app['auth']->forgetGuards();
        $this->withHeaders(['Authorization' => 'Bearer '.$plain])
            ->postJson($this->url($target))
            ->assertForbidden();

        $this->assertTrue($target->fresh()->hasTwoFactorEnabled());
    }

    public function test_guest_is_unauthenticated(): void
    {
        $this->postJson($this->url($this->target()))->assertUnauthorized();
    }

    public function test_after_reset_the_next_login_is_sent_to_the_setup(): void
    {
        $admin = $this->admin();
        $target = $this->target();

        $this->actingAs($admin)->postJson($this->url($target))->assertOk();
        $this->app['auth']->forgetGuards();

        // Password only: no challenge any more, but every page bounces to enrolment.
        $this->post('/login', ['email' => 'cible@example.com', 'password' => 'password123'])
            ->assertRedirect();
        $this->assertAuthenticatedAs($target);
        $this->get('/dashboard')->assertRedirect(route('two-factor.setup'));
        $this->get('/two-factor/setup')->assertOk();
        $this->assertNotNull($target->fresh()->two_factor_secret);
        $this->assertFalse($target->fresh()->hasTwoFactorEnabled());
    }

    // --- users:reset-two-factor -------------------------------------------

    public function test_command_resets_two_factor_with_force_and_audits_without_actor(): void
    {
        $target = $this->target();
        $target->createToken('erp');

        $this->artisan('users:reset-two-factor', ['email' => 'CIBLE@example.com', '--force' => true])
            ->expectsOutputToContain('Double authentification réinitialisée pour cible@example.com')
            ->assertExitCode(0);

        $target->refresh();
        $this->assertFalse($target->hasTwoFactorEnabled());
        $this->assertNull($target->two_factor_secret);
        $this->assertNull($target->two_factor_recovery_codes);
        $this->assertSame(0, $target->tokens()->count());
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'user.two_factor_reset',
            'user_id' => null,
            'auditable_id' => $target->id,
        ]);
    }

    public function test_command_asks_for_confirmation_without_force(): void
    {
        $target = $this->target();

        $this->artisan('users:reset-two-factor', ['email' => 'cible@example.com'])
            ->expectsConfirmation('Réinitialiser la double authentification de '.$target->name.' <cible@example.com> ? Ses sessions et clés API seront révoquées.', 'no')
            ->expectsOutputToContain('Opération annulée.')
            ->assertExitCode(1);

        $this->assertTrue($target->fresh()->hasTwoFactorEnabled());
        $this->assertDatabaseMissing('audit_logs', ['action' => 'user.two_factor_reset']);
    }

    public function test_command_reports_unknown_email(): void
    {
        $this->artisan('users:reset-two-factor', ['email' => 'inconnu@example.com', '--force' => true])
            ->expectsOutputToContain('Aucun utilisateur ne correspond à l’adresse « inconnu@example.com ».')
            ->assertExitCode(1);

        $this->assertDatabaseMissing('audit_logs', ['action' => 'user.two_factor_reset']);
    }

    // --- users:reset-password ----------------------------------------------

    public function test_reset_password_command_sets_a_strong_password_and_audits_without_actor(): void
    {
        config(['session.driver' => 'database']);
        $target = $this->target();
        $target->createToken('erp');
        $this->insertSession('target-session', $target->id);

        $this->artisan('users:reset-password', ['email' => 'cible@example.com', '--force' => true])
            ->expectsOutputToContain('Mot de passe réinitialisé pour cible@example.com')
            ->assertExitCode(0);

        $target->refresh();
        $this->assertFalse(Hash::check('password123', $target->password));
        $this->assertSame(0, $target->tokens()->count());
        $this->assertDatabaseMissing('sessions', ['user_id' => $target->id]);
        // 2FA enrolment is untouched by a password reset.
        $this->assertTrue($target->hasTwoFactorEnabled());
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'user.password_reset',
            'user_id' => null,
            'auditable_id' => $target->id,
        ]);
    }

    public function test_reset_password_command_prints_the_new_password_once(): void
    {
        $target = $this->target();

        $this->assertSame(0, Artisan::call('users:reset-password', ['email' => 'cible@example.com', '--force' => true]));

        $lines = array_values(array_filter(array_map('trim', explode("\n", Artisan::output()))));
        $printed = end($lines);

        $this->assertSame(20, strlen($printed));
        $this->assertMatchesRegularExpression('/[A-Z]/', $printed);
        $this->assertMatchesRegularExpression('/[a-z]/', $printed);
        $this->assertMatchesRegularExpression('/\d/', $printed);
        $this->assertTrue(Hash::check($printed, $target->fresh()->password));
        // The password never reaches the audit trail.
        $this->assertStringNotContainsString($printed, json_encode(DB::table('audit_logs')->get()));
    }

    public function test_reset_password_command_reports_unknown_email(): void
    {
        $this->artisan('users:reset-password', ['email' => 'inconnu@example.com', '--force' => true])
            ->expectsOutputToContain('Aucun utilisateur ne correspond')
            ->assertExitCode(1);

        $this->assertDatabaseMissing('audit_logs', ['action' => 'user.password_reset']);
    }
}
