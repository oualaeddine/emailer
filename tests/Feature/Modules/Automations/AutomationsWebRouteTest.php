<?php

namespace Tests\Feature\Modules\Automations;

use App\Domain\Enums\RoleName;
use App\Modules\Identity\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * WP-91U — routes/web/automations.php: Automatisations list and workflow editor pages.
 */
class AutomationsWebRouteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_administrator_can_open_automations_list(): void
    {
        $admin = User::factory()->withRole(RoleName::Administrator)->withTwoFactor()->create();

        $this->actingAs($admin)->get('/automations')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Automations/Index', false));
    }

    public function test_administrator_can_open_new_automation_editor(): void
    {
        $admin = User::factory()->withRole(RoleName::Administrator)->withTwoFactor()->create();

        $this->actingAs($admin)->get('/automations/new')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Automations/Edit', false)->where('uuid', null));
    }

    public function test_administrator_can_open_existing_automation_editor(): void
    {
        $admin = User::factory()->withRole(RoleName::Administrator)->withTwoFactor()->create();
        $uuid = (string) Str::uuid();

        $this->actingAs($admin)->get("/automations/{$uuid}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Automations/Edit', false)->where('uuid', $uuid));
    }

    public function test_viewer_is_forbidden_on_every_automations_page(): void
    {
        $viewer = User::factory()->withRole(RoleName::Viewer)->withTwoFactor()->create();
        $uuid = (string) Str::uuid();

        $this->actingAs($viewer)->get('/automations')->assertStatus(403);
        $this->actingAs($viewer)->get('/automations/new')->assertStatus(403);
        $this->actingAs($viewer)->get("/automations/{$uuid}")->assertStatus(403);
    }

    public function test_non_uuid_automation_path_is_not_found(): void
    {
        $admin = User::factory()->withRole(RoleName::Administrator)->withTwoFactor()->create();

        $this->actingAs($admin)->get('/automations/not-a-uuid')->assertNotFound();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/automations')->assertRedirect('/login');
        $this->get('/automations/new')->assertRedirect('/login');
    }
}
