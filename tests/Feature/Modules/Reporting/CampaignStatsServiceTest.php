<?php

namespace Tests\Feature\Modules\Reporting;

use App\Modules\Campaigns\Events\CampaignStatusChanged;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Recipients\Models\Recipient;
use App\Modules\Recipients\Models\RecipientList;
use App\Modules\Reporting\Contracts\CampaignStats;
use App\Modules\Reporting\Jobs\RollupCampaignStatsJob;
use App\Modules\Reporting\Services\CampaignStatsService;
use App\Modules\Tracking\Models\Message;
use App\Modules\Tracking\Models\MessageEvent;
use Database\Factories\RecipientListFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * docs/45-brevo-api-contracts.md §45.3 / §45.6.4 / §45.9 (WP-70) —
 * `RollupCampaignStatsJob` correctness against raw events and the
 * Brevo-shaped `CampaignStats` read API.
 */
class CampaignStatsServiceTest extends TestCase
{
    use RefreshDatabase;

    private const BREVO_KEYS = [
        'sent', 'delivered', 'hardBounces', 'softBounces', 'complaints', 'unsubscriptions', 'viewed',
        'uniqueViews', 'trackableViews', 'estimatedViews', 'appleMppOpens', 'uniqueClicks', 'clickers',
        'deferred', 'returnBounce', 'opensRate',
    ];

    private Carbon $h1;

    private Carbon $h2;

    private Campaign $campaign;

    private RecipientList $list;

    /** @var array<string, Message> */
    private array $m = [];

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-20 15:30:00');
        $this->h1 = Carbon::parse('2026-10-20 09:10:00');
        $this->h2 = Carbon::parse('2026-10-20 11:45:00');

