<?php

namespace Tests\Feature\Modules\Verification;

use App\Domain\Enums\RecipientStatus;
use App\Domain\Enums\RoleName;
use App\Modules\Identity\Models\User;
use App\Modules\Recipients\Models\Recipient;
use App\Modules\Verification\Enums\VerificationVerdict;
use App\Modules\Verification\Services\VerificationProviderContract;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakeVerificationProvider;
use Tests\TestCase;

/**
 * docs/22-email-verification.md, docs/29-api-specification.md §29.4.
 */
class VerificationManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->app->bind(VerificationProviderContract::class, FakeVerificationProvider::class);
        FakeVerificationProvider::reset();
    }

    public function test_marketing_operator_can_verify_a_single_recipient_email(): void
    {
        $operator = User::factory()->withRole(RoleName::MarketingOperator)->create();
        $recipient = Recipient::factory()->create(['email' => 'valide@example.com']);
        FakeVerificationProvider::$verdict = VerificationVerdict::Deliverable;

        $response = $this->actingAs($operator)->postJson("/api/v1/recipients/{$recipient->uuid}/verify");

        $response->assertOk();
        $response->assertJsonPath('email', 'valide@example.com');
        $response->assertJsonPath('status', 'valid');
        $this->assertDatabaseHas('verification_results', [
            'email' => 'valide@example.com',
            'verdict' => 'deliverable',
            'provider' => 'fake',
        ]);
        $this->assertSame(RecipientStatus::Active->value, $recipient->fresh()->status);
    }

    public function test_verifying_an_undeliverable_email_marks_the_recipient_invalid(): void
    {
        $operator = User::factory()->withRole(RoleName::MarketingOperator)->create();
        $recipient = Recipient::factory()->create(['email' => 'invalide@example.com']);
        FakeVerificationProvider::$verdict = VerificationVerdict::Undeliverable;

        $response = $this->actingAs($operator)->postJson("/api/v1/recipients/{$recipient->uuid}/verify");

        $response->assertOk();
        $response->assertJsonPath('status', 'invalid');
        $this->assertSame(RecipientStatus::Invalid->value, $recipient->fresh()->status);
    }

    public function test_a_second_verify_within_the_cache_window_reuses_the_cached_result_instead_of_calling_the_provider_again(): void
    {
        $operator = User::factory()->withRole(RoleName::MarketingOperator)->create();
        $recipient = Recipient::factory()->create(['email' => 'cache@example.com']);
        FakeVerificationProvider::$verdict = VerificationVerdict::Deliverable;

        $this->actingAs($operator)->postJson("/api/v1/recipients/{$recipient->uuid}/verify")->assertOk();

        // A second call with a different fake verdict should still report the
        // cached (first) verdict, since docs/22-email-verification.md §22.5
        // says a non-expired cached result is reused instead of re-querying.
        FakeVerificationProvider::$verdict = VerificationVerdict::Undeliverable;
        $response = $this->actingAs($operator)->postJson("/api/v1/recipients/{$recipient->uuid}/verify");

        $response->assertJsonPath('status', 'valid');
        $this->assertSame(1, \App\Modules\Verification\Models\VerificationResult::query()->where('email', 'cache@example.com')->count());
    }

    public function test_viewer_cannot_verify_a_recipient(): void
    {
        $viewer = User::factory()->withRole(RoleName::Viewer)->create();
        $recipient = Recipient::factory()->create();

        $this->actingAs($viewer)->postJson("/api/v1/recipients/{$recipient->uuid}/verify")
            ->assertForbidden();
    }

    public function test_bulk_verify_queues_a_job_that_verifies_every_listed_recipient(): void
    {
        $operator = User::factory()->withRole(RoleName::MarketingOperator)->create();
        $recipients = Recipient::factory()->count(3)->create();
        FakeVerificationProvider::$verdict = VerificationVerdict::Undeliverable;

        $response = $this->actingAs($operator)->postJson('/api/v1/recipients/verify-bulk', [
            'recipient_uuids' => $recipients->pluck('uuid')->all(),
        ]);

        $response->assertStatus(202);
        $response->assertJsonPath('queued', 3);

        // docs/32-testing.md — QUEUE_CONNECTION=sync in tests, so the
        // dispatched BulkVerifyRecipientsJob has already run inline by now.
        foreach ($recipients as $recipient) {
            $this->assertDatabaseHas('verification_results', [
                'email' => $recipient->email,
                'verdict' => 'undeliverable',
            ]);
            $this->assertSame(RecipientStatus::Invalid->value, $recipient->fresh()->status);
        }
    }

    public function test_viewer_cannot_bulk_verify_recipients(): void
    {
        $viewer = User::factory()->withRole(RoleName::Viewer)->create();
        $recipient = Recipient::factory()->create();

        $this->actingAs($viewer)->postJson('/api/v1/recipients/verify-bulk', [
            'recipient_uuids' => [$recipient->uuid],
        ])->assertForbidden();
    }

    public function test_bulk_verify_requires_a_non_empty_recipient_uuids_list(): void
    {
        $operator = User::factory()->withRole(RoleName::MarketingOperator)->create();

        $this->actingAs($operator)->postJson('/api/v1/recipients/verify-bulk', [
            'recipient_uuids' => [],
        ])->assertStatus(422);
    }
}
