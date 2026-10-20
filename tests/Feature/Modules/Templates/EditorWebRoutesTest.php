<?php

namespace Tests\Feature\Modules\Templates;

use App\Domain\Enums\RoleName;
use App\Modules\Identity\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * WP-60 — routes/web/editor.php: EmailBuilder host pages.
 */
class EditorWebRoutesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function user(RoleName $role): User
    {
        return User::factory()->withRole($role)->withTwoFactor()->create();
    }

    public function test_template_editor_page_requires_templates_manage(): void
    {
        $uuid = (string) Str::uuid();

        $this->actingAs($this->user(RoleName::MarketingManager))->get("/templates/{$uuid}/edit")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Templates/Edit', false)->where('templateId', $uuid));

        $this->actingAs($this->user(RoleName::MarketingOperator))->get("/templates/{$uuid}/edit")->assertForbidden();
        $this->actingAs($this->user(RoleName::Viewer))->get("/templates/{$uuid}/edit")->assertForbidden();
    }

    public function test_campaign_design_page_requires_campaigns_create(): void
    {
        $uuid = (string) Str::uuid();

        $this->actingAs($this->user(RoleName::Administrator))->get("/campaigns/{$uuid}/design")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Campaigns/Design', false)->where('campaignId', $uuid));

        $this->actingAs($this->user(RoleName::MarketingOperator))->get("/campaigns/{$uuid}/design")->assertOk();
        $this->actingAs($this->user(RoleName::Viewer))->get("/campaigns/{$uuid}/design")->assertForbidden();
    }

    public function test_route_parameters_must_be_uuids(): void
    {
        $manager = $this->user(RoleName::MarketingManager);

        $this->actingAs($manager)->get('/templates/not-a-uuid/edit')->assertNotFound();
        $this->actingAs($manager)->get('/campaigns/123/design')->assertNotFound();
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $uuid = (string) Str::uuid();

        $this->get("/templates/{$uuid}/edit")->assertRedirect();
        $this->get("/campaigns/{$uuid}/design")->assertRedirect();
    }

    public function test_lab_spike_page_is_removed(): void
    {
        $this->actingAs($this->user(RoleName::Administrator))->get('/lab/editor-spike')->assertNotFound();
    }
}
