<?php

namespace Tests\Feature\Modules\DeliveryEngine\Rendering;

use App\Modules\Campaigns\Models\Campaign;
use App\Modules\DeliveryEngine\Rendering\Contracts\RenderingStage;
use App\Modules\DeliveryEngine\Rendering\RenderContext;
use App\Modules\DeliveryEngine\Rendering\RenderedEmail;
use App\Modules\DeliveryEngine\Rendering\RenderingPipeline;
use App\Modules\DeliveryEngine\Rendering\Stages\ConditionalBlocksStage;
use App\Modules\DeliveryEngine\Rendering\Stages\CssInlineStage;
use App\Modules\DeliveryEngine\Rendering\Stages\InlineImagesStage;
use App\Modules\DeliveryEngine\Rendering\Stages\LinkIdStage;
use App\Modules\DeliveryEngine\Rendering\Stages\MergeTagsStage;
use App\Modules\DeliveryEngine\Rendering\Stages\PlainTextStage;
use App\Modules\DeliveryEngine\Rendering\Stages\SignatureStage;
use App\Modules\DeliveryEngine\Rendering\Stages\ToFieldStage;
use App\Modules\DeliveryEngine\Rendering\Stages\TrackingStage;
use App\Modules\DeliveryEngine\Rendering\Stages\UtmRewriteStage;
use App\Modules\DeliveryEngine\Rendering\Support\Links;
use App\Modules\Recipients\Models\Recipient;
use App\Modules\Tracking\Models\Message;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * docs/38 §38.2-38.4, docs/43 §43.6.3 / §43.6.6, docs/45 §45.6.3 — stage
 * order and the individual stage rules of the rendering pipeline.
 */
class RenderingPipelineTest extends TestCase
{
    use RefreshDatabase;

    public function test_stage_order_follows_doc_38(): void
    {
        $this->assertSame([
            MergeTagsStage::class,
            ToFieldStage::class,
            ConditionalBlocksStage::class,
            SignatureStage::class,
            UtmRewriteStage::class,
            LinkIdStage::class,
            TrackingStage::class,
            CssInlineStage::class,
            PlainTextStage::class,
            InlineImagesStage::class,
        ], array_map(fn ($s) => $s::class, app(RenderingPipeline::class)->stages()));

        $personalise = array_map(fn ($s) => $s::class, app(RenderingPipeline::class)->stages(RenderingStage::PHASE_PERSONALISE));
        $this->assertSame([MergeTagsStage::class, ConditionalBlocksStage::class, SignatureStage::class, UtmRewriteStage::class, LinkIdStage::class], $personalise);
    }

    public function test_data_link_id_is_sha1_prefix_of_the_url_the_click_tracker_receives(): void
    {
        [$message, $recipient] = $this->message();
        $html = '<p><a href="https://example.com/a?x=1&amp;y=2">A</a> <a href="https://example.com/b">B</a></p>';

        $rendered = app(RenderingPipeline::class)->renderFull('S', $html, new RenderContext($recipient, $message));

        preg_match_all('/<a data-link-id="([0-9a-f]+)" href="([^"]+)"/', $rendered->html, $m, PREG_SET_ORDER);
        $this->assertCount(2, $m);

        foreach ($m as [, $id, $href]) {
            $this->assertSame(16, strlen($id));
            parse_str((string) parse_url(html_entity_decode($href), PHP_URL_QUERY), $query);
            $this->assertStringStartsWith(url('t/c/'.$message->uuid), html_entity_decode($href));
            $this->assertSame(substr(sha1($query['url']), 0, 16), $id);
            $this->assertSame(Links::linkId($query['url']), $id);
        }

        $this->assertSame(substr(sha1('https://example.com/a?x=1&y=2'), 0, 16), $m[0][1]);
    }

