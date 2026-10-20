<?php

namespace Tests\Feature\Modules\Campaigns;

use App\Domain\Enums\SuppressionReason;
use App\Modules\Campaigns\Contracts\AudienceResolver;
use App\Modules\Campaigns\Events\AudienceMaterialised;
use App\Modules\Campaigns\Models\CampaignRecipient;
use App\Modules\Suppression\Models\SuppressionEntry;
use App\Modules\Suppression\Services\SuppressionManager;
use App\Modules\Tracking\Models\Message;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\Feature\Modules\Campaigns\Concerns\BuildsCampaignFixtures;
use Tests\TestCase;

/**
 * docs/43 §43.6.1 — union of included lists + segments, minus exclusions,
 * minus suppression, deduplicated; materialised into `campaign_recipients`.
 */
class AudienceResolverTest extends TestCase
{
    use BuildsCampaignFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_union_of_lists_and_segments_is_deduplicated(): void
    {
        $owner = $this->operator();
        $a = $this->recipients(3);
        $b = $this->recipients(2);
        $acme = $this->recipients(2, ['company_name' => 'Acme']);

        $listA = $this->listWith([...$a, $acme[0]]);
        $listB = $this->listWith([...$b, $a[0]]);
        $segment = $this->segmentForCompany('Acme');

        $campaign = $this->campaignWithAudience($owner, [
            'lists' => [$listA->id, $listB->id],
            'segments' => [$segment->id],
        ]);

        $resolver = app(AudienceResolver::class);
        $ids = $resolver->recipientIds($campaign);
        sort($ids);

        $expected = collect([...$a, ...$b, ...$acme])->pluck('id')->sort()->values()->all();
        $this->assertSame($expected, $ids);

        $estimate = $resolver->estimate($campaign);
        // raw: listA 4 + listB 3 + segment 2 = 9; distinct 7.
        $this->assertSame(9, $estimate->included);
        $this->assertSame(2, $estimate->duplicates);
        $this->assertSame(0, $estimate->excluded);
        $this->assertSame(7, $estimate->total);
        $this->assertCount(3, $estimate->byTarget);
    }

    public function test_excluded_lists_and_segments_are_removed(): void
    {
        $owner = $this->operator();
        $keep = $this->recipients(3);
        $dropByList = $this->recipients(2);
        $dropBySegment = $this->recipients(1, ['company_name' => 'Concurrent']);

        $included = $this->listWith([...$keep, ...$dropByList, ...$dropBySegment]);
        $excluded = $this->listWith($dropByList);
        $excludedSegment = $this->segmentForCompany('Concurrent');

        $campaign = $this->campaignWithAudience($owner, [
            'lists' => [$included->id],
            'exclusion_lists' => [$excluded->id],
            'excluded_segments' => [$excludedSegment->id],
        ]);

        $ids = app(AudienceResolver::class)->recipientIds($campaign);
        sort($ids);

        $this->assertSame($keep->pluck('id')->sort()->values()->all(), $ids);

        $estimate = app(AudienceResolver::class)->estimate($campaign);
        $this->assertSame(6, $estimate->included);
        $this->assertSame(3, $estimate->excluded);
        $this->assertSame(3, $estimate->total);
    }

    public function test_suppressed_recipients_are_removed_by_status_and_by_suppression_entry(): void
    {
        $owner = $this->operator();
        $active = $this->recipients(2);
        $byStatus = $this->recipients(1)->first();
        $byEntryOnly = $this->recipients(1)->first();

        app(SuppressionManager::class)->suppress($byStatus->email, SuppressionReason::ManualBlock);
        SuppressionEntry::query()->create(['email' => $byEntryOnly->email, 'reason' => SuppressionReason::ManualBlock->value]);

        $list = $this->listWith([...$active, $byStatus, $byEntryOnly]);
        $campaign = $this->campaignWithAudience($owner, ['lists' => [$list->id]]);

        $estimate = app(AudienceResolver::class)->estimate($campaign);

        $this->assertSame(4, $estimate->included);
        $this->assertSame(2, $estimate->suppressed);
        $this->assertSame(2, $estimate->total);
    }

