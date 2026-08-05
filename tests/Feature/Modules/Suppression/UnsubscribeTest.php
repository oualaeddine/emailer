<?php

namespace Tests\Feature\Modules\Suppression;

use App\Modules\Suppression\Models\SuppressionEntry;
use App\Modules\Suppression\Support\UnsubscribeUrlGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * docs/23-suppression-list.md §23.4, docs/29-api-specification.md §29.8 —
 * GET/POST /unsubscribe/{token}, public and unauthenticated.
 */
class UnsubscribeTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_valid_signed_link_shows_confirmation_details(): void
    {
        $url = app(UnsubscribeUrlGenerator::class)->signedUrlFor('client@example.com');

        $response = $this->getJson($url);

        $response->assertOk();
        $response->assertJsonPath('data.email', 'client@example.com');
        $response->assertJsonPath('data.already_suppressed', false);
    }

    public function test_confirming_a_valid_signed_link_creates_a_manual_unsubscribe_entry(): void
    {
        $url = app(UnsubscribeUrlGenerator::class)->signedUrlFor('client@example.com');

        $response = $this->postJson($url);

        $response->assertOk();
        $response->assertJsonPath('data.email', 'client@example.com');
        $response->assertJsonPath('data.reason', 'manual_unsubscribe');
        $this->assertDatabaseHas('suppression_entries', [
            'email' => 'client@example.com',
            'reason' => 'manual_unsubscribe',
        ]);
    }

    public function test_confirming_with_the_global_option_creates_a_global_unsubscribe_entry(): void
    {
        // docs/23-suppression-list.md §23.4 point 2/3 — the "unsubscribe
        // from everything" checkbox.
        $url = app(UnsubscribeUrlGenerator::class)->signedUrlFor('client@example.com');

        $response = $this->postJson($url, ['global' => true]);

        $response->assertOk();
        $response->assertJsonPath('data.reason', 'global_unsubscribe');
        $this->assertDatabaseHas('suppression_entries', [
            'email' => 'client@example.com',
            'reason' => 'global_unsubscribe',
        ]);
    }

    public function test_a_one_click_list_unsubscribe_post_with_no_body_still_suppresses(): void
    {
        // docs/23-suppression-list.md §23.4 point 4 — the List-Unsubscribe
        // header hits the same endpoint with no interactive body.
        $url = app(UnsubscribeUrlGenerator::class)->signedUrlFor('oneclick@example.com');

        $this->post($url)->assertOk();

        $this->assertDatabaseHas('suppression_entries', [
            'email' => 'oneclick@example.com',
            'reason' => 'manual_unsubscribe',
        ]);
    }

    public function test_confirming_an_already_suppressed_email_stays_a_no_op(): void
    {
        SuppressionEntry::factory()->create(['email' => 'deja@example.com']);
        $url = app(UnsubscribeUrlGenerator::class)->signedUrlFor('deja@example.com');

        $this->postJson($url)->assertOk();

        $this->assertSame(1, SuppressionEntry::query()->where('email', 'deja@example.com')->count());
    }

    public function test_a_tampered_link_is_rejected(): void
    {
        $url = app(UnsubscribeUrlGenerator::class)->signedUrlFor('client@example.com');
        $tampered = str_replace(
            UnsubscribeUrlGenerator::encode('client@example.com'),
            UnsubscribeUrlGenerator::encode('attacker@example.com'),
            $url,
        );

        $this->get($tampered)->assertForbidden();
        $this->postJson($tampered)->assertForbidden();

        $this->assertDatabaseMissing('suppression_entries', ['email' => 'attacker@example.com']);
    }

    public function test_an_expired_link_is_rejected(): void
    {
        $url = app(UnsubscribeUrlGenerator::class)->signedUrlFor('client@example.com', now()->subMinute());

        $this->get($url)->assertForbidden();
        $this->postJson($url)->assertForbidden();

        $this->assertDatabaseMissing('suppression_entries', ['email' => 'client@example.com']);
    }

    public function test_a_validly_signed_link_with_a_malformed_token_returns_not_found(): void
    {
        $url = URL::signedRoute('unsubscribe.show', ['token' => 'not-a-real-token'], now()->addDay());

        $this->getJson($url)->assertNotFound();
    }
}