    public function test_utm_is_appended_only_to_http_links_without_utm(): void
    {
        [$message, $recipient] = $this->message();
        $campaign = Campaign::factory()->create([
            'name' => 'Rentrée', 'utm_enabled' => true, 'utm_source' => 'mail', 'utm_medium' => 'email', 'utm_campaign' => 'rentree', 'utm_id' => '42',
        ]);
        $html = '<a href="https://a.example.com/p#frag">1</a>'
            .'<a href="https://b.example.com/?utm_source=own">2</a>'
            .'<a href="mailto:x@example.com">3</a><a href="#top">4</a><a href="tel:+1">5</a>'
            .'<a href="{{ unsubscribe }}">6</a><a href="{{ mirror }}">7</a>'
            .'<a href="http://c.example.com/q?a=1">8</a>';

        $out = app(RenderingPipeline::class)->run(
            new RenderedEmail('S', $html),
            new RenderContext($recipient, $message, $campaign),
            RenderingStage::PHASE_PERSONALISE,
        )->html;

        $this->assertStringContainsString('href="https://a.example.com/p?utm_source=mail&amp;utm_medium=email&amp;utm_campaign=rentree&amp;utm_id=42#frag"', $out);
        $this->assertStringContainsString('href="https://b.example.com/?utm_source=own"', $out);
        $this->assertStringContainsString('href="http://c.example.com/q?a=1&amp;utm_source=mail', $out);
        $this->assertStringContainsString('href="mailto:x@example.com"', $out);
        $this->assertStringContainsString('href="#top"', $out);
        $this->assertSame(2, substr_count($out, 'utm_source=mail'));

        // Unsubscribe / mirror links are never UTM-tagged nor given a link id.
        preg_match_all('/<a[^>]*href="([^"]*\/(?:unsubscribe|m)\/[^"]*)"[^>]*>/', $out, $system);
        $this->assertCount(2, $system[1]);
        foreach ($system[0] as $i => $tag) {
            $this->assertStringNotContainsString('utm_', $system[1][$i]);
            $this->assertStringNotContainsString('data-link-id', $tag);
        }

        // Disabled → untouched.
        $campaign->update(['utm_enabled' => false]);
        $plain = app(RenderingPipeline::class)->run(new RenderedEmail('S', '<a href="https://a.example.com/">x</a>'), new RenderContext($recipient, $message, $campaign), RenderingStage::PHASE_PERSONALISE)->html;
        $this->assertStringNotContainsString('utm_', $plain);
    }

    public function test_system_links_are_not_click_tracked_and_double_rendering_is_stable(): void
    {
        [$message, $recipient] = $this->message();
        $html = '<html><body><a href="https://example.com/x">x</a><a href="{{ unsubscribe }}">u</a><a href="{{ mirror }}">m</a></body></html>';
        $pipeline = app(RenderingPipeline::class);

        $once = $pipeline->renderFull('S', $html, new RenderContext($recipient, $message));
        $twice = $pipeline->run($once, new RenderContext($recipient, $message));

        $this->assertSame($once->html, $twice->html);
        $this->assertSame(1, substr_count($twice->html, '/t/o/'));
        $this->assertSame(1, substr_count($twice->html, '/t/c/'));
        $this->assertStringContainsString('href="'.url('unsubscribe').'/', $twice->html);
        $this->assertStringContainsString('href="'.url('m/'.$message->uuid), $twice->html);
        $this->assertStringNotContainsString('url=http%3A%2F%2F', $twice->html);
    }

    public function test_mirror_tag_respects_campaign_setting_and_expiration(): void
    {
        [$message, $recipient] = $this->message();
        $pipeline = app(RenderingPipeline::class);

        $off = Campaign::factory()->create(['mirror_link_enabled' => false]);
        $this->assertSame('[]', $pipeline->run(new RenderedEmail('S', '[{{ mirror }}]'), new RenderContext($recipient, $message, $off), RenderingStage::PHASE_PERSONALISE)->html);

        $on = Campaign::factory()->create(['mirror_link_enabled' => true]);
        $url = $pipeline->run(new RenderedEmail('S', '{{ mirror }}'), new RenderContext($recipient, $message, $on), RenderingStage::PHASE_PERSONALISE)->html;
        $this->assertStringStartsWith(url('m/'.$message->uuid).'?signature=', $url);

        $expiring = Campaign::factory()->create(['mirror_link_enabled' => true, 'email_expiration_duration' => 2, 'email_expiration_unit' => 'days']);
        $url = $pipeline->run(new RenderedEmail('S', '{{ mirror }}'), new RenderContext($recipient, $message, $expiring), RenderingStage::PHASE_PERSONALISE)->html;
        $this->assertStringContainsString('expires='.now()->addDays(2)->getTimestamp(), $url);
    }

