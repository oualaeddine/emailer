<?php

namespace Tests\Feature\Modules\Recipients\Segments;

use App\Modules\Identity\Models\User;
use App\Modules\Recipients\Models\RecipientList;
use App\Modules\Recipients\Models\Segment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * docs/45-brevo-api-contracts.md §45.8 Slot G — standalone segment columns and
 * the `dynamic_segment` list → standalone segment conversion.
 */
class SegmentMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_conversion_copies_list_identity_onto_the_segment(): void
    {
        $user = User::factory()->create();
        $dynamic = RecipientList::query()->create(['name' => 'Actifs', 'description' => 'Contacts actifs', 'type' => 'dynamic_segment', 'created_by' => $user->id]);
        $static = RecipientList::query()->create(['name' => 'Statique', 'type' => 'static']);
        $segmentId = DB::table('segments')->insertGetId([
            'uuid' => (string) Str::uuid(), 'name' => 'placeholder', 'recipient_list_id' => $dynamic->id,
            'rules' => json_encode(['operator' => 'AND', 'conditions' => []]), 'rules_version' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $migration = require database_path('migrations/2026_10_21_130002_convert_dynamic_segment_lists_to_standalone_segments.php');
        $migration->up();

        $row = DB::table('segments')->find($segmentId);
        $this->assertSame('Actifs', $row->name);
        $this->assertSame('Contacts actifs', $row->description);
        $this->assertSame($user->id, (int) $row->created_by);
        $this->assertSame($dynamic->id, (int) $row->recipient_list_id); // legacy link kept (read-only list)
        $this->assertSame(1, Segment::query()->count());
        $this->assertNotNull($static->fresh());
    }

    public function test_segments_are_standalone_and_survive_legacy_list_deletion(): void
    {
        $standalone = Segment::factory()->create();
        $this->assertNull($standalone->recipient_list_id);
        $this->assertSame(2, $standalone->rules_version);

        $list = RecipientList::query()->create(['name' => 'Ancienne liste', 'type' => 'dynamic_segment']);
        $legacy = Segment::query()->create(['recipient_list_id' => $list->id, 'rules' => ['operator' => 'AND', 'conditions' => []]]);
        $this->assertSame('Ancienne liste', $legacy->name);
        $this->assertSame(1, $legacy->rules_version);

        $list->forceDelete();

        $this->assertNotNull($legacy->fresh());
        $this->assertNull($legacy->fresh()->recipient_list_id);
    }
}
