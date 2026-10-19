<?php

namespace Tests\Feature\Modules\Recipients;

use App\Domain\Enums\RoleName;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * docs/43-brevo-parity-plan.md §43.4.6 (WP-51) — GET /contacts renders the
 * v2 Contacts page behind `recipients.view`.
 */
class ContactsWebRouteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_route_is_named_contacts_index(): void
    {
        $this->assertSame(url('/contacts'), route('contacts.index'));
    }

    public function test_administrator_sees_the_contacts_page(): void
    {
        $admin = User::factory()->withRole(RoleName::Administrator)->withTwoFactor()->create();

        $this->actingAs($admin)
            ->get('/contacts')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('Contacts/Index', false));
    }

    public function test_viewer_with_recipients_view_sees_the_contacts_page(): void
    {
        $viewer = User::factory()->withRole(RoleName::Viewer)->withTwoFactor()->create();
        $this->assertTrue($viewer->can('recipients.view'));

        $this->actingAs($viewer)
            ->get('/contacts')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('Contacts/Index', false));
    }

    public function test_user_without_recipients_view_is_forbidden(): void
    {
        // A role with no permission at all (the factory default is Viewer).
        $user = User::factory()->withTwoFactor()->create(['role_id' => Role::factory()->create()->id]);
        $this->assertFalse($user->can('recipients.view'));

        $this->actingAs($user)->get('/contacts')->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/contacts')->assertRedirect();
    }
}
