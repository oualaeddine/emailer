<?php

namespace Tests\Feature\Modules\Recipients\Timeline;

use App\Domain\Enums\RoleName;
use App\Modules\Identity\Models\User;
use App\Modules\Recipients\Models\Recipient;
use App\Modules\Recipients\Models\RecipientList;
use App\Modules\Tracking\Models\Message;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * docs/45-brevo-api-contracts.md §45.5.5 — GET /api/v1/recipients/{uuid}/timeline.
 */
class RecipientTimelineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function message(Recipient $recipient, array $attrs = []): Message
    {
        $message = Message::factory()->create(['recipient_id' => $recipient->id]);
        foreach ($attrs as $key => $value) {
            $message->forceFill([$key => $value]);
        }
        $message->save();

        return $message;
    }

    public function test_timeline_merges_sources_newest_first(): void
    {
        $viewer = User::factory()->withRole(RoleName::Viewer)->create();
        $recipient = Recipient::factory()->create(['created_at' => '2026-10-01 08:00:00']);
        $this->message($recipient, [
            'status' => 'clicked', 'sent_at' => '2026-10-02 09:00:00', 'delivered_at' => '2026-10-02 09:01:00',
            'opened_at' => '2026-10-02 10:00:00', 'clicked_at' => '2026-10-02 10:05:00',
        ]);
        $list = RecipientList::create(['name' => 'Clients', 'type' => 'static']);
        DB::table('recipient_list_recipient')->insert([
            'recipient_list_id' => $list->id, 'recipient_id' => $recipient->id, 'added_at' => '2026-10-03 12:00:00',
        ]);

        $response = $this->actingAs($viewer)->getJson("/api/v1/recipients/{$recipient->uuid}/timeline");

        $response->assertOk();
        $this->assertSame(
            ['list_added', 'clicked', 'opened', 'delivered', 'sent', 'created'],
            array_column($response->json('data'), 'type'),
        );
        $response->assertJsonPath('data.0.list.name', 'Clients')
            ->assertJsonPath('data.1.occurred_at', '2026-10-02T10:05:00Z')
            ->assertJsonPath('meta.next_cursor', null)
            ->assertJsonStructure(['meta' => ['counters' => ['messages_sent', 'delivered', 'opened', 'clicked', 'hard_bounces', 'soft_bounces', 'complaints', 'unsubscriptions']]]);
    }

    public function test_cursor_pagination_walks_every_entry_once(): void
    {
        $viewer = User::factory()->withRole(RoleName::Viewer)->create();
        $recipient = Recipient::factory()->create(['created_at' => '2026-10-01 08:00:00']);
        $this->message($recipient, [
            'status' => 'clicked', 'sent_at' => '2026-10-02 09:00:00', 'delivered_at' => '2026-10-02 09:00:00',
            'opened_at' => '2026-10-02 09:00:00', 'clicked_at' => '2026-10-02 09:00:00',
        ]);

        $seen = [];
        $cursor = null;
        for ($i = 0; $i < 10; $i++) {
            $query = $cursor ? "&cursor={$cursor}" : '';
            $json = $this->actingAs($viewer)->getJson("/api/v1/recipients/{$recipient->uuid}/timeline?per_page=2{$query}")->assertOk()->json();
            $seen = array_merge($seen, array_column($json['data'], 'id'));
            $cursor = $json['meta']['next_cursor'];
            if ($cursor === null) {
                break;
            }
        }

        $this->assertCount(5, $seen);
        $this->assertSame($seen, array_values(array_unique($seen)));
    }

    public function test_types_filter_and_bounce_mapping(): void
    {
        $viewer = User::factory()->withRole(RoleName::Viewer)->create();
        $recipient = Recipient::factory()->create();
        $this->message($recipient, ['status' => 'hard_bounced', 'sent_at' => '2026-10-02 09:00:00', 'bounced_at' => '2026-10-02 09:02:00']);

        $json = $this->actingAs($viewer)
            ->getJson("/api/v1/recipients/{$recipient->uuid}/timeline?types[]=hard_bounce")
            ->assertOk()->json('data');

        $this->assertSame(['hard_bounce'], array_column($json, 'type'));
        $this->actingAs($viewer)->getJson("/api/v1/recipients/{$recipient->uuid}/timeline?types[]=nope")->assertStatus(422);
    }

    public function test_unauthenticated_and_unknown_contact(): void
    {
        $recipient = Recipient::factory()->create();
        $this->getJson("/api/v1/recipients/{$recipient->uuid}/timeline")->assertUnauthorized();

        $viewer = User::factory()->withRole(RoleName::Viewer)->create();
        $this->actingAs($viewer)->getJson('/api/v1/recipients/'.fake()->uuid().'/timeline')->assertNotFound();
    }

    public function test_counters_are_backfilled_columns(): void
    {
        $viewer = User::factory()->withRole(RoleName::Viewer)->create();
        $recipient = Recipient::factory()->create();
        DB::table('recipients')->where('id', $recipient->id)->update(['messages_sent' => 14, 'opened' => 9]);

        $this->actingAs($viewer)->getJson("/api/v1/recipients/{$recipient->uuid}/timeline")
            ->assertJsonPath('meta.counters.messages_sent', 14)
            ->assertJsonPath('meta.counters.opened', 9);
    }

    public function test_contact_page_route_is_uuid_constrained(): void
    {
        $viewer = User::factory()->withRole(RoleName::Viewer)->create();
        $recipient = Recipient::factory()->create();

        $this->actingAs($viewer)->get("/contacts/{$recipient->uuid}")->assertOk();
        $this->actingAs($viewer)->get('/contacts/not-a-uuid')->assertNotFound();
    }
}
