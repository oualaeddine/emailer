<?php

namespace Tests\Feature\Modules\Recipients;

use App\Domain\Enums\RoleName;
use App\Modules\Identity\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttributesWebRouteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_administrator_can_access_page(): void
    {
        $admin = User::factory()->withRole(RoleName::Administrator)->withTwoFactor()->create();

        $this->actingAs($admin)->get('/contacts/attributes')->assertOk();
    }

    public function test_viewer_can_access_page(): void
    {
        $viewer = User::factory()->withRole(RoleName::Viewer)->withTwoFactor()->create();

        $this->actingAs($viewer)->get('/contacts/attributes')->assertOk();
    }

    public function test_guest_is_redirected(): void
    {
        $this->get('/contacts/attributes')->assertRedirect();
    }
}