    /**
     * gap-campaign-ux: a contact that is both excluded and suppressed is counted once (as excluded),
     * so distinct included = excluded + suppressed + total and the figures shown in the picker add up.
     */
    public function test_estimate_figures_add_up_when_exclusion_and_suppression_overlap(): void
    {
        $owner = $this->operator();
        $plain = $this->recipients(5);
        $excludedOnly = $this->recipients(2);
        $excludedAndSuppressed = $this->recipients(1)->first();
        $suppressedOnly = $this->recipients(1)->first();

        app(SuppressionManager::class)->suppress($excludedAndSuppressed->email, SuppressionReason::ManualBlock);
        app(SuppressionManager::class)->suppress($suppressedOnly->email, SuppressionReason::ManualBlock);

        $included = $this->listWith([...$plain, ...$excludedOnly, $excludedAndSuppressed, $suppressedOnly]);
        $excluded = $this->listWith([...$excludedOnly, $excludedAndSuppressed]);

        $campaign = $this->campaignWithAudience($owner, ['lists' => [$included->id], 'exclusion_lists' => [$excluded->id]]);
        $estimate = app(AudienceResolver::class)->estimate($campaign);

        $this->assertSame(9, $estimate->included);
        $this->assertSame(3, $estimate->excluded);
        $this->assertSame(1, $estimate->suppressed);
        $this->assertSame(5, $estimate->total);
        $this->assertSame($estimate->included - $estimate->duplicates, $estimate->excluded + $estimate->suppressed + $estimate->total);
    }

    public function test_resolve_materialises_rows_once_and_fires_event(): void
    {
        Event::fake([AudienceMaterialised::class]);
        $owner = $this->operator();
        $list = $this->listWith($this->recipients(4));
        $campaign = $this->campaignWithAudience($owner, ['lists' => [$list->id]]);

        $resolver = app(AudienceResolver::class);
        $this->assertSame(4, $resolver->resolve($campaign));
        $this->assertSame(4, $resolver->resolve($campaign)); // idempotent

        $this->assertSame(4, CampaignRecipient::query()->where('campaign_id', $campaign->id)->where('status', 'pending')->count());
        $this->assertSame(4, $campaign->fresh()->recipients_count);
        Event::assertDispatched(AudienceMaterialised::class, fn (AudienceMaterialised $e) => $e->campaignId === $campaign->id && $e->count === 4);
    }

    public function test_unengaged_share_counts_mailed_contacts_without_open_in_90_days(): void
    {
        $owner = $this->operator();
        [$engaged, $unengaged, $fresh] = $this->recipients(3)->all();

        Message::factory()->create(['recipient_id' => $engaged->id, 'opened_at' => now()->subDays(10)]);
        Message::factory()->create(['recipient_id' => $unengaged->id, 'opened_at' => now()->subDays(200)]);

        $list = $this->listWith([$engaged, $unengaged, $fresh]);
        $campaign = $this->campaignWithAudience($owner, ['lists' => [$list->id]]);

        $estimate = app(AudienceResolver::class)->estimate($campaign);

        $this->assertSame(1, $estimate->unengaged);
        $this->assertEqualsWithDelta(0.3333, $estimate->unengagedPercent, 0.0001);
        $this->assertTrue($estimate->unengagedWarning);
    }

    public function test_child_campaign_excludes_parent_recipients(): void
    {
        $owner = $this->operator();
        $old = $this->recipients(2);
        $list = $this->listWith($old);
        $parent = $this->campaignWithAudience($owner, ['lists' => [$list->id]]);
        app(AudienceResolver::class)->resolve($parent);

        $new = $this->recipients(1);
        $list->recipients()->syncWithoutDetaching([$new->first()->id => ['added_at' => now()]]);

        $child = $this->campaignWithAudience($owner, ['lists' => [$list->id]], ['parent_campaign_id' => $parent->id]);

        $this->assertSame([$new->first()->id], app(AudienceResolver::class)->recipientIds($child));
    }
}
