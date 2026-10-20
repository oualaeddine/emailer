<?php

namespace Tests\Feature\Modules\Tracking;

use App\Domain\Enums\MessageStatus;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Recipients\Models\Recipient;
use App\Modules\Tracking\Contracts\GeoIpResolver;
use App\Modules\Tracking\Events\MessageEventRecorded;
use App\Modules\Tracking\Models\CampaignLink;
use App\Modules\Tracking\Models\Message;
use App\Modules\Tracking\Models\MessageEvent;
use App\Modules\Tracking\Services\TrackingRenderer;
use App\Modules\Tracking\Support\MaxMindGeoIpResolver;
use App\Modules\Tracking\Support\TrackingUrlGenerator;
use App\Modules\Tracking\Support\UserAgentParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\Feature\Modules\Tracking\Fakes\FakeGeoIpResolver;
use Tests\TestCase;

/**
 * docs/43 §43.6.3 / §43.6.5, doc 45 §45.6.4 / §45.8 Slot L / §45.9 (WP-70) —
 * `message_events` enrichment, Apple MPP proxy opens, per-link counters and
 * recipient engagement updates.
 */
class TrackingEnrichmentTest extends TestCase
{
    use RefreshDatabase;

    private const IPHONE_MAIL = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/15E148';

    private const OUTLOOK = 'Microsoft Office/16.0 (Windows NT 10.0; Microsoft Outlook 16.0.5266; Pro)';

    private const CHROME = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';