    public function test_update_profile_uses_preferences_route_when_available(): void
    {
        [$message, $recipient] = $this->message();
        $pipeline = app(RenderingPipeline::class);

        if (! Route::has('preferences.show')) {
            $this->assertSame('#', $pipeline->run(new RenderedEmail('S', '{{ update_profile }}'), new RenderContext($recipient, $message), RenderingStage::PHASE_PERSONALISE)->html);

            Route::middleware('signed')->get('preferences/{token}', fn () => 'ok')->name('preferences.show');
            app('router')->getRoutes()->refreshNameLookups();
        }

        $html = $pipeline->run(new RenderedEmail('S', '{{ update_profile }}'), new RenderContext($recipient, $message), RenderingStage::PHASE_PERSONALISE)->html;
        $this->assertStringStartsWith(url('preferences').'/', html_entity_decode($html));
        $this->assertStringContainsString('signature=', $html);
    }

    public function test_merge_values_are_escaped_in_html_but_not_in_subject_and_defaults_apply(): void
    {
        [$message] = $this->message();
        $recipient = Recipient::factory()->create(['first_name' => '<b>Ali</b>', 'last_name' => null, 'custom_fields' => []]);

        $out = app(RenderingPipeline::class)->run(
            new RenderedEmail('{{ contact.PRENOM }} !', '<p>{{ contact.PRENOM }} {{ contact.NOM | default:"<i>ami</i>" }}</p>'),
            new RenderContext($recipient, $message),
            RenderingStage::PHASE_PERSONALISE,
        );

        $this->assertSame('<b>Ali</b> !', $out->subject);
        $this->assertSame('<p>&lt;b&gt;Ali&lt;/b&gt; <i>ami</i></p>', $out->html);
    }

    public function test_to_field_renders_display_name(): void
    {
        [$message] = $this->message();
        $recipient = Recipient::factory()->create(['first_name' => 'Yacine', 'last_name' => 'Haddad', 'custom_fields' => []]);
        $campaign = Campaign::factory()->create(['to_field' => "{{ contact.PRENOM }}\n{LNAME}"]);

        $out = app(RenderingPipeline::class)->renderFull('S', '<p>x</p>', new RenderContext($recipient, $message, $campaign));

        $this->assertSame('Yacine Haddad', $out->toName);
        $this->assertSame('Yacine Haddad', $out->toAddress($recipient->email)->getName());

        $none = Campaign::factory()->create(['to_field' => '{{ contact.SOCIETE }}']);
        $this->assertNull(app(RenderingPipeline::class)->renderFull('S', 'x', new RenderContext(Recipient::factory()->create(['company_name' => null]), $message, $none))->toName);
    }

    public function test_plain_text_keeps_links_lists_and_appends_unsubscribe(): void
    {
        $text = PlainTextStage::fromHtml('<html><head><style>p{}</style></head><body><h1>Titre</h1><p>Un&nbsp;<a href="https://e.com/a?b=1&amp;c=2">lien</a></p><ul><li>un</li><li>deux</li></ul><p><a href="https://e.com">https://e.com</a><br>fin &amp; merci</p></body></html>');

        $this->assertSame("Titre\n\nUn lien [https://e.com/a?b=1&c=2]\n\n- un\n- deux\n\nhttps://e.com\nfin & merci", $text);

        [$message, $recipient] = $this->message();
        $out = app(RenderingPipeline::class)->renderFull('S', '<p>Bonjour</p>', new RenderContext($recipient, $message));
        $this->assertMatchesRegularExpression('#^Bonjour\n\nSe désinscrire : '.preg_quote(url('unsubscribe'), '#').'/#', (string) $out->text);
    }

    public function test_preview_runs_merge_and_css_only(): void
    {
        $recipient = Recipient::factory()->make(['first_name' => 'Lina']);
        $out = app(RenderingPipeline::class)->preview('S', '<style>p{color:red}</style><p>{{ contact.PRENOM }} <a href="https://e.com">e</a> {{ mirror }}</p>', $recipient);

        $this->assertStringContainsString('Lina', $out->html);
        $this->assertStringContainsString('style="color: red;"', $out->html);
        $this->assertStringNotContainsString('/t/', $out->html);
        $this->assertStringNotContainsString('data-link-id', $out->html);
        $this->assertNull($out->text);
    }

    /**
     * @return array{Message, Recipient}
     */
    private function message(): array
    {
        $recipient = Recipient::factory()->create(['email' => 'r@example.com', 'custom_fields' => []]);
        $message = Message::factory()->create(['recipient_id' => $recipient->id]);

        return [$message, $recipient];
    }
}
