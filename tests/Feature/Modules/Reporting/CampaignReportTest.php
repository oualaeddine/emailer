<?php

namespace Tests\Feature\Modules\Reporting;

use App\Domain\Enums\RoleName;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Campaigns\Models\CampaignAudience;
use App\Modules\Identity\Models\User;
use App\Modules\Recipients\Models\Segment;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Modules\Reporting\Support\SeedsReportData;
use Tests\TestCase;

/**
 * docs/45 §45.6.5 (WP-72) — campaign report tabs, compare, segment, export.
 */
class CampaignReportTest extends TestCase
{
    use RefreshDatabase, SeedsReportData;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->manager = User::factory()->withRole(RoleName::MarketingManager)->create();
    }

    private function seedFullCampaign(): \App\Modules\Campaigns\Models\Campaign
    {
        $c = $this->sentCampaign();
        $list = $this->list('Clients 2025', 812);
        CampaignAudience::query()->create(['campaign_id' => $c->id, 'target_type' => 'list', 'target_id' => $list->id, 'mode' => 'include']);

        $this->hourly($c, '2026-10-10 09:00:00', ['sent' => 60, 'delivered' => 55, 'opens' => 30, 'unique_opens' => 20, 'unique_clicks' => 8, 'hard_bounces' => 2, 'deferred' => 3]);
        $this->hourly($c, '2026-10-10 10:00:00', ['sent' => 40, 'delivered' => 35, 'opens' => 20, 'unique_opens' => 15, 'unique_clicks' => 6, 'unsubscriptions' => 1]);
        $this->hourly($c, '2026-10-14 08:00:00', ['opens' => 10, 'unique_opens' => 5, 'unique_clicks' => 2]);
        $this->byList($c, 'list', $list->id, ['sent' => 100, 'delivered' => 90, 'unique_opens' => 45, 'unique_clicks' => 18, 'unsubscriptions' => 3, 'complaints' => 1]);
        $this->link($c, 'a1b2c3d4e5f6', 'https://pagesjaunes.dz/promo', 12, 20);
        $this->link($c, 'ffffffffffff', 'https://pagesjaunes.dz/autre', 6, 10);

        $m1 = $this->message($c, 'a@gmail.com', ['status' => 'clicked', 'opened_at' => now(), 'clicked_at' => now(), 'open_count' => 2, 'click_count' => 3]);
        $m2 = $this->message($c, 'b@gmail.com', ['status' => 'opened', 'opened_at' => now(), 'open_count' => 1]);
        $this->message($c, 'c@yahoo.fr', ['status' => 'hard_bounced', 'delivered_at' => null, 'bounced_at' => now(), 'last_provider_response' => '550 5.1.1 User unknown']);
        $this->message($c, 'd@yahoo.fr', ['status' => 'delivered']);
        $this->event($m1, 'opened', ['device_type' => 'mobile', 'email_client' => 'Gmail', 'ip_country' => 'DZ']);
        $this->event($m1, 'clicked', ['device_type' => 'mobile', 'email_client' => 'Gmail', 'ip_country' => 'DZ', 'url' => 'https://pagesjaunes.dz/promo', 'link_id' => 'a1b2c3d4e5f6']);
        $this->event($m2, 'opened', ['device_type' => 'desktop', 'email_client' => 'Outlook', 'ip_country' => 'FR']);

        $this->audit($c, 'campaign.scheduled', $this->manager, '2026-10-09 10:00:00');
        $this->audit($c, 'campaign.sent', null, '2026-10-10 09:30:00');
        $this->audit($c, 'campaign.unrelated_thing', null, '2026-10-10 09:31:00');

        return $c;
    }

    public function test_overview_shape(): void
    {
        $c = $this->seedFullCampaign();

        $r = $this->actingAs($this->manager)->getJson("/api/v1/campaigns/{$c->uuid}/report/overview")->assertOk();
        $d = $r->json('data');

        $this->assertSame($c->uuid, $d['campaign']['id']);
        $this->assertSame('sent', $d['campaign']['brevo_status']);
        $this->assertSame(['promo'], $d['campaign']['tags']);
        $this->assertSame(100, $d['globalStats']['sent']);
        $this->assertSame(90, $d['globalStats']['delivered']);
        $this->assertSame(45, $d['globalStats']['uniqueViews']);
        $this->assertSame(40, $d['globalStats']['trackableViews']);
        $this->assertSame(5, $d['globalStats']['appleMppOpens']);
        $this->assertSame(3, $d['globalStats']['deferred']);
        $this->assertEquals(50.0, $d['globalStats']['opensRate']);
        $this->assertEqualsWithDelta(0.9, $d['rates']['delivery_rate'], 0.0001);
        $this->assertEqualsWithDelta(0.2, $d['rates']['click_rate'], 0.0001);
        $this->assertArrayHasKey('open_rate', $d['averages']);
        $this->assertArrayHasKey('open_rate', $d['vs_account_average']);
        $this->assertSame(2, $d['mirrorClick']);
        $this->assertTrue($d['apple_mpp_note']);
        $this->assertNull($d['ab_test']);

        // 72 h of hourly points then daily points.
        $this->assertSame('hour', $d['timeseries']['granularity']);
        $points = $d['timeseries']['points'];
        $this->assertCount(3, $points);
        $this->assertSame('2026-10-10T09:00:00Z', $points[0]['t']);
        $this->assertSame(60, $points[0]['sent']);
        $this->assertSame(20, $points[0]['uniqueViews']);
        $this->assertSame('day', $points[2]['granularity']);
        $this->assertSame('2026-10-14T00:00:00Z', $points[2]['t']);

        $this->assertSame('Clients 2025', $d['audience']['lists'][0]['name']);
        $this->assertSame(812, $d['audience']['lists'][0]['contact_count']);
        $this->assertSame(100, $d['audience']['recipients']);
        $this->assertSame('Clients 2025', $d['campaignStats'][0]['name']);
        $this->assertSame(45, $d['campaignStats'][0]['uniqueViews']);

        $actions = array_column($d['timeline'], 'action');
        $this->assertSame(['created', 'scheduled', 'sent', 'completed'], $actions === [] ? [] : array_values(array_intersect(['created', 'scheduled', 'sent', 'completed'], $actions)));
        $this->assertNotContains('unrelated_thing', $actions);
        $scheduled = collect($d['timeline'])->firstWhere('action', 'scheduled');
        $this->assertSame($this->manager->uuid, $scheduled['actor']['id']);

        $this->assertSame('a1b2c3d4e5f6', $d['links'][0]['link_id']);
        $this->assertEqualsWithDelta(20 / 30, $d['links'][0]['share'], 0.001);
        $this->assertSame(1, $d['statsByDevice']['mobile']['Gmail']['uniqueViews']);
        $this->assertSame(1, $d['statsByDevice']['desktop']['Outlook']['uniqueViews']);
        $this->assertEquals([], (array) $d['statsByDevice']['tablet']);
    }

    public function test_deliverability_shape(): void
    {
        $c = $this->seedFullCampaign();
        $d = $this->actingAs($this->manager)->getJson("/api/v1/campaigns/{$c->uuid}/report/deliverability")->assertOk()->json('data');

        $this->assertSame(['type' => 'hard', 'reason' => '550 5.1.1 User unknown', 'count' => 1], $d['bounce_reasons'][0]);
        $this->assertSame(4, $d['statsByDomain']['gmail.com']['sent'] + 2);
        $this->assertSame(2, $d['statsByDomain']['yahoo.fr']['sent']);
        $this->assertSame(1, $d['statsByDomain']['yahoo.fr']['hardBounces']);
        $this->assertSame(0, $d['blocked_by_suppression']);
        $this->assertArrayHasKey('by_smtp_account', $d);
        $this->assertArrayHasKey('bounce_rate', $d['rates']);
    }

    public function test_opens_shape(): void
    {
        $c = $this->seedFullCampaign();
        $d = $this->actingAs($this->manager)->getJson("/api/v1/campaigns/{$c->uuid}/report/opens")->assertOk()->json('data');

        $this->assertSame(80, $d['viewed']);
        $this->assertSame(45, $d['uniqueViews']);
        $this->assertSame(40, $d['trackableViews']);
        $this->assertCount(24, $d['by_hour_of_day']);
        // 09:00 UTC and 10:00 UTC are 10:00 and 11:00 in Africa/Algiers (UTC+1); 08:00 UTC on the 14th -> 9.
        $byHour = array_column($d['by_hour_of_day'], 'uniqueViews', 'hour');
        $this->assertSame(20, $byHour[10]);
        $this->assertSame(15, $byHour[11]);
        $this->assertSame(5, $byHour[9]);
        $this->assertSame(1, $d['statsByBrowser']['Gmail']['uniqueViews']);
        $this->assertSame(1, $d['statsByCountry']['DZ']['uniqueClicks']);
        $this->assertSame(1, $d['statsByCountry']['FR']['uniqueViews']);
        $this->assertSame(2, $d['statsByDomain']['gmail.com']['sent']);
    }

    public function test_clicks_shape_and_click_map(): void
    {
        $c = $this->seedFullCampaign();
        $d = $this->actingAs($this->manager)->getJson("/api/v1/campaigns/{$c->uuid}/report/clicks")->assertOk()->json('data');

        $this->assertSame(18, $d['uniqueClicks']);
        $this->assertSame(30, $d['total_clicks']);
        $this->assertSame(['https://pagesjaunes.dz/promo' => 20, 'https://pagesjaunes.dz/autre' => 10], $d['linksStats']);
        $this->assertCount(2, $d['links']);
        $this->assertSame(['link_id', 'url', 'unique_clicks', 'share'], array_keys($d['click_map'][0]));
        $this->assertEqualsWithDelta(1.0, array_sum(array_column($d['links'], 'share')), 0.001);
        $this->assertEqualsWithDelta(0.4, $d['click_to_open_rate'], 0.0001);
    }

    public function test_unsubscriptions_shape(): void
    {
        $c = $this->seedFullCampaign();
        $this->message($c, 'u@gmail.com', ['status' => 'unsubscribed']);
        $d = $this->actingAs($this->manager)->getJson("/api/v1/campaigns/{$c->uuid}/report/unsubscriptions")->assertOk()->json('data');

        $this->assertSame(3, $d['unsubscriptions']);
        $this->assertSame(1, $d['complaints']);
        $this->assertSame('Clients 2025', $d['by_list'][0]['name']);
        $this->assertSame(3, $d['by_list'][0]['unsubscriptions']);
        $this->assertSame([], $d['reasons']);
        $this->assertSame('u@gmail.com', $d['recent'][0]['email']);

        $this->actingAs($this->manager)->getJson("/api/v1/campaigns/{$c->uuid}/report/unsubscribes")->assertOk();
    }

    public function test_contacts_drill_down(): void
    {
        $c = $this->seedFullCampaign();

        $r = $this->actingAs($this->manager)->getJson("/api/v1/campaigns/{$c->uuid}/report/contacts?metric=clicked&link_id=a1b2c3d4e5f6")->assertOk();
        $this->assertCount(1, $r->json('data'));
        $this->assertSame('a@gmail.com', $r->json('data.0.email'));
        $this->assertSame('mobile', $r->json('data.0.device_type'));
        $this->assertSame(3, $r->json('data.0.count'));

        $r = $this->actingAs($this->manager)->getJson("/api/v1/campaigns/{$c->uuid}/report/contacts?metric=delivered&per_page=1")->assertOk();
        $this->assertCount(1, $r->json('data'));
        $this->assertNotNull($r->json('next_cursor'));

        $r = $this->actingAs($this->manager)->getJson("/api/v1/campaigns/{$c->uuid}/report/contacts?metric=hard_bounced")->assertOk();
        $this->assertSame('550 5.1.1 User unknown', $r->json('data.0.reason'));

        $this->actingAs($this->manager)->getJson("/api/v1/campaigns/{$c->uuid}/report/contacts?metric=bogus")->assertStatus(422);
    }

    public function test_never_sent_campaign_is_409(): void
    {
        $draft = \App\Modules\Campaigns\Models\Campaign::factory()->create();

        foreach (['overview', 'deliverability', 'opens', 'clicks', 'unsubscriptions'] as $tab) {
            $this->actingAs($this->manager)->getJson("/api/v1/campaigns/{$draft->uuid}/report/{$tab}")->assertStatus(409);
        }
        $this->actingAs($this->manager)->getJson("/api/v1/campaigns/{$draft->uuid}/report/export?tab=opens")->assertStatus(409);
    }

    public function test_report_permissions(): void
    {
        $c = $this->seedFullCampaign();
        $viewer = User::factory()->withRole(RoleName::Viewer)->create();

        $this->getJson("/api/v1/campaigns/{$c->uuid}/report/overview")->assertUnauthorized();
        $this->actingAs($viewer)->getJson("/api/v1/campaigns/{$c->uuid}/report/overview")->assertOk();
        $this->actingAs($viewer)->getJson("/api/v1/campaigns/{$c->uuid}/report/export?tab=opens")->assertForbidden();
        $this->actingAs($viewer)->postJson("/api/v1/campaigns/{$c->uuid}/report/segment", ['metric' => 'opened'])->assertForbidden();
        $this->actingAs($this->manager)->getJson('/api/v1/campaigns/'.\Illuminate\Support\Str::uuid().'/report/overview')->assertNotFound();
    }

    public function test_compare(): void
    {
        $a = $this->sentCampaign(['name' => 'A']);
        $b = $this->sentCampaign(['name' => 'B', 'unique_opens' => 10]);

        $r = $this->actingAs($this->manager)->getJson("/api/v1/campaigns/compare?ids={$b->uuid},{$a->uuid}")->assertOk();
        $this->assertSame(['B', 'A'], array_column(array_column($r->json('data'), 'campaign'), 'name'));
        $this->assertSame(10, $r->json('data.0.globalStats.uniqueViews'));
        $this->assertArrayHasKey('click_rate', $r->json('data.0.rates'));

        $this->actingAs($this->manager)->getJson("/api/v1/campaigns/compare?ids={$a->uuid}")->assertStatus(422);
        $six = collect(range(1, 6))->map(fn () => $this->sentCampaign()->uuid)->implode(',');
        $this->actingAs($this->manager)->getJson("/api/v1/campaigns/compare?ids={$six}")->assertStatus(422);
        $this->actingAs($this->manager)->getJson("/api/v1/campaigns/compare?ids={$a->uuid},".\Illuminate\Support\Str::uuid())->assertStatus(422);
    }

    public function test_segment_creation_uses_engagement_condition(): void
    {
        $c = $this->seedFullCampaign();

        $r = $this->actingAs($this->manager)->postJson("/api/v1/campaigns/{$c->uuid}/report/segment", ['metric' => 'opened'])->assertCreated();
        $this->assertStringContainsString('Newsletter octobre', $r->json('data.name'));
        $cond = $r->json('data.rules.children.0');
        $this->assertSame('engagement.opened', $cond['field']);
        $this->assertSame('has', $cond['operator']);
        $this->assertSame([$c->uuid], $cond['value']['campaign_ids']);
        $this->assertNotNull(Segment::query()->where('uuid', $r->json('data.id'))->first());

        $r = $this->actingAs($this->manager)->postJson("/api/v1/campaigns/{$c->uuid}/report/segment", [
            'metric' => 'not_clicked', 'name' => 'Sans clic',
        ])->assertCreated();
        $this->assertSame('Sans clic', $r->json('data.name'));
        $this->assertSame('has_not', $r->json('data.rules.children.0.operator'));

        $r = $this->actingAs($this->manager)->postJson("/api/v1/campaigns/{$c->uuid}/report/segment", [
            'metric' => 'clicked', 'link_id' => 'a1b2c3d4e5f6',
        ])->assertCreated();
        $this->assertSame('https://pagesjaunes.dz/promo', $r->json('data.rules.children.0.value.link_url'));

        $this->actingAs($this->manager)->postJson("/api/v1/campaigns/{$c->uuid}/report/segment", ['metric' => 'clicked', 'link_id' => 'nope'])->assertStatus(422);
        $this->actingAs($this->manager)->postJson("/api/v1/campaigns/{$c->uuid}/report/segment", ['metric' => 'sent'])->assertStatus(422);
    }

    public function test_export_csv_per_tab_is_formula_neutralised_and_audited(): void
    {
        $c = $this->seedFullCampaign();
        $this->link($c, 'eeeeeeeeeeee', '=HYPERLINK("http://evil")', 1, 1);

        foreach (['overview', 'deliverability', 'opens', 'clicks', 'unsubscriptions'] as $tab) {
            $r = $this->actingAs($this->manager)->get("/api/v1/campaigns/{$c->uuid}/report/export?tab={$tab}")->assertOk();
            $this->assertStringContainsString('text/csv', $r->headers->get('Content-Type'));
            $this->assertStringStartsWith("\xEF\xBB\xBF", $r->streamedContent());
        }

        $csv = $this->actingAs($this->manager)->get("/api/v1/campaigns/{$c->uuid}/report/export?tab=clicks")->streamedContent();
        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertStringNotContainsString(';=HYPERLINK', $csv);
        $this->assertStringContainsString('a1b2c3d4e5f6;https://pagesjaunes.dz/promo', $csv);

        $this->assertSame(6, AuditLog::query()->where('action', 'report.exported')->where('auditable_id', $c->id)->count());
        $this->actingAs($this->manager)->get("/api/v1/campaigns/{$c->uuid}/report/export?tab=nope")->assertStatus(302);
    }

    public function test_uses_campaign_stats_service_when_bound(): void
    {
        $c = $this->sentCampaign();
        $stub = new class
        {
            public function globalStats($campaign): array
            {
                return \App\Modules\Reporting\Services\ReportQueryService::brevoStats(['sent' => 7, 'delivered' => 7, 'unique_opens' => 3]);
            }

            public function timeseries($campaign, string $granularity): array
            {
                return ['granularity' => $granularity, 'points' => $granularity === 'hour'
                    ? [['t' => '2026-10-10T09:00:00Z', 'sent' => 7, 'delivered' => 7, 'viewed' => 3, 'uniqueViews' => 3, 'uniqueClicks' => 0, 'unsubscriptions' => 0, 'hardBounces' => 0, 'softBounces' => 0, 'complaints' => 0]]
                    : []];
            }
        };
        $this->app->instance(\App\Modules\Reporting\Services\ReportQueryService::STATS_SERVICE, $stub);

        $d = $this->actingAs($this->manager)->getJson("/api/v1/campaigns/{$c->uuid}/report/overview")->assertOk()->json('data');
        $this->assertSame(7, $d['globalStats']['sent']);
        $this->assertSame(3, $d['globalStats']['uniqueViews']);
        $this->assertCount(1, $d['timeseries']['points']);
    }
}
