<?php

namespace Tests\Feature\Modules\Recipients;

use App\Domain\Enums\RoleName;
use App\Modules\Identity\Models\User;
use App\Modules\Recipients\Models\Recipient;
use App\Modules\Recipients\Models\Tag;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * docs/29-api-specification.md §29.4, docs/13-recipient-management.md.
 */
class RecipientManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_marketing_operator_can_create_a_recipient(): void
    {
        $operator = User::factory()->withRole(RoleName::MarketingOperator)->create();

        $response = $this->actingAs($operator)->postJson('/api/v1/recipients', [
            'email' => 'nouveau@example.com',
            'first_name' => 'Amine',
            'source' => 'manual',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('recipients', ['email' => 'nouveau@example.com', 'first_name' => 'Amine']);
    }

    public function test_viewer_cannot_create_a_recipient(): void
    {
        $viewer = User::factory()->withRole(RoleName::Viewer)->create();

        $this->actingAs($viewer)->postJson('/api/v1/recipients', [
            'email' => 'x@example.com',
            'source' => 'manual',
        ])->assertForbidden();
    }

    public function test_viewer_can_list_recipients(): void
    {
        $viewer = User::factory()->withRole(RoleName::Viewer)->create();
        Recipient::factory()->count(3)->create();

        $response = $this->actingAs($viewer)->getJson('/api/v1/recipients');

        $response->assertOk();
        $response->assertJsonCount(3, 'data');
    }

    public function test_creating_a_recipient_via_the_api_with_an_existing_email_returns_409(): void
    {
        $operator = User::factory()->withRole(RoleName::MarketingOperator)->create();
        $existing = Recipient::factory()->create(['email' => 'existing@example.com']);

        $response = $this->actingAs($operator)->postJson('/api/v1/recipients', [
            'email' => 'existing@example.com',
            'first_name' => 'Karim',
            'source' => 'manual',
        ]);

        // docs/29-api-specification.md §29.4 — the direct manual-create API
        // conflicts on duplicate rather than merging.
        $response->assertStatus(409);
        $response->assertJsonPath('existing_recipient.id', $existing->uuid);
        $this->assertSame(1, Recipient::query()->where('email', 'existing@example.com')->count());
    }

    public function test_the_import_path_merges_into_an_existing_recipient_instead_of_conflicting(): void
    {
        Recipient::factory()->create([
            'email' => 'existing@example.com',
            'first_name' => null,
            'company_name' => 'Entreprise Existante',
        ]);

        $service = app(\App\Modules\Recipients\Services\RecipientService::class);
        $service->createOrMerge(new \App\Modules\Recipients\DataTransferObjects\CreateRecipientData(
            email: 'existing@example.com',
            source: \App\Domain\Enums\RecipientSource::CsvImport,
            firstName: 'Karim',
        ));

        $this->assertSame(1, Recipient::query()->where('email', 'existing@example.com')->count());

        $recipient = Recipient::query()->where('email', 'existing@example.com')->first();
        // "richest non-null wins": first_name was null, so the new value fills it.
        $this->assertSame('Karim', $recipient->first_name);
        // "company_name" already had a value, so it must not be overwritten.
        $this->assertSame('Entreprise Existante', $recipient->company_name);
    }

    public function test_updating_a_recipient_can_sync_tags(): void
    {
        $operator = User::factory()->withRole(RoleName::MarketingOperator)->create();
        $recipient = Recipient::factory()->create();
        $tag = Tag::query()->create(['name' => 'VIP', 'color' => 'brand']);

        $response = $this->actingAs($operator)->patchJson("/api/v1/recipients/{$recipient->uuid}", [
            'tag_ids' => [$tag->id],
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('recipient_tag', ['recipient_id' => $recipient->id, 'tag_id' => $tag->id]);
    }

    public function test_operator_can_add_a_note_to_a_recipient(): void
    {
        $operator = User::factory()->withRole(RoleName::MarketingOperator)->create();
        $recipient = Recipient::factory()->create();

        $response = $this->actingAs($operator)->postJson("/api/v1/recipients/{$recipient->uuid}/notes", [
            'body' => 'Contact prioritaire pour la campagne Q3.',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('recipient_notes', [
            'recipient_id' => $recipient->id,
            'user_id' => $operator->id,
        ]);
    }
}
