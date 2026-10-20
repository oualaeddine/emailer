<?php

namespace Tests\Feature\Modules\Templates;

use App\Domain\Enums\RoleName;
use App\Modules\Identity\Models\User;
use App\Modules\Templates\Models\Template;
use App\Modules\Templates\Models\TemplateFolder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * docs/45-brevo-api-contracts.md §45.6.2 / §45.8 slot K — WP-62 folders.
 */
class TemplateFoldersTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->manager = User::factory()->withRole(RoleName::MarketingManager)->create();
    }

    public function test_folder_crud(): void
    {
        $created = $this->actingAs($this->manager)->postJson('/api/v1/template-folders', ['name' => 'Newsletters']);
        $created->assertCreated()->assertJsonPath('data.name', 'Newsletters')->assertJsonPath('data.kind', 'template')->assertJsonPath('data.items_count', 0);
        $id = $created->json('data.id');

        $this->actingAs($this->manager)->patchJson("/api/v1/template-folders/{$id}", ['name' => 'Promos'])
            ->assertOk()->assertJsonPath('data.name', 'Promos');

        $this->actingAs($this->manager)->getJson('/api/v1/template-folders')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Promos');

        $this->actingAs($this->manager)->deleteJson("/api/v1/template-folders/{$id}")->assertNoContent();
        $this->assertSame(0, TemplateFolder::query()->count());
    }

    public function test_folder_names_are_unique_and_required(): void
    {
        TemplateFolder::query()->create(['name' => 'Promos']);

        $this->actingAs($this->manager)->postJson('/api/v1/template-folders', ['name' => 'Promos'])->assertUnprocessable()->assertJsonValidationErrors('name');
        $this->actingAs($this->manager)->postJson('/api/v1/template-folders', [])->assertUnprocessable();
    }

    public function test_operator_can_list_but_not_manage_folders(): void
    {
        $operator = User::factory()->withRole(RoleName::MarketingOperator)->create();
        $folder = TemplateFolder::query()->create(['name' => 'Promos']);

        $this->actingAs($operator)->getJson('/api/v1/template-folders')->assertOk();
        $this->actingAs($operator)->postJson('/api/v1/template-folders', ['name' => 'X'])->assertForbidden();
        $this->actingAs($operator)->patchJson("/api/v1/template-folders/{$folder->uuid}", ['name' => 'Y'])->assertForbidden();
        $this->actingAs($operator)->deleteJson("/api/v1/template-folders/{$folder->uuid}")->assertForbidden();
    }

    public function test_move_template_into_folder_and_filter_by_folder(): void
    {
        $folder = TemplateFolder::query()->create(['name' => 'Promos']);
        $inside = Template::factory()->create(['name' => 'Dedans']);
        $outside = Template::factory()->create(['name' => 'Dehors']);

        $this->actingAs($this->manager)->patchJson("/api/v1/templates/{$inside->uuid}", ['folder_id' => $folder->uuid])
            ->assertOk()->assertJsonPath('data.folder_id', $folder->uuid);

        $this->actingAs($this->manager)->getJson("/api/v1/templates?folder_id={$folder->uuid}")
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $inside->uuid);
        $this->actingAs($this->manager)->getJson('/api/v1/templates?folder_id=none&scope=marketing')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $outside->uuid);

        $this->actingAs($this->manager)->getJson('/api/v1/template-folders')->assertJsonPath('data.0.items_count', 1);

        // Back to "no folder".
        $this->actingAs($this->manager)->patchJson("/api/v1/templates/{$inside->uuid}", ['folder_id' => null])
            ->assertOk()->assertJsonPath('data.folder_id', null);
    }

    public function test_moving_to_an_unknown_folder_is_rejected(): void
    {
        $template = Template::factory()->create();

        $this->actingAs($this->manager)->patchJson("/api/v1/templates/{$template->uuid}", ['folder_id' => '00000000-0000-0000-0000-000000000000'])
            ->assertUnprocessable()->assertJsonValidationErrors('folder_id');
    }

    public function test_deleting_a_folder_moves_its_templates_to_no_folder(): void
    {
        $folder = TemplateFolder::query()->create(['name' => 'Promos']);
        $template = Template::factory()->create(['folder_id' => $folder->id]);

        $this->actingAs($this->manager)->deleteJson("/api/v1/template-folders/{$folder->uuid}")->assertNoContent();

        $this->assertNull($template->fresh()->folder_id);
        $this->assertNotNull(Template::query()->find($template->id));
    }

    public function test_create_and_duplicate_accept_a_folder(): void
    {
        $folder = TemplateFolder::query()->create(['name' => 'Promos']);

        $created = $this->actingAs($this->manager)->postJson('/api/v1/templates', [
            'name' => 'Rangé', 'html_content' => '<p>x</p>', 'folder_id' => $folder->uuid,
        ]);
        $created->assertCreated()->assertJsonPath('data.folder_id', $folder->uuid);

        $copy = $this->actingAs($this->manager)->postJson('/api/v1/templates/'.$created->json('data.id').'/duplicate', ['folder_id' => $folder->uuid]);
        $copy->assertCreated()->assertJsonPath('data.folder_id', $folder->uuid);
    }

    public function test_versions_drawer_data_and_restore(): void
    {
        $created = $this->actingAs($this->manager)->postJson('/api/v1/templates', ['name' => 'V', 'html_content' => '<p>un</p>']);
        $id = $created->json('data.id');
        $this->actingAs($this->manager)->patchJson("/api/v1/templates/{$id}", ['html_content' => '<p>deux</p>'])->assertOk();

        $versions = $this->actingAs($this->manager)->getJson("/api/v1/templates/{$id}/versions");
        $versions->assertOk()->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.version_number', 2)->assertJsonPath('data.0.is_current', true)
            ->assertJsonPath('data.1.is_current', false)->assertJsonPath('data.1.editor_type', 'html')
            ->assertJsonPath('data.1.author.name', $this->manager->name);

        $this->actingAs($this->manager)->postJson("/api/v1/templates/{$id}/versions/1/restore")
            ->assertOk()->assertJsonPath('data.html_content', '<p>un</p>')->assertJsonPath('data.versions_count', 3);

        $this->actingAs($this->manager)->postJson("/api/v1/templates/{$id}/versions/99/restore")->assertNotFound();
    }

    public function test_unarchive(): void
    {
        $template = Template::factory()->create(['is_archived' => true]);

        $this->actingAs($this->manager)->postJson("/api/v1/templates/{$template->uuid}/unarchive")
            ->assertOk()->assertJsonPath('data.is_archived', false);
    }
}
