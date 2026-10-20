<?php

namespace Tests\Feature\Modules\Recipients\Segments;

use App\Domain\Enums\RoleName;
use App\Modules\Identity\Models\User;
use App\Modules\Recipients\Models\Recipient;
use App\Modules\Recipients\Models\RecipientList;
use App\Modules\Recipients\Models\Segment;
use App\Modules\Recipients\Services\SegmentEvaluationService;
use App\Modules\Suppression\Models\SuppressionEntry;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Wave 2 review fixes on the segment compiler (B2, B3, S3, S9, N9).
 */
class SegmentReviewFixesTest extends TestCase
{
    use BuildsRuleTrees;
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->admin = User::factory()->withRole(RoleName::Administrator)->create();
    }

    public function test_v1_custom_field_condition_without_registry_row_reads_the_raw_json_key(): void
    {
        Recipient::factory()->create(['company_name' => 'A', 'custom_fields' => ['ville' => 'Oran']]);
        Recipient::factory()->create(['company_name' => 'B', 'custom_fields' => ['ville' => 'Alger']]);
        Recipient::factory()->create(['company_name' => 'C', 'custom_fields' => ['VILLE' => 'Oran']]);
        $v1 = ['operator' => 'AND', 'conditions' => [['field' => 'custom_fields.ville', 'operator' => 'equals', 'value' => 'oran']]];

        $upgraded = $this->compiler()->upgrade($v1);
        $this->assertSame('ville', $upgraded['children'][0]['legacy_key']);
        $this->assertSame('text', $upgraded['children'][0]['field_type']);

        $this->assertSame(['A'], $this->labels($v1));
        $this->contract()->validate($v1); // no "Attribut inconnu"

        $segment = Segment::query()->create(['name' => 'Legacy', 'rules' => $v1]);
        $this->actingAs($this->admin)->postJson("/api/v1/segments/{$segment->uuid}/count")
            ->assertOk()->assertJsonPath('data.count', 1);
    }

    public function test_v1_custom_field_condition_keeps_its_key_case_even_with_a_registry_row(): void
    {
        $this->defineAttribute('VILLE', 'text');
        Recipient::factory()->create(['company_name' => 'A', 'custom_fields' => ['ville' => 'Oran']]);

        $this->assertSame(['A'], $this->labels(['operator' => 'AND', 'conditions' => [
            ['field' => 'custom_fields.ville', 'operator' => 'contains', 'value' => 'ra'],
        ]]));
    }

    public function test_deleted_list_in_a_saved_segment_matches_none_without_throwing(): void
    {
        $list = RecipientList::query()->create(['name' => 'L', 'type' => 'static']);
        $member = Recipient::factory()->create(['company_name' => 'M']);
        $list->recipients()->attach($member->id);
        $segment = Segment::factory()->create(['rules' => $this->tree([$this->cond('list', 'in', [$list->uuid])]), 'created_by' => $this->admin->id]);

        $list->forceDelete();

        $this->assertSame(0, $this->compiler()->count($segment->rules));
        $this->assertSame(0, app(SegmentEvaluationService::class)->evaluate($segment, false)->count());
        $this->actingAs($this->admin)->postJson("/api/v1/segments/{$segment->uuid}/count")
            ->assertOk()->assertJsonPath('data.count', 0);
        $this->actingAs($this->admin)->getJson("/api/v1/segments/{$segment->uuid}/members")
            ->assertOk()->assertJsonCount(0, 'data');
        $this->actingAs($this->admin)->getJson("/api/v1/recipients?pagination=cursor&segment_id={$segment->uuid}")
            ->assertOk()->assertJsonPath('meta.total', 0);

        // References are still checked when a tree is saved.
        $this->actingAs($this->admin)->patchJson("/api/v1/segments/{$segment->uuid}", ['rules' => $segment->rules])
            ->assertStatus(422)->assertJsonValidationErrors('rules.children.0.value.0');
    }

    public function test_unknown_campaign_in_a_saved_segment_matches_none(): void
    {
        Recipient::factory()->create();
        $tree = $this->tree([$this->cond('engagement.opened', 'has', $this->engagement(['campaign_ids' => [(string) Str::uuid()]]))]);

        $this->assertSame(0, $this->compiler()->count($tree));
        $this->expectException(ValidationException::class);
        $this->contract()->validate($tree);
    }

    public function test_column_stored_attribute_reads_the_recipients_column(): void
    {
        $this->defineSegmentAttributes(); // seeds EXT_ID (storage=column, column=ext_id)
        Recipient::factory()->create(['company_name' => 'A'])->forceFill(['ext_id' => 'X-1'])->save();
        Recipient::factory()->create(['company_name' => 'B', 'custom_fields' => ['EXT_ID' => 'X-1']]);

        $this->assertSame(['A'], $this->labelsFor($this->cond('attribute.EXT_ID', 'equals', 'x-1')));
    }

    public function test_blocklisted_condition_also_matches_suppression_entries_without_mirror(): void
    {
        $mirrored = Recipient::factory()->create(['company_name' => 'Mirror']);
        $mirrored->forceFill(['blocklisted_at' => now()])->saveQuietly();
        $lagging = Recipient::factory()->create(['company_name' => 'Lagging', 'email' => 'lag@x.dz']);
        SuppressionEntry::query()->insert(['uuid' => (string) Str::uuid(), 'email' => 'lag@x.dz', 'reason' => 'hard_bounce', 'created_at' => now()]); // no model events
        Recipient::factory()->create(['company_name' => 'Clean']);

        $this->assertNull($lagging->fresh()->getAttribute('blocklisted_at'));
        $this->assertSame(['Lagging', 'Mirror'], $this->labelsFor($this->cond('contact.blocklisted', 'is_true')));
        $this->assertSame(['Clean'], $this->labelsFor($this->cond('contact.blocklisted', 'is_false')));
    }

    public function test_contact_created_after_its_suppression_entry_starts_blocklisted(): void
    {
        SuppressionEntry::factory()->create(['email' => 'late@x.dz', 'reason' => 'hard_bounce']);

        $recipient = Recipient::factory()->create(['email' => 'late@x.dz', 'status' => 'active']);

        $this->assertSame('suppressed', $recipient->fresh()->status);
        $this->assertNotNull($recipient->fresh()->getAttribute('blocklisted_at'));
        $this->assertSame('active', Recipient::factory()->create(['email' => 'ok@x.dz'])->fresh()->status);
    }

    public function test_members_return_contact_rows_with_requested_columns(): void
    {
        $this->defineAttribute('VILLE', 'text');
        Recipient::factory()->create(['email' => 'a@x.dz', 'custom_fields' => ['VILLE' => 'alger']]);
        Recipient::factory()->create(['email' => 'b@x.dz', 'custom_fields' => ['VILLE' => 'oran']]);
        $segment = Segment::factory()->create([
            'rules' => $this->tree([$this->cond('attribute.VILLE', 'equals', 'alger')]), 'created_by' => $this->admin->id,
        ]);

        $this->actingAs($this->admin)->getJson("/api/v1/segments/{$segment->uuid}/members?columns=attribute.EMAIL,attribute.VILLE,contact.blocklisted")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.email', 'a@x.dz')
            ->assertJsonPath('data.0.attributes.VILLE', 'alger')
            ->assertJsonPath('data.0.blocklisted', false)
            ->assertJsonPath('meta.columns', ['attribute.EMAIL', 'attribute.VILLE', 'contact.blocklisted'])
            ->assertJsonPath('meta.total', 1);
    }
}
