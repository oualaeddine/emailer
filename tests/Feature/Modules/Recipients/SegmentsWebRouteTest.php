<?php

namespace Tests\Feature\Modules\Recipients;

use App\Domain\Enums\RoleName;
use App\Modules\Identity\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * WP-54U — routes/web/segments.php: Contacts › Segments list and builder pages.
 */
class SegmentsWebRouteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_administrator_can_open_segments_list(): void
    {
        $admin = User::factory()->withRole(RoleName::Administrator)->withTwoFactor()->create();

        $this->actingAs($admin)->get('/contacts/segments')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Contacts/Segments', false));
    }

    public function test_administrator_can_open_new_segment_builder(): void
    {
        $admin = User::factory()->withRole(RoleName::Administrator)->withTwoFactor()->create();

        $this->actingAs($admin)->get('/contacts/segments/new')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Contacts/SegmentEdit', false)->where('uuid', null));
    }

    public function test_administrator_can_open_existing_segment_builder(): void
    {
        $admin = User::factory()->withRole(RoleName::Administrator)->withTwoFactor()->create();
        $uuid = (string) Str::uuid();

        $this->actingAs($admin)->get("/contacts/segments/{$uuid}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Contacts/SegmentEdit', false)->where('uuid', $uuid));
    }

    public function test_viewer_can_open_segments_list(): void
    {
        $viewer = User::factory()->withRole(RoleName::Viewer)->withTwoFactor()->create();

        $this->actingAs($viewer)->get('/contacts/segments')->assertOk();
    }

    public function test_viewer_is_blocked_from_new_segment_but_can_open_existing_read_only(): void
    {
        $viewer = User::factory()->withRole(RoleName::Viewer)->withTwoFactor()->create();
        $uuid = (string) Str::uuid();

        $this->actingAs($viewer)->get('/contacts/segments/new')->assertStatus(403);
        $this->actingAs($viewer)->get("/contacts/segments/{$uuid}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Contacts/SegmentEdit', false)
                ->where('uuid', $uuid)
                ->where('auth.permissions', fn ($permissions) => collect($permissions)->contains('recipients.view')
                    && ! collect($permissions)->contains('segments.manage')));
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/contacts/segments')->assertRedirect('/login');
    }
}
