<?php

namespace Tests\Feature\Modules\Suppression\Preferences;

use App\Domain\Enums\RoleName;
use App\Modules\Identity\Models\User;
use App\Modules\Recipients\Models\RecipientList;
use App\Modules\Suppression\Preferences\Models\UnsubscribePage;
use App\Modules\Suppression\Preferences\Models\UpdateProfileForm;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PreferencePagesApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        UnsubscribePage::query()->delete();
        UpdateProfileForm::query()->delete();
    }

    private function user(RoleName $role): User
    {
        return User::factory()->withRole($role)->create();
    }

    public function test_unsubscribe_page_crud_flow(): void
    {
        $admin = $this->user(RoleName::Administrator);

        $id = $this->actingAs($admin)->postJson('/api/v1/unsubscribe-pages', [
            'name' => 'Page B2B',
            'content' => ['title' => 'Au revoir'],
            'design' => ['primary_color' => '#112233'],
        ])->assertCreated()
            ->assertJsonPath('data.name', 'Page B2B')
            ->assertJsonPath('data.is_default', true)
            ->assertJsonPath('data.content.title', 'Au revoir')
            ->assertJsonPath('data.content.button_label', 'Me désinscrire')
            ->assertJsonPath('data.design.primary_color', '#112233')
            ->assertJsonPath('data.design.text_color', '#242424')
            ->json('data.id');

        $this->actingAs($admin)->patchJson("/api/v1/unsubscribe-pages/$id", ['content' => ['body' => 'Texte']])
            ->assertOk()
            ->assertJsonPath('data.content.body', 'Texte')
            ->assertJsonPath('data.content.title', 'Au revoir');
        $this->actingAs($admin)->getJson("/api/v1/unsubscribe-pages/$id")->assertOk();
        $this->actingAs($admin)->getJson('/api/v1/unsubscribe-pages')->assertOk()->assertJsonCount(1, 'data');

        $other = $this->actingAs($admin)->postJson('/api/v1/unsubscribe-pages', [
            'name' => 'Autre', 'content' => [],
        ])->assertCreated()->assertJsonPath('data.is_default', false)->json('data.id');

        // The default page cannot be deleted; promoting another one frees it.
        $this->actingAs($admin)->deleteJson("/api/v1/unsubscribe-pages/$id")->assertStatus(409);
        $this->actingAs($admin)->postJson("/api/v1/unsubscribe-pages/$other/default")
            ->assertOk()->assertJsonPath('data.is_default', true);
        $this->assertSame(1, UnsubscribePage::where('is_default', true)->count());
        $this->actingAs($admin)->deleteJson("/api/v1/unsubscribe-pages/$id")->assertNoContent();
        $this->assertDatabaseCount('unsubscribe_pages', 1);
    }

    public function test_validation_rejects_bad_colours_and_missing_name(): void
    {
        $admin = $this->user(RoleName::Administrator);

        $this->actingAs($admin)->postJson('/api/v1/unsubscribe-pages', ['content' => []])
            ->assertStatus(422)->assertJsonValidationErrors('name');
        $this->actingAs($admin)->postJson('/api/v1/unsubscribe-pages', [
            'name' => 'X', 'content' => [], 'design' => ['primary_color' => 'red'],
        ])->assertStatus(422)->assertJsonValidationErrors('design.primary_color');
    }

    public function test_page_in_use_by_scheduled_campaign_cannot_be_deleted(): void
    {
        $admin = $this->user(RoleName::Administrator);
        UnsubscribePage::factory()->default()->create();
        $page = UnsubscribePage::factory()->create();
        $campaign = \App\Modules\Campaigns\Models\Campaign::factory()->create(['status' => 'scheduled']);
        DB::table('campaigns')->where('id', $campaign->id)->update(['unsubscribe_page_id' => $page->id]);

        $this->actingAs($admin)->deleteJson("/api/v1/unsubscribe-pages/{$page->uuid}")->assertStatus(409);

        DB::table('campaigns')->where('id', $campaign->id)->update(['status' => 'draft']);
        $this->actingAs($admin)->deleteJson("/api/v1/unsubscribe-pages/{$page->uuid}")->assertNoContent();
        $this->assertNull(DB::table('campaigns')->where('id', $campaign->id)->value('unsubscribe_page_id'));
    }

    public function test_update_profile_form_crud_and_preview(): void
    {
        $admin = $this->user(RoleName::Administrator);
        $list = \Database\Factories\RecipientListFactory::new()->create(['name' => 'Newsletter']);

        $id = $this->actingAs($admin)->postJson('/api/v1/update-profile-forms', [
            'name' => 'Profil',
            'fields' => [['attribute' => 'PRENOM', 'label' => 'Prénom', 'required' => true]],
            'list_ids' => [$list->uuid],
            'content' => ['title' => 'Mes préférences'],
        ])->assertCreated()
            ->assertJsonPath('data.list_ids.0', $list->uuid)
            ->assertJsonPath('data.is_default', true)
            ->json('data.id');

        $this->actingAs($admin)->postJson('/api/v1/update-profile-forms', [
            'name' => 'Bad', 'fields' => [], 'list_ids' => ['00000000-0000-0000-0000-000000000000'], 'content' => [],
        ])->assertStatus(422)->assertJsonValidationErrors('list_ids.0');

        $this->actingAs($admin)->patchJson("/api/v1/update-profile-forms/$id", ['name' => 'Profil 2'])
            ->assertOk()->assertJsonPath('data.name', 'Profil 2')->assertJsonPath('data.fields.0.attribute', 'PRENOM');

        $this->actingAs($admin)->get("/api/v1/update-profile-forms/$id/preview")
            ->assertOk()->assertSee('Mes préférences')->assertSee('Newsletter', false);
        $this->assertStringContainsString('text/html', $this->actingAs($admin)->get("/api/v1/update-profile-forms/$id/preview")->headers->get('Content-Type'));

        $this->actingAs($admin)->deleteJson("/api/v1/update-profile-forms/$id")->assertStatus(409);
        $second = UpdateProfileForm::factory()->create();
        $this->actingAs($admin)->postJson("/api/v1/update-profile-forms/{$second->uuid}/default")->assertOk();
        $this->actingAs($admin)->deleteJson("/api/v1/update-profile-forms/$id")->assertNoContent();
    }

    public function test_unsubscribe_page_preview_is_html(): void
    {
        $admin = $this->user(RoleName::Administrator);
        $page = UnsubscribePage::factory()->default()->create();

        $response = $this->actingAs($admin)->get("/api/v1/unsubscribe-pages/{$page->uuid}/preview")->assertOk();
        $this->assertStringContainsString('text/html', $response->headers->get('Content-Type'));
        $response->assertSee('Se désinscrire');
    }

    public function test_permissions(): void
    {
        $viewer = $this->user(RoleName::Viewer);
        $operator = $this->user(RoleName::MarketingOperator);
        $manager = $this->user(RoleName::MarketingManager);
        $page = UnsubscribePage::factory()->default()->create();

        // Viewer: nothing. Operator (campaigns.create): list only.
        $this->actingAs($viewer)->getJson('/api/v1/unsubscribe-pages')->assertForbidden();
        $this->actingAs($viewer)->getJson('/api/v1/update-profile-forms')->assertForbidden();
        $this->actingAs($operator)->getJson('/api/v1/unsubscribe-pages')->assertOk();
        $this->actingAs($operator)->getJson('/api/v1/update-profile-forms')->assertOk();
        $this->actingAs($operator)->postJson('/api/v1/unsubscribe-pages', ['name' => 'X', 'content' => []])->assertForbidden();
        $this->actingAs($operator)->getJson("/api/v1/unsubscribe-pages/{$page->uuid}")->assertForbidden();
        $this->actingAs($operator)->deleteJson("/api/v1/unsubscribe-pages/{$page->uuid}")->assertForbidden();

        // Marketing manager has no settings.manage.
        $this->actingAs($manager)->postJson('/api/v1/update-profile-forms', [
            'name' => 'X', 'fields' => [], 'list_ids' => [], 'content' => [],
        ])->assertForbidden();

    }

    public function test_guest_is_unauthorised(): void
    {
        $this->getJson('/api/v1/unsubscribe-pages')->assertUnauthorized();
    }
}
