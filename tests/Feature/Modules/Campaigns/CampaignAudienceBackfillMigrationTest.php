<?php

namespace Tests\Feature\Modules\Campaigns;

use App\Domain\Enums\RecipientListType;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Campaigns\Models\CampaignAudience;
use App\Modules\Recipients\Models\RecipientList;
use App\Modules\Recipients\Models\Segment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * docs/43 §43.8 / docs/45 §45.8 Slot B — backfill of `campaign_audiences`
 * from the legacy `recipient_list_id` / `segment_id` columns.
 */
class CampaignAudienceBackfillMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_backfill_creates_one_include_row_per_legacy_target_and_is_idempotent(): void
    {
        $list = RecipientList::query()->create(['name' => 'L', 'type' => RecipientListType::Static->value]);
        $segmentList = RecipientList::query()->create(['name' => 'S', 'type' => RecipientListType::DynamicSegment->value]);
        $segment = Segment::query()->create(['recipient_list_id' => $segmentList->id, 'rules' => ['operator' => 'AND', 'conditions' => []]]);

        // Legacy rows written straight to the table (pre-v2 data, no audience rows).
        $withList = Campaign::factory()->create(['recipient_list_id' => $list->id]);
        $withSegment = Campaign::factory()->create(['segment_id' => $segment->id]);
        $withBoth = Campaign::factory()->create(['recipient_list_id' => $list->id, 'segment_id' => $segment->id]);
        $without = Campaign::factory()->create();

        $migration = require database_path('migrations/2026_10_20_110006_backfill_campaign_audiences.php');
        $migration->up();
        $migration->up();

        $this->assertDatabaseHas('campaign_audiences', ['campaign_id' => $withList->id, 'target_type' => 'list', 'target_id' => $list->id, 'mode' => 'include']);
        $this->assertDatabaseHas('campaign_audiences', ['campaign_id' => $withSegment->id, 'target_type' => 'segment', 'target_id' => $segment->id, 'mode' => 'include']);
        $this->assertSame(2, CampaignAudience::query()->where('campaign_id', $withBoth->id)->count());
        $this->assertSame(0, CampaignAudience::query()->where('campaign_id', $without->id)->count());
        $this->assertSame(4, CampaignAudience::query()->count());

        // Legacy columns are kept.
        $this->assertSame($list->id, $withList->fresh()->recipient_list_id);
    }
}
