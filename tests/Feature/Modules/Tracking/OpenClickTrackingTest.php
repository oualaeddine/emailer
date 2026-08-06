<?php

namespace Tests\Feature\Modules\Tracking;

use App\Domain\Enums\MessageStatus;
use App\Modules\Tracking\Models\Message;
use App\Modules\Tracking\Services\TrackingRenderer;
use App\Modules\Tracking\Support\TrackingUrlGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * docs/21-open-click-tracking.md §21.1-21.6 — tracking pixel, click
 * redirect, unique-recipient semantics, bot filtering, and privacy
 * (anti-tamper) behaviour.
 */
class OpenClickTrackingTest extends TestCase
{
    use RefreshDatabase;

    public function test_pixel_request_records_first_open(): void
    {
        $message = Message::factory()->create(['status' => MessageStatus::Delivered->value]);
        $url = app(TrackingUrlGenerator::class)->signedOpenUrl($message);

        $response = $this->withHeaders(['User-Agent' => 'Mozilla/5.0 (Macintosh) AppleWebKit/605.1.15 Mail/1.0'])
            ->get($url);

        $response->assertOk();
        $response->assertHeader('Content-Type', 'image/gif');
        // Symfony's ResponseHeaderBag parses `Cache-Control` into a
        // directive set and re-serializes it (reordered, with `private`
        // appended) regardless of the exact string the controller set —
        // assert the required directives are present rather than an exact
        // string match.
        $cacheControl = $response->headers->get('Cache-Control');
        foreach (['no-store', 'no-cache', 'must-revalidate'] as $directive) {
            $this->assertStringContainsString($directive, $cacheControl);
        }

        $message->refresh();
        $this->assertSame(1, $message->open_count);
        $this->assertNotNull($message->opened_at);
        $this->assertSame(MessageStatus::Opened->value, $message->status);
    }

    public function test_second_pixel_request_increments_count_without_resetting_opened_at(): void
    {
        $message = Message::factory()->create(['status' => MessageStatus::Delivered->value]);
        $url = app(TrackingUrlGenerator::class)->signedOpenUrl($message);
        $userAgent = ['User-Agent' => 'Mozilla/5.0 (Macintosh) AppleWebKit/605.1.15 Mail/1.0'];

        $this->withHeaders($userAgent)->get($url)->assertOk();
        $message->refresh();
        $firstOpenedAt = $message->opened_at;

        $this->travel(5)->minutes();

        $this->withHeaders($userAgent)->get($url)->assertOk();
        $message->refresh();

        $this->assertSame(2, $message->open_count);
        $this->assertNotNull($message->opened_at);
        $this->assertTrue($firstOpenedAt->equalTo($message->opened_at));
    }

    public function test_bot_user_agent_is_filtered_and_does_not_change_counts(): void
    {
        $message = Message::factory()->create(['status' => MessageStatus::Delivered->value]);
        $url = app(TrackingUrlGenerator::class)->signedOpenUrl($message);

        $response = $this->withHeaders(['User-Agent' => 'GoogleImageProxy'])->get($url);

        // Still returns a valid pixel image to the requester (a bot-filtered
        // hit must look identical from the outside, docs/21 §21.5).
        $response->assertOk();
        $response->assertHeader('Content-Type', 'image/gif');

        $message->refresh();
        $this->assertSame(0, $message->open_count);
        $this->assertNull($message->opened_at);
        $this->assertSame(MessageStatus::Delivered->value, $message->status);
    }

    public function test_open_never_downgrades_a_later_status(): void
    {
        $message = Message::factory()->create([
            'status' => MessageStatus::Clicked->value,
            'clicked_at' => now(),
        ]);
        $url = app(TrackingUrlGenerator::class)->signedOpenUrl($message);

        $this->withHeaders(['User-Agent' => 'Mozilla/5.0 real client'])->get($url)->assertOk();

        $message->refresh();
        // Count/timestamp still recorded...
        $this->assertSame(1, $message->open_count);
        $this->assertNotNull($message->opened_at);
        // ...but status stays "clicked", not downgraded back to "opened".
        $this->assertSame(MessageStatus::Clicked->value, $message->status);
    }

    public function test_click_redirect_records_click_and_redirects_to_original_url(): void
    {
        $message = Message::factory()->create(['status' => MessageStatus::Delivered->value]);
        $url = app(TrackingUrlGenerator::class)->signedClickUrl($message, 'https://example.com/promo?ref=abc');

        $response = $this->get($url);

        $response->assertStatus(302);
        $response->assertHeader('Location', 'https://example.com/promo?ref=abc');

        $message->refresh();
        $this->assertSame(1, $message->click_count);
        $this->assertNotNull($message->clicked_at);
        $this->assertSame(MessageStatus::Clicked->value, $message->status);
    }

