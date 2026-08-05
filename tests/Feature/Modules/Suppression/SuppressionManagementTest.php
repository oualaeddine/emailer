<?php

namespace Tests\Feature\Modules\Suppression;

use App\Domain\Enums\RoleName;
use App\Domain\Enums\SuppressionReason;
use App\Modules\Identity\Models\User;
use App\Modules\Suppression\Models\SuppressionEntry;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * docs/29-api-specification.md §29.8, docs/23-suppression-list.md.
 */
class SuppressionManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_marketing_manager_can_add_a_suppression_entry(): void
    {
        $manager = User::factory()->withRole(RoleName::MarketingManager)->create();

        $response = $this->actingAs($manager)->postJson('/api/v1/suppression-entries', [
            'email' => 'bloque@example.com',
            'reason' => 'manual_block',
            'notes' => 'Demande via le ticket support #1234',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.email', 'bloque@example.com');
        $response->assertJsonPath('data.reason', 'manual_block');
        $this->assertDatabaseHas('suppression_entries', [
            'email' => 'bloque@example.com',
            'reason' => 'manual_block',
        ]);
    }

    public function test_marketing_operator_cannot_add_a_suppression_entry(): void
    {
        // docs/26-rbac.md matrix — MarketingOperator has suppression.view
        // but not suppression.manage.
        $operator = User::factory()->withRole(RoleName::MarketingOperator)->create();

        $this->actingAs($operator)->postJson('/api/v1/suppression-entries', [
            'email' => 'x@example.com',
            'reason' => 'manual_block',
        ])->assertForbidden();
    }

    public function test_viewer_cannot_add_a_suppression_entry(): void
    {
        $viewer = User::factory()->withRole(RoleName::Viewer)->create();

        $this->actingAs($viewer)->postJson('/api/v1/suppression-entries', [
            'email' => 'x@example.com',
            'reason' => 'manual_block',
        ])->assertForbidden();
    }

    public function test_adding_a_suppression_entry_requires_a_valid_reason(): void
    {
        $manager = User::factory()->withRole(RoleName::MarketingManager)->create();

        $response = $this->actingAs($manager)->postJson('/api/v1/suppression-entries', [
            'email' => 'x@example.com',
            'reason' => 'not_a_real_reason',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('reason');
    }

    public function test_adding_a_suppression_entry_requires_a_valid_email(): void
    {
        $manager = User::factory()->withRole(RoleName::MarketingManager)->create();

        $response = $this->actingAs($manager)->postJson('/api/v1/suppression-entries', [
            'email' => 'not-an-email',
            'reason' => 'manual_block',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('email');
    }

    public function test_re_adding_an_already_suppressed_email_is_a_no_op_and_returns_200(): void
    {
        // docs/23-suppression-list.md §23.2 — "unique on email — a
        // re-triggered suppression for an already-suppressed address is a
        // no-op".
        $manager = User::factory()->withRole(RoleName::MarketingManager)->create();
        SuppressionEntry::factory()->reason(SuppressionReason::HardBounce)->create(['email' => 'deja@example.com']);

        $response = $this->actingAs($manager)->postJson('/api/v1/suppression-entries', [
            'email' => 'deja@example.com',
            'reason' => 'manual_block',
        ]);

        $response->assertOk();
        // The original reason is untouched by the no-op upsert.
        $response->assertJsonPath('data.reason', 'hard_bounce');
        $this->assertSame(1, SuppressionEntry::query()->where('email', 'deja@example.com')->count());
    }

    public function test_viewer_cannot_list_suppression_entries(): void
    {
        // docs/26-rbac.md matrix — Viewer has neither suppression.view nor
        // suppression.manage.
        $viewer = User::factory()->withRole(RoleName::Viewer)->create();

        $this->actingAs($viewer)->getJson('/api/v1/suppression-entries')->assertForbidden();
    }

    public function test_marketing_operator_can_list_suppression_entries(): void
    {
        $operator = User::factory()->withRole(RoleName::MarketingOperator)->create();
        SuppressionEntry::factory()->count(2)->create();
        SuppressionEntry::factory()->reason(SuppressionReason::SpamComplaint)->create();

        $response = $this->actingAs($operator)->getJson('/api/v1/suppression-entries');

        $response->assertOk();
        $response->assertJsonCount(3, 'data');
    }

    public function test_listing_can_be_filtered_by_reason(): void
    {
        $operator = User::factory()->withRole(RoleName::MarketingOperator)->create();
        SuppressionEntry::factory()->reason(SuppressionReason::SpamComplaint)->create(['email' => 'a@example.com']);
        SuppressionEntry::factory()->reason(SuppressionReason::ManualBlock)->create(['email' => 'b@example.com']);

        $response = $this->actingAs($operator)->getJson('/api/v1/suppression-entries?reason=spam_complaint');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.email', 'a@example.com');
    }

    public function test_marketing_manager_can_remove_a_suppression_entry_with_confirmation(): void
    {
        $manager = User::factory()->withRole(RoleName::MarketingManager)->create();
        $entry = SuppressionEntry::factory()->create();

        $response = $this->actingAs($manager)->deleteJson("/api/v1/suppression-entries/{$entry->uuid}", [
            'confirm' => true,
        ]);

        $response->assertNoContent();
        $this->assertDatabaseMissing('suppression_entries', ['id' => $entry->id]);
    }

    public function test_removing_a_suppression_entry_without_confirmation_fails_validation(): void
    {
        $manager = User::factory()->withRole(RoleName::MarketingManager)->create();
        $entry = SuppressionEntry::factory()->create();

        $response = $this->actingAs($manager)->deleteJson("/api/v1/suppression-entries/{$entry->uuid}");

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('confirm');
        $this->assertDatabaseHas('suppression_entries', ['id' => $entry->id]);
    }

    public function test_marketing_operator_cannot_remove_a_suppression_entry(): void
    {
        // MarketingOperator has suppression.view but not suppression.manage.
        $operator = User::factory()->withRole(RoleName::MarketingOperator)->create();
        $entry = SuppressionEntry::factory()->create();

        $this->actingAs($operator)->deleteJson("/api/v1/suppression-entries/{$entry->uuid}", [
            'confirm' => true,
        ])->assertForbidden();

        $this->assertDatabaseHas('suppression_entries', ['id' => $entry->id]);
    }
}
