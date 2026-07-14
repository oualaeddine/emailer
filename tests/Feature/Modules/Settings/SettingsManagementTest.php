<?php

namespace Tests\Feature\Modules\Settings;

use App\Domain\Enums\RoleName;
use App\Modules\Identity\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * docs/29-api-specification.md §29.11, docs/26-rbac.md §26.3 —
 * `settings.manage` vs `settings.branding_only`.
 */
class SettingsManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->seed(SettingsSeeder::class);
    }

    public function test_administrator_can_read_settings(): void
    {
        $admin = User::factory()->withRole(RoleName::Administrator)->create();

        $response = $this->actingAs($admin)->getJson('/api/v1/settings');

        $response->assertOk();
        $response->assertJsonFragment(['key' => 'branding.organization_name']);
    }

    public function test_marketing_operator_cannot_read_settings(): void
    {
        $operator = User::factory()->withRole(RoleName::MarketingOperator)->create();

        $this->actingAs($operator)->getJson('/api/v1/settings')->assertForbidden();
    }

    public function test_marketing_manager_can_update_branding_settings(): void
    {
        $manager = User::factory()->withRole(RoleName::MarketingManager)->create();

        $response = $this->actingAs($manager)->patchJson('/api/v1/settings', [
            'settings' => ['branding.organization_name' => 'Nouvelle Organisation'],
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('settings', ['key' => 'branding.organization_name']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'settings.updated']);
    }

    public function test_marketing_manager_cannot_update_non_branding_settings(): void
    {
        $manager = User::factory()->withRole(RoleName::MarketingManager)->create();

        $response = $this->actingAs($manager)->patchJson('/api/v1/settings', [
            'settings' => ['some.other.key' => 'value'],
        ]);

        $response->assertForbidden();
    }

    public function test_updating_a_setting_invalidates_its_cache_entry(): void
    {
        $admin = User::factory()->withRole(RoleName::Administrator)->create();
        $settings = app(\App\Modules\Settings\Services\SettingsService::class);

        // Prime the cache via SettingsService::get() (docs/02-system-architecture.md §2.7).
        $settings->get('branding.organization_name');
        $this->assertTrue(Cache::has('settings.branding.organization_name'));

        $this->actingAs($admin)->patchJson('/api/v1/settings', [
            'settings' => ['branding.organization_name' => 'Autre Nom'],
        ]);

        // ConfigCacheInvalidationListener (docs/31-events.md §31.4) must have
        // cleared the stale cached value synchronously.
        $this->assertFalse(Cache::has('settings.branding.organization_name'));
    }

    public function test_secret_settings_never_return_their_value(): void
    {
        $admin = User::factory()->withRole(RoleName::Administrator)->create();

        app(\App\Modules\Settings\Services\SettingsService::class)->set(
            'some.secret.key',
            'super-secret-value',
            \App\Domain\Enums\SettingValueType::String,
            true,
            $admin,
        );

        $response = $this->actingAs($admin)->getJson('/api/v1/settings');

        $response->assertOk();
        $response->assertJsonFragment(['key' => 'some.secret.key', 'value' => null, 'is_secret' => true]);
        $response->assertJsonMissing(['value' => 'super-secret-value']);
    }
}
