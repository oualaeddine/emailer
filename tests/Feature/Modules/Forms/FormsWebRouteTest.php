<?php

namespace Tests\Feature\Modules\Forms;

use App\Domain\Enums\RoleName;
use App\Modules\Identity\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * WP-90U — routes/web/forms-pages.php: Marketing › Formulaires list and builder pages.
 */
class FormsWebRouteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_administrator_can_open_forms_list(): void
    {
        $admin = User::factory()->withRole(RoleName::Administrator)->withTwoFactor()->create();

        $this->actingAs($admin)->get('/marketing/forms')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Marketing/Forms', false));
    }

    public function test_administrator_can_open_form_builder(): void
    {
        $admin = User::factory()->withRole(RoleName::Administrator)->withTwoFactor()->create();
        $uuid = (string) Str::uuid();

        $this->actingAs($admin)->get("/marketing/forms/{$uuid}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Marketing/FormEdit', false)->where('uuid', $uuid));
    }

    public function test_route_names_resolve(): void
    {
        $uuid = (string) Str::uuid();

        $this->assertSame(url('/marketing/forms'), route('marketing.forms'));
        $this->assertSame(url("/marketing/forms/{$uuid}"), route('marketing.forms.edit', ['form' => $uuid]));
    }

    public function test_viewer_can_open_list_but_not_builder(): void
    {
        $viewer = User::factory()->withRole(RoleName::Viewer)->withTwoFactor()->create();
        $uuid = (string) Str::uuid();

        $this->actingAs($viewer)->get('/marketing/forms')->assertOk();
        $this->actingAs($viewer)->get("/marketing/forms/{$uuid}")->assertStatus(403);
    }

    public function test_builder_route_requires_a_uuid(): void
    {
        $admin = User::factory()->withRole(RoleName::Administrator)->withTwoFactor()->create();

        $this->actingAs($admin)->get('/marketing/forms/not-a-uuid')->assertNotFound();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/marketing/forms')->assertRedirect('/login');
        $this->get('/marketing/forms/'.Str::uuid())->assertRedirect('/login');
    }
}
