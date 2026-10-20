<?php

namespace Tests\Feature\Modules\DeliveryEngine\Rendering;

use App\Domain\Enums\MessageStatus;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\DeliveryEngine\Rendering\RenderingPipeline;
use App\Modules\DeliveryEngine\Rendering\Support\SystemUrls;
use App\Modules\Recipients\Models\Recipient;
use App\Modules\Tracking\Models\Message;
use App\Modules\Tracking\Support\TrackingUrlGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * docs/45 §45.6.3 — signed `GET /m/{message}` ("voir dans le navigateur")
 * and `GET /m/c/{campaign}` (anonymous share link).
 */
class MirrorPageTest extends TestCase
{
    use RefreshDatabase;

    private function campaignMessage(array $campaign = [], bool $isTest = false): array
    {
        $campaign = Campaign::factory()->create(array_merge([
            'subject' => 'Nouveautés pour {{ contact.PRENOM }}',
            'html_body' => '<html><body><p>Bonjour {{ contact.PRENOM }}</p><a href="https://shop.example.com/a">Shop</a><a href="{{ mirror }}">Web</a></body></html>',
        ], $campaign));
        $recipient = Recipient::factory()->create(['first_name' => 'Rania', 'custom_fields' => []]);

        $attributes = ['campaign_id' => $campaign->id, 'recipient_id' => $recipient->id, 'status' => MessageStatus::Queued->value, 'queued_at' => now()];
        if ($isTest) {
            $attributes['is_test'] = true;
        }

        $message = app(RenderingPipeline::class)->createMessage($attributes, (string) $campaign->subject, (string) $campaign->html_body, $recipient, $campaign);

        preg_match('/href="([^"]*\/m\/[^"]+)"/', $message->html_body, $m);

        return [$campaign, $message, html_entity_decode($m[1])];
    }

    public function test_mirror_page_shows_personalised_html_without_tracking(): void
    {
        [$campaign, $message, $url] = $this->campaignMessage();

        $response = $this->get($url)->assertOk();

        $response->assertSee('Nouveautés pour Rania');
        $response->assertSee('Version en ligne', false);
        $response->assertSee('Bonjour Rania', false);
        $response->assertSee('sandbox=', false);
        $response->assertDontSee('data-link-id', false);
        $response->assertDontSee('/t/o/', false);
        $response->assertDontSee('/t/c/', false);
        $this->assertStringContainsString('data-link-id', $message->html_body);
        $this->assertSame(1, (int) $campaign->fresh()->mirror_clicks);
    }

    public function test_tracking_markup_in_stored_html_is_stripped(): void
    {
        [, $message, $url] = $this->campaignMessage();
        $urls = app(TrackingUrlGenerator::class);
        $message->forceFill(['html_body' => '<p><a data-link-id="abc" href="'.e($urls->signedClickUrl($message, 'https://orig.example.com/p?x=1')).'">o</a></p>'
            .'<img src="'.e($urls->signedOpenUrl($message)).'" width="1" height="1">'])->save();

        $this->get($url)->assertOk()
            ->assertSee('https://orig.example.com/p?x=1', false)
            ->assertDontSee('/t/o/', false)
            ->assertDontSee('/t/c/', false)
            ->assertDontSee('data-link-id', false);
    }

    public function test_unsigned_tampered_or_expired_links_are_rejected(): void
    {
        [, $message, $url] = $this->campaignMessage(['email_expiration_duration' => 1, 'email_expiration_unit' => 'days']);

        $this->get(url('m/'.$message->uuid))->assertForbidden();
        $this->get(preg_replace('/signature=([0-9a-f])/', 'signature=0$1', $url))->assertForbidden();
        $this->get(preg_replace('/expires=\d+/', 'expires=9999999999', $url))->assertForbidden();
        $this->get($url)->assertOk();

        $this->travel(2)->days();
        $this->get($url)->assertForbidden();
    }

    public function test_test_messages_do_not_count_mirror_clicks(): void
    {
        [$campaign, , $url] = $this->campaignMessage([], true);

        $this->get($url)->assertOk();
        $this->assertSame(0, (int) $campaign->fresh()->mirror_clicks);
    }

    public function test_mirror_link_is_empty_when_disabled_on_the_campaign(): void
    {
        $campaign = Campaign::factory()->create(['mirror_link_enabled' => false, 'html_body' => '<a href="{{ mirror }}">Web</a>']);
        $recipient = Recipient::factory()->create(['custom_fields' => []]);

        $message = app(RenderingPipeline::class)->createMessage(
            ['campaign_id' => $campaign->id, 'recipient_id' => $recipient->id, 'status' => MessageStatus::Queued->value],
            'S', (string) $campaign->html_body, $recipient, $campaign,
        );

        $this->assertSame('<a href="">Web</a>', $message->html_body);
    }

    public function test_anonymous_campaign_share_link_renders_defaults(): void
    {
        $campaign = Campaign::factory()->create([
            'subject' => 'Lettre',
            'html_body' => '<p>Bonjour {{ contact.PRENOM | default:"à tous" }}</p><a href="{{ unsubscribe }}">u</a>',
        ]);

        $this->get(app(SystemUrls::class)->campaignMirror($campaign))->assertOk()
            ->assertSee('Bonjour à tous', false)
            ->assertDontSee('/unsubscribe/', false);

        $this->get(URL::route('mirror.campaign', ['campaign' => $campaign->uuid]))->assertForbidden();
    }
}
