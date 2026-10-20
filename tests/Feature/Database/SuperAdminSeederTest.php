<?php

namespace Tests\Feature\Database;

use App\Modules\Identity\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Production seeding must not create the well-known "password" accounts, and
 * re-running db:seed must never reset an administrator's password.
 */
class SuperAdminSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function seedAs(string $env): void
    {
        // Invoked directly: db:seed in production would prompt for confirmation.
        $this->app['env'] = $env;
        $this->app->make(SuperAdminSeeder::class)->setContainer($this->app)->__invoke();
        $this->app['env'] = 'testing';
    }

    public function test_non_production_seeds_the_two_development_accounts(): void
    {
        $this->seedAs('local');

        $this->assertTrue(Hash::check('password', User::query()->where('email', 'admin@pagejaunes-mailer.local')->value('password')));
        $this->assertTrue(User::query()->where('email', 'admin@pagejaunes.dz')->exists());
    }

    public function test_production_seeds_one_configured_admin_and_never_resets_it(): void
    {
        config(['app.super_admin' => ['name' => 'Admin PJ', 'email' => 'Chef@PageJaunes.dz', 'password' => 'Un-Mot-De-Passe-Long-42']]);

        $this->seedAs('production');

        $this->assertSame(1, User::query()->count());
        $admin = User::query()->where('email', 'chef@pagejaunes.dz')->firstOrFail();
        $this->assertTrue(Hash::check('Un-Mot-De-Passe-Long-42', $admin->password));
        $this->assertTrue($admin->is_active);
        $this->assertNotNull($admin->email_verified_at);
        $this->assertFalse(User::query()->where('email', 'admin@pagejaunes-mailer.local')->exists());

        $admin->forceFill(['password' => Hash::make('changé-par-l-admin')])->save();
        $this->seedAs('production');

        $this->assertSame(1, User::query()->count());
        $this->assertTrue(Hash::check('changé-par-l-admin', $admin->fresh()->password));
    }

    public function test_production_without_password_generates_a_random_one(): void
    {
        config(['app.super_admin' => ['name' => 'Admin', 'email' => 'admin@pagejaunes.dz', 'password' => null]]);

        $this->seedAs('production');

        $hash = User::query()->where('email', 'admin@pagejaunes.dz')->value('password');
        $this->assertNotNull($hash);
        $this->assertFalse(Hash::check('password', $hash));
    }
}
