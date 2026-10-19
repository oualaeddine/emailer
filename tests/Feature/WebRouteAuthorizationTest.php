<?php

namespace Tests\Feature;

use App\Domain\Enums\RoleName;
use App\Modules\Identity\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebRouteAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_viewer_is_blocked_from_admin_users_page(): void
    {
        $viewer = User::factory()->withRole(RoleName::Viewer)->withTwoFactor()->create();

        $response = $this->actingAs($viewer)->get('/admin/users');

        $response->assertStatus(403);
    }

    public function test_viewer_is_blocked_from_smtp_settings(): void
    {
        $viewer = User::factory()->withRole(RoleName::Viewer)->withTwoFactor()->create();

        $response = $this->actingAs($viewer)->get('/smtp');

        $response->assertStatus(403);
    }

    public function test_viewer_is_blocked_from_audit_logs(): void
    {
        $viewer = User::factory()->withRole(RoleName::Viewer)->withTwoFactor()->create();

        $response = $this->actingAs($viewer)->get('/admin/audit-log');

        $response->assertStatus(403);
    }

    public function test_administrator_can_access_admin_users_page(): void
    {
        $admin = User::factory()->withRole(RoleName::Administrator)->withTwoFactor()->create();

        $response = $this->actingAs($admin)->get('/admin/users');

        $response->assertOk();
    }

    public function test_administrator_can_access_smtp_settings(): void
    {
        $admin = User::factory()->withRole(RoleName::Administrator)->withTwoFactor()->create();

        $response = $this->actingAs($admin)->get('/smtp');

        $response->assertOk();
    }

    public function test_viewer_is_blocked_from_contacts_blocklist(): void
    {
        $viewer = User::factory()->withRole(RoleName::Viewer)->withTwoFactor()->create();

        $response = $this->actingAs($viewer)->get('/contacts/blocklist');

        $response->assertStatus(403);
    }

    public function test_administrator_can_access_renamed_contact_pages(): void
    {
        $admin = User::factory()->withRole(RoleName::Administrator)->withTwoFactor()->create();

        foreach (['/contacts', '/contacts/imports/new', '/contacts/pagejaunes', '/contacts/blocklist', '/statistics'] as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function legacyRedirectProvider(): array
    {
        return [
            'recipients' => ['/recipients', '/contacts'],
            'recipients import' => ['/recipients/import', '/contacts/imports/new'],
            'recipients pagejaunes' => ['/recipients/import/pagejaunes', '/contacts/pagejaunes'],
            'suppression' => ['/suppression', '/contacts/blocklist'],
            'reporting' => ['/reporting', '/statistics'],
        ];
    }

    /**
     * @dataProvider legacyRedirectProvider
     */
    public function test_legacy_url_permanently_redirects_to_new_url(string $old, string $new): void
    {
        $viewer = User::factory()->withRole(RoleName::Viewer)->withTwoFactor()->create();

        $response = $this->actingAs($viewer)->get($old);

        $response->assertStatus(301);
        $response->assertRedirect($new);
    }
}
