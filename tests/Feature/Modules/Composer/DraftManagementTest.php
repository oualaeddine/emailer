<?php

namespace Tests\Feature\Modules\Composer;

use App\Domain\Enums\RoleName;
use App\Modules\Composer\Models\Draft;
use App\Modules\Identity\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * docs/29-api-specification.md §29.3, docs/11-email-composer.md §11.6.
 */
class DraftManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_an_operator_can_create_and_autosave_a_draft(): void
    {
        $operator = User::factory()->withRole(RoleName::MarketingOperator)->create();

        $createResponse = $this->actingAs($operator)->postJson('/api/v1/drafts', [
            'subject' => 'Bonjour',
            'html_body' => '<p>Contenu initial</p>',
        ]);
        $createResponse->assertCreated();
        $draftId = $createResponse->json('data.id');

        $updateResponse = $this->actingAs($operator)->patchJson("/api/v1/drafts/{$draftId}", [
            'html_body' => '<p>Contenu mis à jour</p>',
        ]);

        $updateResponse->assertOk();
        $updateResponse->assertJsonPath('data.html_body', '<p>Contenu mis à jour</p>');

        // Autosave must not create a version (docs/11-email-composer.md §11.6).
        $this->assertSame(0, Draft::query()->find($this->decodeId($draftId))->versions()->count());
    }

    public function test_saving_a_version_and_restoring_it_creates_a_new_version_rather_than_mutating_history(): void
    {
        $operator = User::factory()->withRole(RoleName::MarketingOperator)->create();
        $draft = $this->createDraft($operator, 'Sujet v1', '<p>v1</p>');

        $this->actingAs($operator)->postJson("/api/v1/drafts/{$draft->uuid}/versions")->assertCreated();

        $this->actingAs($operator)->patchJson("/api/v1/drafts/{$draft->uuid}", ['html_body' => '<p>v2</p>']);
        $this->actingAs($operator)->postJson("/api/v1/drafts/{$draft->uuid}/versions")->assertCreated();

        $versionsResponse = $this->actingAs($operator)->getJson("/api/v1/drafts/{$draft->uuid}/versions");
        $versionsResponse->assertJsonCount(2, 'data');
        $firstVersionId = collect($versionsResponse->json('data'))->firstWhere('version_number', 1)['id'];

        $restoreResponse = $this->actingAs($operator)
            ->postJson("/api/v1/drafts/{$draft->uuid}/versions/{$firstVersionId}/restore");

        $restoreResponse->assertOk();
        $restoreResponse->assertJsonPath('data.html_body', '<p>v1</p>');

        // Restoring created a THIRD version rather than deleting/mutating the first two.
        $this->assertSame(3, $draft->fresh()->versions()->count());
    }

    public function test_an_operator_cannot_view_another_operators_draft_without_mailbox_view_all(): void
    {
        $owner = User::factory()->withRole(RoleName::MarketingOperator)->create();
        $otherOperator = User::factory()->withRole(RoleName::MarketingOperator)->create();
        $draft = $this->createDraft($owner, 'Privé', '<p>Privé</p>');

        $this->actingAs($otherOperator)
            ->patchJson("/api/v1/drafts/{$draft->uuid}", ['subject' => 'Hack'])
            ->assertForbidden();
    }

    public function test_a_marketing_manager_with_mailbox_view_all_can_edit_any_draft(): void
    {
        $owner = User::factory()->withRole(RoleName::MarketingOperator)->create();
        $manager = User::factory()->withRole(RoleName::MarketingManager)->create();
        $draft = $this->createDraft($owner, 'Sujet', '<p>Contenu</p>');

        $this->actingAs($manager)
            ->patchJson("/api/v1/drafts/{$draft->uuid}", ['subject' => 'Modifié par le responsable'])
            ->assertOk();
    }

    private function createDraft(User $user, string $subject, string $html): Draft
    {
        return Draft::query()->create([
            'user_id' => $user->id,
            'subject' => $subject,
            'html_body' => $html,
            'status' => 'draft',
        ]);
    }

    private function decodeId(string $uuid): int
    {
        return Draft::query()->where('uuid', $uuid)->value('id');
    }
}