        $this->campaign = Campaign::factory()->create([
            'status' => 'completed',
            'sent_at' => $this->h1->copy()->subMinutes(5),
            'recipients_count' => 7,
            'mirror_clicks' => 3,
        ]);
        $this->list = RecipientListFactory::new()->create(['name' => 'Clients 2025']);
        DB::table('campaign_audiences')->insert([
            ['campaign_id' => $this->campaign->id, 'target_type' => 'list', 'target_id' => $this->list->id, 'mode' => 'include', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->m['opener'] = $this->message('amina@gmail.com', ['sent_at' => $this->h1, 'opened_at' => $this->h1->copy()->addMinutes(5), 'clicked_at' => $this->h2, 'open_count' => 2, 'click_count' => 1, 'status' => 'clicked'], inList: true);
        $this->m['proxy'] = $this->message('karim@yahoo.fr', ['sent_at' => $this->h1, 'opened_at' => $this->h1->copy()->addMinutes(2), 'open_count' => 1, 'status' => 'opened'], inList: true);
        $this->m['bounce'] = $this->message('nadia@gmail.com', ['bounced_at' => $this->h1, 'status' => 'hard_bounced']);
        $this->m['unsub'] = $this->message('yacine@gmail.com', ['sent_at' => $this->h1, 'status' => 'unsubscribed']);
        $this->m['test'] = $this->message('test@gmail.com', ['sent_at' => $this->h1, 'opened_at' => $this->h1, 'open_count' => 1, 'status' => 'opened', 'is_test' => true], inList: true);
        $this->m['queued'] = $this->message('later@gmail.com', ['status' => 'queued']);

        $events = [
            ['opener', 'opened', $this->h1->copy()->addMinutes(5), ['device_type' => 'mobile', 'email_client' => 'Apple Mail', 'ip_country' => 'DZ', 'metadata' => ['os' => 'iOS']]],
            ['opener', 'opened', $this->h1->copy()->addMinutes(65), ['device_type' => 'desktop', 'email_client' => 'Outlook', 'ip_country' => 'FR', 'metadata' => ['os' => 'Windows']]],
            ['opener', 'clicked', $this->h2, ['device_type' => 'desktop', 'email_client' => 'Outlook', 'ip_country' => 'FR', 'url' => 'https://pagesjaunes.dz/promo', 'metadata' => ['os' => 'Windows']]],
            ['proxy', 'opened', $this->h1->copy()->addMinutes(2), ['is_proxy_open' => true, 'device_type' => 'unknown', 'email_client' => 'Apple Mail']],
            ['unsub', 'unsubscribed', $this->h2, []],
            ['test', 'opened', $this->h1, ['device_type' => 'desktop']],
        ];
        foreach ($events as [$key, $type, $at, $attributes]) {
            MessageEvent::factory()->forMessage($this->m[$key])->create(['event_type' => $type, 'occurred_at' => $at] + $attributes);
        }

        DB::table('campaign_links')->insert([
            ['uuid' => 'aaaaaaaa-0000-4000-8000-000000000001', 'campaign_id' => $this->campaign->id, 'link_id' => 'aaaa', 'url' => 'https://pagesjaunes.dz/promo', 'url_hash' => sha1('https://pagesjaunes.dz/promo'), 'unique_clicks' => 3, 'total_clicks' => 6, 'created_at' => now(), 'updated_at' => now()],
            ['uuid' => 'aaaaaaaa-0000-4000-8000-000000000002', 'campaign_id' => $this->campaign->id, 'link_id' => 'bbbb', 'url' => 'https://pagesjaunes.dz/autre', 'url_hash' => sha1('https://pagesjaunes.dz/autre'), 'unique_clicks' => 1, 'total_clicks' => 2, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function message(string $email, array $attributes, bool $inList = false): Message
    {
        $recipient = Recipient::factory()->create(['email' => $email]);
        if ($inList) {
            DB::table('recipient_list_recipient')->insert(['recipient_list_id' => $this->list->id, 'recipient_id' => $recipient->id]);
        }

        $isTest = $attributes['is_test'] ?? false;
        unset($attributes['is_test']);

        $message = Message::factory()->create(['campaign_id' => $this->campaign->id, 'recipient_id' => $recipient->id]);
        DB::table('messages')->where('id', $message->id)->update($attributes + ['is_test' => $isTest]);

        return $message->fresh();
    }

    private function stats(): CampaignStats
    {
        return app(CampaignStats::class);
    }

    public function test_the_contract_is_bound_to_the_service(): void
    {
        $this->assertInstanceOf(CampaignStatsService::class, $this->stats());
    }

    public function test_rollup_job_matches_raw_events_and_caches_campaign_counters(): void
    {
        RollupCampaignStatsJob::dispatchSync($this->campaign->id);

        $hours = DB::table('campaign_stats_hourly')->where('campaign_id', $this->campaign->id)->orderBy('hour')->get()
            ->keyBy(fn (object $r): string => 'h'.Carbon::parse($r->hour)->format('H'));

        $this->assertSame(['h09', 'h10', 'h11'], $hours->keys()->all());
        // 09h: sends of opener/proxy/unsub + bounce, opener first open + proxy open.
        $this->assertSame(4, (int) $hours['h09']->sent);
        $this->assertSame(3, (int) $hours['h09']->delivered);
        $this->assertSame(1, (int) $hours['h09']->hard_bounces);
        $this->assertSame(2, (int) $hours['h09']->opens);
        $this->assertSame(2, (int) $hours['h09']->unique_opens);
        $this->assertSame(1, (int) $hours['h09']->proxy_opens);
        // 10h: the opener's second open (repeat, not unique).
        $this->assertSame(1, (int) $hours['h10']->opens);
        $this->assertSame(0, (int) $hours['h10']->unique_opens);
        // 11h: click + unsubscription.
        $this->assertSame(1, (int) $hours['h11']->clicks);
        $this->assertSame(1, (int) $hours['h11']->unique_clicks);
        $this->assertSame(1, (int) $hours['h11']->unsubscriptions);

        // Totals equal the raw (non-test) events.
        $raw = fn (string $type): int => DB::table('message_events as e')->join('messages as m', 'm.id', '=', 'e.message_id')
            ->where('m.campaign_id', $this->campaign->id)->where('m.is_test', false)->where('e.event_type', $type)->count();
        $this->assertSame($raw('opened'), (int) DB::table('campaign_stats_hourly')->where('campaign_id', $this->campaign->id)->sum('opens'));
        $this->assertSame($raw('clicked'), (int) DB::table('campaign_stats_hourly')->where('campaign_id', $this->campaign->id)->sum('clicks'));
        $this->assertSame($raw('unsubscribed'), (int) DB::table('campaign_stats_hourly')->where('campaign_id', $this->campaign->id)->sum('unsubscriptions'));

        $campaign = $this->campaign->fresh();
        $this->assertSame(4, (int) $campaign->sent_count);
        $this->assertSame(3, (int) $campaign->delivered_count);
        $this->assertSame(2, (int) $campaign->unique_opens);
        $this->assertSame(3, (int) $campaign->total_opens);
        $this->assertSame(1, (int) $campaign->unique_clicks);
        $this->assertSame(1, (int) $campaign->total_clicks);
        $this->assertSame(1, (int) $campaign->unsubscribes);
        $this->assertSame(1, (int) $campaign->hard_bounces);
        $this->assertSame(1, (int) $campaign->proxy_opens);
        $this->assertNotNull($campaign->stats_updated_at);
        $this->assertSame(7, (int) $campaign->recipients_count, 'recipients_count is WP-42\'s marker and never written');
        $this->assertSame(3, (int) $campaign->mirror_clicks);

        // Idempotent: a second run replaces, never accumulates.
        RollupCampaignStatsJob::dispatchSync($this->campaign->id);
        $this->assertSame(3, (int) DB::table('campaign_stats_hourly')->where('campaign_id', $this->campaign->id)->count());
        $this->assertSame(4, (int) DB::table('campaign_stats_hourly')->where('campaign_id', $this->campaign->id)->sum('sent'));
    }

    public function test_global_stats_has_the_brevo_shape_and_values(): void
    {
        RollupCampaignStatsJob::dispatchSync($this->campaign->id);

        $stats = $this->stats()->globalStats($this->campaign->fresh());

        $this->assertSame(self::BREVO_KEYS, array_keys($stats));
        $this->assertSame([
            'sent' => 4, 'delivered' => 3, 'hardBounces' => 1, 'softBounces' => 0, 'complaints' => 0,
            'unsubscriptions' => 1, 'viewed' => 3, 'uniqueViews' => 2, 'trackableViews' => 1,
            'estimatedViews' => 2, 'appleMppOpens' => 1, 'uniqueClicks' => 1, 'clickers' => 1,
            'deferred' => 0, 'returnBounce' => 0, 'opensRate' => 66.67,
        ], $stats);

        $rates = $this->stats()->rates($stats);
        $this->assertSame(['delivery_rate', 'open_rate', 'click_rate', 'click_to_open_rate', 'unsubscribe_rate', 'bounce_rate', 'complaint_rate'], array_keys($rates));
        $this->assertSame(0.75, $rates['delivery_rate']);
        $this->assertSame(0.6667, $rates['open_rate']);
        $this->assertSame(0.5, $rates['click_to_open_rate']);
    }

    public function test_global_stats_rolls_up_inline_for_small_campaigns(): void
    {
        $stats = $this->stats()->globalStats($this->campaign);

        $this->assertSame(4, $stats['sent']);
        $this->assertNotNull($this->campaign->fresh()->stats_updated_at);
    }

    public function test_large_campaigns_are_never_aggregated_on_the_request_path(): void
    {
        config(['tracking.stats.live_threshold' => 2]);
        Queue::fake();

        $stats = $this->stats()->globalStats($this->campaign);

        $this->assertSame(0, $stats['sent']);
        $this->assertSame(0, DB::table('campaign_stats_hourly')->count());
        Queue::assertPushed(RollupCampaignStatsJob::class, fn (RollupCampaignStatsJob $job): bool => $job->campaignId === $this->campaign->id);
    }

    public function test_by_list_stats_use_campaign_audiences(): void
    {
        RollupCampaignStatsJob::dispatchSync($this->campaign->id);

        $row = DB::table('campaign_stats_by_list')->where('campaign_id', $this->campaign->id)->sole();
        $this->assertSame('list', $row->target_type);
        $this->assertSame($this->list->id, (int) $row->target_id);

        $entries = $this->stats()->byList($this->campaign->fresh());

        $this->assertCount(1, $entries);
        $this->assertSame($this->list->uuid, $entries[0]['listId']);
        $this->assertNull($entries[0]['segmentId']);
        $this->assertSame('Clients 2025', $entries[0]['name']);
        $this->assertSame(['listId', 'segmentId', 'name', ...self::BREVO_KEYS], array_keys($entries[0]));
        // Only the opener and the proxy opener are list members (test send excluded).
        $this->assertSame(2, $entries[0]['sent']);
        $this->assertSame(2, $entries[0]['delivered']);
        $this->assertSame(3, $entries[0]['viewed']);
        $this->assertSame(2, $entries[0]['uniqueViews']);
        $this->assertSame(1, $entries[0]['appleMppOpens']);
        $this->assertSame(1, $entries[0]['uniqueClicks']);
        $this->assertSame(0, $entries[0]['hardBounces']);
    }

    public function test_breakdowns_links_timeseries_and_statistics_object(): void
    {
        RollupCampaignStatsJob::dispatchSync($this->campaign->id);
        $campaign = $this->campaign->fresh();
        $stats = $this->stats();

        $domains = $stats->statsByDomain($campaign);
        $this->assertSame(['gmail.com', 'yahoo.fr'], array_keys($domains));
        $this->assertSame([
            'sent' => 3, 'delivered' => 2, 'hardBounces' => 1, 'softBounces' => 0, 'complaints' => 0,
            'unsubscriptions' => 1, 'uniqueViews' => 1, 'uniqueClicks' => 1,
        ], $domains['gmail.com']);

        $devices = $stats->statsByDevice($campaign);
        $this->assertSame(['desktop', 'mobile', 'tablet', 'unknown'], array_keys($devices));
        $this->assertSame(['clickers' => 1, 'uniqueClicks' => 1, 'viewed' => 1, 'uniqueViews' => 1], $devices['desktop']['Windows']);
        $this->assertSame(['clickers' => 0, 'uniqueClicks' => 0, 'viewed' => 1, 'uniqueViews' => 1], $devices['mobile']['iOS']);
        $this->assertSame([], $devices['tablet']);

        $browsers = $stats->statsByBrowser($campaign);
        $this->assertSame(['clickers' => 0, 'uniqueClicks' => 0, 'viewed' => 2, 'uniqueViews' => 2], $browsers['Apple Mail']);
        $this->assertSame(['clickers' => 1, 'uniqueClicks' => 1, 'viewed' => 1, 'uniqueViews' => 1], $browsers['Outlook']);

        $countries = $stats->statsByCountry($campaign);
        $this->assertSame(1, $countries['DZ']['uniqueViews']);
        $this->assertSame(1, $countries['FR']['uniqueClicks']);
        $this->assertSame(1, $countries['unknown']['uniqueViews']);

        $this->assertSame(['https://pagesjaunes.dz/promo' => 6, 'https://pagesjaunes.dz/autre' => 2], $stats->linksStats($campaign));
        $links = $stats->links($campaign);
        $this->assertSame(['id', 'link_id', 'url', 'label', 'unique_clicks', 'total_clicks', 'share'], array_keys($links[0]));
        $this->assertSame('aaaa', $links[0]['link_id']);
        $this->assertSame(0.75, $links[0]['share']);
        $this->assertCount(1, $stats->links($campaign, 1));

        $series = $stats->timeseries($campaign, 'hour');
        $this->assertSame('hour', $series['granularity']);
        $this->assertSame('2026-10-20T09:00:00Z', $series['points'][0]['t']);
        $this->assertSame(['t', 'sent', 'delivered', 'viewed', 'uniqueViews', 'uniqueClicks', 'unsubscriptions', 'hardBounces', 'softBounces', 'complaints'], array_keys($series['points'][0]));
        $this->assertSame('hour', $stats->timeseries($campaign)['granularity'], 'auto = hour within 72 h of sent_at');
        $day = $stats->timeseries($campaign, 'day');
        $this->assertCount(1, $day['points']);
        $this->assertSame(3, $day['points'][0]['viewed']);

        $statistics = $stats->statistics($campaign);
        $this->assertSame(['globalStats', 'campaignStats', 'mirrorClick', 'remaining', 'linksStats', 'statsByDomain', 'statsByDevice', 'statsByBrowser', 'statsByCountry'], array_keys($statistics));
        $this->assertSame(3, $statistics['mirrorClick']);
        $this->assertSame(1, $statistics['remaining'], 'the queued message');
    }

    public function test_account_averages_are_computed_from_campaign_counters_and_cached(): void
    {
        RollupCampaignStatsJob::dispatchSync($this->campaign->id);

        $averages = $this->stats()->accountAverages(90);
        $this->assertSame(0.75, $averages['delivery_rate']);
        $this->assertSame(0.6667, $averages['open_rate']);

        DB::table('campaigns')->update(['unique_opens' => 0]);
        $this->assertSame(0.6667, $this->stats()->accountAverages(90)['open_rate'], 'cached for an hour');
    }

    public function test_completion_dispatches_the_rollup_and_hourly_run_fans_out(): void
    {
        CampaignStatusChanged::dispatch($this->campaign->id, 'running', 'completed');
        $this->assertNotNull($this->campaign->fresh()->stats_updated_at);

        $idle = Campaign::factory()->create(['status' => 'draft']);
        $this->assertContains($this->campaign->id, RollupCampaignStatsJob::activeCampaignIds());
        $this->assertNotContains($idle->id, RollupCampaignStatsJob::activeCampaignIds());

        Queue::fake();
        (new RollupCampaignStatsJob)->handle($this->stats());
        Queue::assertPushed(RollupCampaignStatsJob::class, fn (RollupCampaignStatsJob $job): bool => $job->campaignId === $this->campaign->id);
    }
}
