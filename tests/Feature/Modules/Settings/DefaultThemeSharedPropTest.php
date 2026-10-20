<?php

namespace Tests\Feature\Modules\Settings;

use App\Domain\Enums\RoleName;
use App\Domain\Enums\SettingValueType;
use App\Modules\Identity\Models\User;
use App\Modules\Settings\Services\SettingsService;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * docs/07-ui-design.md §7.11 — the organisation default theme
 * (`branding.default_theme`) reaches every Inertia page as the shared
 * `branding.defaultTheme` prop; the frontend applies it unless the user
 * picked their own theme.
 */
class DefaultThemeSharedPropTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->seed(SettingsSeeder::class);
    }

    public function test_default_theme_is_light_when_not_configured(): void
    {
        $admin = User::factory()->withRole(RoleName::Administrator)->withTwoFactor()->create();

        $this->actingAs($admin)->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('branding.defaultTheme', 'light'));
    }

    public function test_saved_default_theme_is_shared_with_every_user(): void
    {
        $admin = User::factory()->withRole(RoleName::Administrator)->withTwoFactor()->create();
        $viewer = User::factory()->withRole(RoleName::Viewer)->withTwoFactor()->create();

        $this->actingAs($admin)->patchJson('/api/v1/settings', [
            'settings' => ['branding.default_theme' => 'dark'],
        ])->assertOk();

        $this->actingAs($viewer)->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('branding.defaultTheme', 'dark'));

        $this->actingAs($admin)->patchJson('/api/v1/settings', [
            'settings' => ['branding.default_theme' => 'system'],
        ])->assertOk();

        $this->actingAs($viewer)->get('/dashboard')
            ->assertInertia(fn (Assert $page) => $page->where('branding.defaultTheme', 'system'));
    }

    public function test_default_theme_is_shared_on_guest_pages(): void
    {
        app(SettingsService::class)->setSystemValue('branding.default_theme', 'dark', SettingValueType::String);

        $this->get('/login')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('branding.defaultTheme', 'dark'));
    }

    public function test_unknown_default_theme_is_rejected(): void
    {
        $admin = User::factory()->withRole(RoleName::Administrator)->withTwoFactor()->create();

        $this->actingAs($admin)->patchJson('/api/v1/settings', [
            'settings' => ['branding.default_theme' => 'rose'],
        ])->assertUnprocessable();
    }
}
