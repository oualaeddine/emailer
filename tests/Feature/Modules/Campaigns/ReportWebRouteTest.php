<?php

namespace Tests\Feature\Modules\Campaigns;

use App\Domain\Enums\RoleName;
use App\Modules\Identity\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * WP-71 — routes/web/campaigns.php: GET /campaigns/{campaign}/report renders
 * the Campaigns/Report page (docs/43 §43.4.15) behind `can:campaigns.view`.
 */
class ReportWebRouteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_administrator_can_open_campaign_report(): void
    {
        $admin = User::factory()->withRole(RoleName::Administrator)->withTwoFactor()->create();
        $uuid = (string) Str::uuid();

        $this->actingAs($admin)->get("/campaigns/{$uuid}/report")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Campaigns/Report', false)->where('campaignId', $uuid));
    }

    public function test_viewer_can_open_campaign_report(): void
    {
        $viewer = User::factory()->withRole(RoleName::Viewer)->withTwoFactor()->create();
        $uuid = (string) Str::uuid();

        $this->actingAs($viewer)->get("/campaigns/{$uuid}/report")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Campaigns/Report', false)->where('campaignId', $uuid));
    }

    public function test_route_is_named(): void
    {
        $uuid = (string) Str::uuid();

        $this->assertSame(url("/campaigns/{$uuid}/report"), route('campaigns.report', ['campaign' => $uuid]));
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/campaigns/'.Str::uuid().'/report')->assertRedirect('/login');
    }
}
