<?php

namespace Tests\Feature\Modules\Templates\Sections;

use App\Domain\Enums\RoleName;
use App\Modules\Identity\Models\User;
use App\Modules\Templates\Sections\Http\Requests\StoreSavedSectionRequest;
use App\Modules\Templates\Sections\Models\SavedSection;
use App\Modules\Templates\Services\MjmlCompiler;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * docs/45-brevo-api-contracts.md §45.7.1 / §45.8 Slot N — WP-61 saved
 * sections (*Enregistrés*) and pre-made layouts (*Sections*).
 */
class SavedSectionsTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private User $operator;

    private User $viewer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->manager = User::factory()->withRole(RoleName::MarketingManager)->create();
        $this->operator = User::factory()->withRole(RoleName::MarketingOperator)->create();
        $this->viewer = User::factory()->withRole(RoleName::Viewer)->create();
    }

    /**
     * @return array<string, mixed>
     */
    private function input(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Pied de page société',
            'mjml' => '<mj-section><mj-column><mj-text>Société</mj-text></mj-column></mj-section>',
            'component' => [
                'type' => 'mj-section',
                'tagName' => 'mj-section',
                'attributes' => ['id' => 'i3k9', 'padding' => '0px'],
                'components' => [['type' => 'mj-column', 'attributes' => ['id' => 'x1']]],
            ],
        ], $overrides);
    }

    public function test_table_has_slot_n_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('saved_sections', ['id', 'uuid', 'name', 'mjml', 'component', 'thumbnail_path', 'created_by', 'created_at', 'updated_at']));
    }

    public function test_operator_saves_a_section_and_everyone_lists_it(): void
    {
        $response = $this->actingAs($this->operator)->postJson('/api/v1/saved-sections', $this->input());

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Pied de page société')
            ->assertJsonPath('data.mjml', '<mj-section><mj-column><mj-text>Société</mj-text></mj-column></mj-section>')
            ->assertJsonPath('data.component.type', 'mj-section')
            ->assertJsonPath('data.component.attributes.padding', '0px')
            ->assertJsonPath('data.thumbnail_url', null)
            ->assertJsonPath('data.created_by.id', $this->operator->uuid)
            ->assertJsonPath('data.created_by.name', $this->operator->name);
        $this->assertArrayNotHasKey('id', $response->json('data.component.attributes'));
        $this->assertArrayNotHasKey('id', $response->json('data.component.components.0.attributes'));
        $this->assertIsString($response->json('data.created_at'));

        $this->actingAs($this->viewer)->getJson('/api/v1/saved-sections')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $response->json('data.id'));
    }

    public function test_list_is_filtered_by_q_and_newest_first(): void
    {
        SavedSection::factory()->create(['name' => 'En-tête marque']);
        SavedSection::factory()->create(['name' => 'Pied de page']);
        SavedSection::factory()->create(['name' => 'Pied de page 2']);

        $this->actingAs($this->manager)->getJson('/api/v1/saved-sections?q=pied')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', 'Pied de page 2');
        $this->actingAs($this->manager)->getJson('/api/v1/saved-sections?q=%25')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_only_whole_sections_can_be_saved(): void
    {
        $url = '/api/v1/saved-sections';

        $this->actingAs($this->manager)->postJson($url, $this->input(['mjml' => '<mj-text>Seul</mj-text>']))
            ->assertStatus(422)
            ->assertJsonPath('errors.mjml.0', StoreSavedSectionRequest::NOT_A_SECTION);
        $this->actingAs($this->manager)->postJson($url, $this->input(['mjml' => '<mj-section></mj-section><mj-text>x</mj-text>']))
            ->assertStatus(422);
        $this->actingAs($this->manager)->postJson($url, $this->input(['mjml' => '<mj-section><mj-include path="/etc/passwd"></mj-include></mj-section>']))
            ->assertStatus(422)
            ->assertJsonPath('errors.mjml.0', MjmlCompiler::INCLUDE_FORBIDDEN);
        $this->actingAs($this->manager)->postJson($url, ['name' => '', 'mjml' => '', 'component' => 'x'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'mjml', 'component']);

        // Wrapper and dynamic-content wrapper (with its markers) are whole sections.
        $this->actingAs($this->manager)->postJson($url, $this->input(['mjml' => '<mj-wrapper padding="0px"><mj-section></mj-section></mj-wrapper>']))
            ->assertCreated();
        $this->actingAs($this->manager)->postJson($url, $this->input([
            'mjml' => '<mj-raw><div data-dynamic-rule="dyn_1"></mj-raw><mj-wrapper><mj-section></mj-section></mj-wrapper><mj-raw></div></mj-raw>',
        ]))->assertCreated();
    }

    public function test_viewer_cannot_save(): void
    {
        $this->actingAs($this->viewer)->postJson('/api/v1/saved-sections', $this->input())->assertForbidden();
    }

    public function test_owner_renames_and_deletes_own_section_but_not_others(): void
    {
        $own = SavedSection::factory()->create(['created_by' => $this->operator->id]);
        $other = SavedSection::factory()->create(['created_by' => $this->manager->id]);

        $this->actingAs($this->operator)->patchJson("/api/v1/saved-sections/{$own->uuid}", ['name' => 'Nouveau nom'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Nouveau nom');
        $this->actingAs($this->operator)->patchJson("/api/v1/saved-sections/{$other->uuid}", ['name' => 'X'])->assertForbidden();
        $this->actingAs($this->operator)->deleteJson("/api/v1/saved-sections/{$other->uuid}")->assertForbidden();
        $this->actingAs($this->operator)->patchJson("/api/v1/saved-sections/{$own->uuid}", ['name' => str_repeat('a', 101)])->assertStatus(422);

        $this->actingAs($this->operator)->deleteJson("/api/v1/saved-sections/{$own->uuid}")->assertNoContent();
        $this->assertDatabaseMissing('saved_sections', ['id' => $own->id]);
    }

    public function test_templates_manage_renames_and_deletes_any_section(): void
    {
        $section = SavedSection::factory()->create(['created_by' => $this->operator->id]);

        $this->actingAs($this->manager)->patchJson("/api/v1/saved-sections/{$section->uuid}", ['name' => 'Renommée'])->assertOk();
        $this->actingAs($this->manager)->deleteJson("/api/v1/saved-sections/{$section->uuid}")->assertNoContent();
        $this->actingAs($this->manager)->deleteJson("/api/v1/saved-sections/{$section->uuid}")->assertNotFound();
    }

    public function test_guest_is_rejected(): void
    {
        $this->getJson('/api/v1/saved-sections')->assertUnauthorized();
        $this->getJson('/api/v1/editor/sections')->assertUnauthorized();
    }

    public function test_premade_sections_are_whole_well_formed_sections(): void
    {
        $response = $this->actingAs($this->operator)->getJson('/api/v1/editor/sections');
        $response->assertOk();

        $rows = $response->json('data');
        $this->assertGreaterThanOrEqual(8, count($rows));
        $this->assertSame(count($rows), count(array_unique(array_column($rows, 'key'))));
        $this->assertEqualsCanonicalizing(['header', 'content', 'footer', 'product'], array_values(array_unique(array_column($rows, 'category'))));

        foreach ($rows as $row) {
            $this->assertSame(['key', 'label', 'category', 'mjml', 'thumbnail_url'], array_keys($row));
            $this->assertTrue(StoreSavedSectionRequest::isWholeSection($row['mjml']), $row['key']);
            $this->assertStringNotContainsString('/>', $row['mjml'], $row['key'].' must not use self-closing tags');
            $this->assertNotFalse(simplexml_load_string('<mjml><mj-body>'.$row['mjml'].'</mj-body></mjml>'), $row['key']);
            $this->assertStringStartsWith('data:image/svg+xml;base64,', $row['thumbnail_url']);
            $this->assertNotFalse(simplexml_load_string((string) base64_decode(substr($row['thumbnail_url'], 26))), $row['key']);
        }
    }
}