    private FakeGeoIpResolver $geo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->geo = new FakeGeoIpResolver([
            '41.111.10.20' => ['country' => 'DZ', 'city' => 'Oran'],
            '17.58.1.2' => ['country' => 'US', 'city' => 'Cupertino'],
        ]);
        $this->app->instance(GeoIpResolver::class, $this->geo);
    }

    private function campaignMessage(array $attributes = []): Message
    {
        $campaign = Campaign::factory()->create(['status' => 'running']);

        return Message::factory()->create(['campaign_id' => $campaign->id, 'status' => MessageStatus::Accepted->value] + $attributes);
    }

    private function open(Message $message, string $userAgent, string $ip = '41.111.10.20'): void
    {
        $url = app(TrackingUrlGenerator::class)->signedOpenUrl($message);

        $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->withHeaders(['User-Agent' => $userAgent])
            ->get($url)
            ->assertOk();
    }

    private function click(Message $message, string $target, string $userAgent = self::CHROME, ?string $linkId = null): void
    {
        $url = app(TrackingUrlGenerator::class)->signedClickUrl($message, $target, null, $linkId);

        $this->withServerVariables(['REMOTE_ADDR' => '41.111.10.20'])
            ->withHeaders(['User-Agent' => $userAgent])
            ->get($url)
            ->assertRedirect($target);
    }

    public function test_open_writes_an_enriched_message_event_and_fires_the_domain_event(): void
    {
        Event::fake([MessageEventRecorded::class]);
        $message = $this->campaignMessage();

        $this->open($message, self::IPHONE_MAIL);

        $event = MessageEvent::query()->sole();
        $this->assertSame('opened', $event->event_type);
        $this->assertSame($message->id, $event->message_id);
        $this->assertSame($message->campaign_id, $event->campaign_id);
        $this->assertSame($message->recipient_id, $event->recipient_id);
        $this->assertSame('mobile', $event->device_type);
        $this->assertSame('Apple Mail', $event->email_client);
        $this->assertSame('DZ', $event->ip_country);
        $this->assertSame('Oran', $event->ip_city);
        $this->assertSame('41.111.10.20', $event->ip);
        $this->assertSame(self::IPHONE_MAIL, $event->user_agent);
        $this->assertFalse($event->is_proxy_open);
        $this->assertSame('mobile', $event->metadata['device_type']);
        $this->assertSame('Apple Mail', $event->metadata['email_client']);
        $this->assertSame('DZ', $event->metadata['ip_country']);
        $this->assertSame('iOS', $event->metadata['os']);

        $this->assertSame(1, $message->fresh()->open_count);

        Event::assertDispatched(MessageEventRecorded::class, fn (MessageEventRecorded $e): bool => $e->messageEventId === $event->id
            && $e->messageId === $message->id
            && $e->type === 'opened'
            && $e->campaignId === $message->campaign_id
            && $e->recipientId === $message->recipient_id
            && $e->isProxyOpen === false);
    }

    public function test_apple_mpp_opens_are_counted_and_flagged_as_proxy_opens(): void
    {
        $byAgent = $this->campaignMessage();
        $byIp = $this->campaignMessage();

        $this->open($byAgent, 'Mozilla/5.0');
        $this->open($byIp, self::CHROME, '17.58.1.2');

        $agentEvent = MessageEvent::query()->where('message_id', $byAgent->id)->sole();
        $this->assertTrue($agentEvent->is_proxy_open);
        $this->assertSame('unknown', $agentEvent->device_type);
        $this->assertSame('Apple Mail', $agentEvent->email_client);
        $this->assertSame(1, $byAgent->fresh()->open_count);
        $this->assertNotNull($byAgent->fresh()->opened_at);

        $ipEvent = MessageEvent::query()->where('message_id', $byIp->id)->sole();
        $this->assertTrue($ipEvent->is_proxy_open);
        $this->assertSame('US', $ipEvent->ip_country);
    }

    public function test_bot_opens_are_still_filtered_and_write_no_event(): void
    {
        $message = $this->campaignMessage();

        $this->open($message, 'GoogleImageProxy');
        $this->open($message, '');

        $this->assertSame(0, MessageEvent::query()->count());
        $this->assertSame(0, $message->fresh()->open_count);
    }

    public function test_click_records_url_and_link_id_and_upserts_campaign_link_counters(): void
    {
        $message = $this->campaignMessage();
        $other = Message::factory()->create(['campaign_id' => $message->campaign_id, 'status' => MessageStatus::Accepted->value]);
        $target = 'https://pagesjaunes.dz/promo?x=1';
        $linkId = substr(sha1($target), 0, 16);

        $this->click($message, $target, self::OUTLOOK);
        $this->click($message, $target, self::OUTLOOK);
        $this->click($other, $target);
        $this->click($other, 'https://pagesjaunes.dz/autre');

        $event = MessageEvent::query()->where('message_id', $message->id)->orderBy('id')->first();
        $this->assertSame('clicked', $event->event_type);
        $this->assertSame($target, $event->url);
        $this->assertSame($linkId, $event->link_id);
        $this->assertSame('desktop', $event->device_type);
        $this->assertSame('Outlook', $event->email_client);
        $this->assertSame($target, $event->metadata['url']);

        $link = CampaignLink::query()->where('campaign_id', $message->campaign_id)->where('link_id', $linkId)->sole();
        $this->assertSame($target, $link->url);
        $this->assertSame(sha1($target), $link->url_hash);
        $this->assertSame(3, $link->total_clicks);
        $this->assertSame(2, $link->unique_clicks, 'unique clicks count distinct recipients');
        $this->assertNotNull($link->last_clicked_at);
        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $link->uuid);

        $this->assertSame(2, CampaignLink::query()->where('campaign_id', $message->campaign_id)->count());
        $this->assertSame(2, $message->fresh()->click_count);
    }

    public function test_a_data_link_id_travels_from_the_renderer_to_the_campaign_link(): void
    {
        $message = $this->campaignMessage();
        $html = app(TrackingRenderer::class)->render($message, '<a data-link-id="a1b2c3d4e5f6" href="https://example.com/offre">Offre</a>');

        preg_match('/href="([^"]+)"/', $html, $match);
        $this->withHeaders(['User-Agent' => self::CHROME])->get(html_entity_decode($match[1]))->assertRedirect('https://example.com/offre');

        $this->assertSame('a1b2c3d4e5f6', CampaignLink::query()->sole()->link_id);
        $this->assertSame('a1b2c3d4e5f6', MessageEvent::query()->sole()->link_id);
    }

    public function test_opens_and_clicks_update_recipient_engagement(): void
    {
        $message = $this->campaignMessage();
        DB::table('recipients')->where('id', $message->recipient_id)->update(['messages_sent' => 2, 'delivered' => 2]);

        $this->open($message, self::IPHONE_MAIL);
        $this->open($message, self::IPHONE_MAIL);

        $recipient = DB::table('recipients')->where('id', $message->recipient_id)->first();
        $this->assertNotNull($recipient->last_opened_at);
        $this->assertNull($recipient->last_clicked_at);
        $this->assertSame(1, (int) $recipient->opened, 'a message counts once in opened');
        $afterOpen = (int) $recipient->engagement_score;
        $this->assertGreaterThan(0, $afterOpen);

        $this->click($message, 'https://example.com/');

        $recipient = DB::table('recipients')->where('id', $message->recipient_id)->first();
        $this->assertNotNull($recipient->last_clicked_at);
        $this->assertSame(1, (int) $recipient->clicked);
        $this->assertGreaterThan($afterOpen, (int) $recipient->engagement_score);
        $this->assertLessThanOrEqual(100, (int) $recipient->engagement_score);
    }

    public function test_test_sends_do_not_touch_recipient_counters_or_campaign_links(): void
    {
        $message = $this->campaignMessage();
        DB::table('messages')->where('id', $message->id)->update(['is_test' => true]);
        $message->refresh();

        $this->open($message, self::IPHONE_MAIL);
        $this->click($message, 'https://example.com/');

        $recipient = DB::table('recipients')->where('id', $message->recipient_id)->first();
        $this->assertNull($recipient->last_opened_at);
        $this->assertSame(0, (int) $recipient->opened);
        $this->assertSame(0, CampaignLink::query()->count());
        $this->assertSame(2, MessageEvent::query()->count());
    }

    public function test_status_transitions_are_recorded_as_message_events(): void
    {
        $message = Message::factory()->create(['status' => MessageStatus::Sending->value]);

        $message->markStatus(MessageStatus::Accepted);
        $message->markStatus(MessageStatus::Accepted);
        $message->markStatus(MessageStatus::HardBounced);

        $this->assertSame(['sent', 'hard_bounced'], MessageEvent::query()->orderBy('id')->pluck('event_type')->all());

        $recipient = DB::table('recipients')->where('id', $message->recipient_id)->first();
        $this->assertSame(1, (int) $recipient->messages_sent);
        $this->assertSame(1, (int) $recipient->hard_bounces);
    }

    public function test_timestamps_stamped_outside_the_recorder_are_mirrored_into_message_events(): void
    {
        $message = Message::factory()->create();
        $message->forceFill(['status' => 'hard_bounced', 'sent_at' => '2026-10-02 09:00:00', 'bounced_at' => '2026-10-02 09:02:00'])->save();
        $message->forceFill(['updated_at' => now()])->save();

        $events = MessageEvent::query()->orderBy('id')->get();
        $this->assertSame(['sent', 'hard_bounced'], $events->pluck('event_type')->all());
        $this->assertSame('2026-10-02 09:02:00', $events[1]->occurred_at->format('Y-m-d H:i:s'));
        $this->assertSame('message', $events[1]->metadata['source']);
    }

    public function test_user_agent_parser_classifies_devices_and_clients(): void
    {
        $parser = new UserAgentParser;

        $this->assertSame(['device_type' => 'desktop', 'email_client' => 'Outlook', 'os' => 'Windows'], $parser->parse(self::OUTLOOK));
        $this->assertSame('tablet', $parser->parse('Mozilla/5.0 (iPad; CPU OS 16_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/15E148')['device_type']);
        $this->assertSame(
            ['device_type' => 'mobile', 'email_client' => 'Chrome Mobile', 'os' => 'Android'],
            $parser->parse('Mozilla/5.0 (Linux; Android 13; SM-S901B) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/112.0.0.0 Mobile Safari/537.36'),
        );
        $this->assertSame('Apple Mail', $parser->parse('Mozilla/5.0 (Macintosh) AppleWebKit/605.1.15 Mail/1.0')['email_client']);
        $this->assertSame('Gmail', $parser->parse('Mozilla/5.0 (Windows NT 5.1; rv:11.0) Gecko Firefox/11.0 (via ggpht.com GoogleImageProxy)')['email_client']);
        $this->assertSame(['device_type' => 'unknown', 'email_client' => null, 'os' => null], $parser->parse(null));
    }

    public function test_maxmind_resolver_returns_null_without_a_database(): void
    {
        $resolver = new MaxMindGeoIpResolver(storage_path('app/geoip/does-not-exist.mmdb'));

        $this->assertNull($resolver->lookup('41.111.10.20'));
        $this->assertNull($resolver->lookup('127.0.0.1'));
        $this->assertNull($resolver->lookup(null));
    }
}
