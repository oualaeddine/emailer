<?php

namespace Tests\Feature\Modules\Suppression\Blocklist;

use App\Domain\Enums\RoleName;
use App\Domain\Enums\SuppressionReason;
use App\Modules\Identity\Models\User;
use App\Modules\Recipients\Models\Recipient;
use App\Modules\Suppression\Listeners\RecipientBlocklistChanged;
use App\Modules\Suppression\Models\SuppressionEntry;
use App\Modules\Suppression\Services\SuppressionEntryService;
use App\Modules\Suppression\Services\SuppressionManager;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * docs/45 §45.5.8 / §45.8 slot I — recipients.blocklisted_at mirror (WP-56).
 */
class BlocklistMirrorTest extends TestCase
{
    use RefreshDatabase;

    private function recipient(string $email): Recipient
    {
        return Recipient::query()->create(['email' => $email, 'status' => 'active', 'source' => 'manual']);
    }

    public function test_suppress_sets_blocklisted_at_and_dispatches_event(): void
    {
        $r = $this->recipient('a@example.com');
        $other = $this->recipient('b@example.com');
        Event::fake([RecipientBlocklistChanged::class]);

        app(SuppressionManager::class)->suppress('a@example.com', SuppressionReason::ManualBlock);

        $this->assertNotNull($r->fresh()->blocklisted_at);
        $this->assertNull($other->fresh()->blocklisted_at);
        Event::assertDispatched(
            RecipientBlocklistChanged::class,
            fn ($e) => $e->recipientId === $r->id && $e->blocklisted === true && $e->reason === 'manual_block',
        );
    }

    public function test_removing_the_entry_clears_the_mirror(): void
    {
        $r = $this->recipient('a@example.com');
        $entry = app(SuppressionManager::class)->suppress('a@example.com', SuppressionReason::SpamComplaint);
        $this->assertNotNull($r->fresh()->blocklisted_at);

        app(SuppressionEntryService::class)->remove($entry);

        $this->assertNull($r->fresh()->blocklisted_at);
    }

    public function test_removing_the_entry_reactivates_a_suppressed_recipient(): void
    {
        $r = $this->recipient('a@example.com');
        $entry = app(SuppressionManager::class)->suppress('a@example.com', SuppressionReason::ManualBlock);
        $this->assertSame('suppressed', $r->fresh()->status);

        app(SuppressionEntryService::class)->remove($entry);

        $this->assertSame('active', $r->fresh()->status);
    }

    public function test_api_create_and_delete_keep_the_mirror_in_sync(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $manager = User::factory()->withRole(RoleName::MarketingManager)->create();
        $r = $this->recipient('c@example.com');

        $id = $this->actingAs($manager)->postJson('/api/v1/suppression-entries', [
            'email' => 'c@example.com', 'reason' => 'manual_block',
        ])->assertCreated()->json('data.id');
        $this->assertNotNull($r->fresh()->blocklisted_at);

        $this->actingAs($manager)->deleteJson("/api/v1/suppression-entries/{$id}", ['confirm' => true])->assertNoContent();
        $this->assertNull($r->fresh()->blocklisted_at);
    }

    public function test_backfill_sql_marks_recipients_with_entries(): void
    {
        $this->assertTrue(Schema::hasColumn('recipients', 'blocklisted_at'));
        $a = $this->recipient('a@example.com');
        $b = $this->recipient('b@example.com');
        SuppressionEntry::query()->getConnection()->table('suppression_entries')->insert([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'email' => 'a@example.com', 'reason' => 'hard_bounce', 'created_at' => '2026-01-02 03:04:05',
        ]);
        $this->assertNull($a->fresh()->blocklisted_at);

        $migration = require base_path('database/migrations/2026_10_21_150000_add_blocklisted_at_to_recipients_table.php');
        $migration->up();

        $this->assertSame('2026-01-02 03:04:05', (string) $a->fresh()->blocklisted_at);
        $this->assertNull($b->fresh()->blocklisted_at);
    }

    public function test_web_route_permissions(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $this->assertSame('/contacts/blocklist', parse_url(route('contacts.blocklist'), PHP_URL_PATH));
        $this->assertTrue(Route::has('contacts.blocklist'));

        $this->get('/contacts/blocklist')->assertRedirect();

        $operator = User::factory()->withRole(RoleName::MarketingOperator)->create();
        $this->actingAs($operator)->get('/contacts/blocklist')->assertOk();
    }
}
