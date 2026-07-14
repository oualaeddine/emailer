<?php

namespace Tests\Feature\Modules\Templates;

use App\Domain\Enums\RoleName;
use App\Modules\Composer\Models\Draft;
use App\Modules\Identity\Models\User;
use App\Modules\Templates\Models\Template;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * docs/12-template-system.md, docs/26-rbac.md §26.3 — `templates.manage`
 * vs `templates.view`.
 */
class TemplateManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_marketing_manager_can_create_a_template_and_it_seeds_version_one(): void
    {
        $manager = User::factory()->withRole(RoleName::MarketingManager)->create();

        $response = $this->actingAs($manager)->postJson('/api/v1/templates', [
            'name' => 'Newsletter mensuelle',
            'category' => 'Newsletter',
            'html_content' => '<p>Bonjour</p>',
        ]);

        $response->assertCreated();
        $templateUuid = $response->json('data.id');

        $versions = $this->actingAs($manager)->getJson("/api/v1/templates/{$templateUuid}/versions");
        $versions->assertJsonCount(1, 'data');
        $versions->assertJsonPath('data.0.version_number', 1);
    }

    public function test_marketing_operator_cannot_manage_templates_but_can_view_them(): void
    {
        $operator = User::factory()->withRole(RoleName::MarketingOperator)->create();

        $this->actingAs($operator)->postJson('/api/v1/templates', [
            'name' => 'X',
            'html_content' => '<p>x</p>',
        ])->assertForbidden();

        $this->actingAs($operator)->getJson('/api/v1/templates')->assertOk();
    }

    public function test_each_update_creates_a_new_version(): void
    {
        $manager = User::factory()->withRole(RoleName::MarketingManager)->create();
        $template = Template::query()->create([
            'name' => 'Promo',
            'html_content' => '<p>v0</p>',
            'created_by' => $manager->id,
        ]);

        $this->actingAs($manager)->patchJson("/api/v1/templates/{$template->uuid}", [
            'html_content' => '<p>v1</p>',
            'change_note' => 'Première mise à jour',
        ])->assertOk();

        $this->actingAs($manager)->patchJson("/api/v1/templates/{$template->uuid}", [
            'html_content' => '<p>v2</p>',
            'change_note' => 'Deuxième mise à jour',
        ])->assertOk();

        $this->assertSame(2, $template->versions()->count());
        $this->assertSame('<p>v2</p>', $template->fresh()->html_content);
    }

    public function test_a_template_in_use_by_a_draft_cannot_be_deleted(): void
    {
        $manager = User::factory()->withRole(RoleName::MarketingManager)->create();
        $template = Template::query()->create([
            'name' => 'Utilisé',
            'html_content' => '<p>x</p>',
            'created_by' => $manager->id,
        ]);
        Draft::query()->create([
            'user_id' => $manager->id,
            'template_id' => $template->id,
            'subject' => 'Test',
            'html_body' => '<p>x</p>',
            'status' => 'draft',
        ]);

        $this->actingAs($manager)->deleteJson("/api/v1/templates/{$template->uuid}")->assertStatus(409);
    }

    public function test_an_unused_template_can_be_deleted(): void
    {
        $manager = User::factory()->withRole(RoleName::MarketingManager)->create();
        $template = Template::query()->create([
            'name' => 'Inutilisé',
            'html_content' => '<p>x</p>',
            'created_by' => $manager->id,
        ]);

        $this->actingAs($manager)->deleteJson("/api/v1/templates/{$template->uuid}")->assertNoContent();
        // Soft delete (docs/04-database-design.md §4.1 conventions note) —
        // the row still exists with deleted_at set, not physically removed.
        $this->assertSoftDeleted('templates', ['id' => $template->id]);
    }

    public function test_archiving_a_template_hides_it_from_the_default_library_listing(): void
    {
        $manager = User::factory()->withRole(RoleName::MarketingManager)->create();
        $template = Template::query()->create([
            'name' => 'À archiver',
            'html_content' => '<p>x</p>',
            'created_by' => $manager->id,
        ]);

        $this->actingAs($manager)->postJson("/api/v1/templates/{$template->uuid}/archive")->assertOk();

        $listing = $this->actingAs($manager)->getJson('/api/v1/templates');
        $listing->assertJsonMissing(['id' => $template->uuid]);
    }
}