    public function test_second_click_increments_count_without_resetting_clicked_at(): void
    {
        $message = Message::factory()->create(['status' => MessageStatus::Delivered->value]);
        $url = app(TrackingUrlGenerator::class)->signedClickUrl($message, 'https://example.com/promo');

        $this->get($url)->assertStatus(302);
        $message->refresh();
        $firstClickedAt = $message->clicked_at;

        $this->travel(5)->minutes();

        $this->get($url)->assertStatus(302);
        $message->refresh();

        $this->assertSame(2, $message->click_count);
        $this->assertTrue($firstClickedAt->equalTo($message->clicked_at));
    }

    public function test_a_tampered_pixel_url_is_rejected(): void
    {
        $message = Message::factory()->create();
        $other = Message::factory()->create();
        $url = app(TrackingUrlGenerator::class)->signedOpenUrl($message);

        $tampered = str_replace($message->uuid, $other->uuid, $url);

        $this->get($tampered)->assertForbidden();

        $other->refresh();
        $this->assertSame(0, $other->open_count);
    }

    public function test_an_expired_pixel_url_is_rejected(): void
    {
        $message = Message::factory()->create();
        $url = app(TrackingUrlGenerator::class)->signedOpenUrl($message, now()->subMinute());

        $this->get($url)->assertForbidden();

        $message->refresh();
        $this->assertSame(0, $message->open_count);
    }

    public function test_a_tampered_click_redirect_url_is_rejected(): void
    {
        $message = Message::factory()->create();
        $url = app(TrackingUrlGenerator::class)->signedClickUrl($message, 'https://example.com/promo');

        $tampered = str_replace('example.com', 'evil-attacker.example', $url);

        $this->get($tampered)->assertForbidden();

        $message->refresh();
        $this->assertSame(0, $message->click_count);
    }

    public function test_a_click_target_with_a_disallowed_scheme_is_rejected(): void
    {
        // Bypasses TrackingRenderer's own scheme filtering to exercise the
        // controller's independent defense-in-depth check directly
        // (docs/21-open-click-tracking.md §21.6).
        $message = Message::factory()->create();
        $url = URL::signedRoute(
            'tracking.click',
            ['message' => $message->uuid, 'url' => 'javascript:alert(1)'],
            now()->addDay(),
        );

        $this->get($url)->assertStatus(400);

        $message->refresh();
        $this->assertSame(0, $message->click_count);
    }

    public function test_a_valid_signature_with_a_malformed_message_uuid_returns_not_found(): void
    {
        $url = URL::signedRoute('tracking.open', ['message' => str_repeat('a', 36)], now()->addDay());

        $this->get($url)->assertNotFound();
    }

    public function test_tracking_renderer_injects_pixel_before_closing_body_and_rewrites_links(): void
    {
        $message = Message::factory()->create();
        $html = '<html><body><p>Hello</p><a href="https://example.com/a">Click</a>'
            .'<a href="mailto:someone@example.com">Email us</a>'
            .'<a href="#section">Jump</a></body></html>';

        $rendered = app(TrackingRenderer::class)->render($message, $html);

        // Pixel injected just before </body>.
        $this->assertMatchesRegularExpression('#<img[^>]*width="1"[^>]*></body>#', $rendered);
        $this->assertStringContainsString('/t/o/'.$message->uuid.'.gif', $rendered);

        // http(s) link rewritten to the click tracker...
        $this->assertStringContainsString('/t/c/'.$message->uuid, $rendered);
        $this->assertStringNotContainsString('href="https://example.com/a"', $rendered);

        // ...but mailto:/#anchor links are left untouched.
        $this->assertStringContainsString('href="mailto:someone@example.com"', $rendered);
        $this->assertStringContainsString('href="#section"', $rendered);
    }

    public function test_tracking_renderer_appends_pixel_when_no_body_tag_present(): void
    {
        $message = Message::factory()->create();
        $html = '<p>Fragment only, no html/body wrapper.</p>';

        $rendered = app(TrackingRenderer::class)->render($message, $html);

        $this->assertStringContainsString('/t/o/'.$message->uuid.'.gif', $rendered);
        $this->assertStringEndsWith('/>', trim($rendered));
    }
}
