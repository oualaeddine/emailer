<?php

namespace Tests\Feature\Modules\DeliveryEngine\Rendering;

use App\Modules\Campaigns\Models\Campaign;
use App\Modules\DeliveryEngine\Rendering\Contracts\RenderingStage;
use App\Modules\DeliveryEngine\Rendering\RenderContext;
use App\Modules\DeliveryEngine\Rendering\RenderedEmail;
use App\Modules\DeliveryEngine\Rendering\RenderingPipeline;
use App\Modules\DeliveryEngine\Rendering\Stages\ConditionalBlocksStage;
use App\Modules\Recipients\Models\Recipient;
use App\Modules\Tracking\Models\Message;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * docs/38 §38.2 stage 3, docs/45 §45.7.1 (WP-61) — dynamic-content blocks
 * (`data-dynamic-rule` markers + `design_json.dynamic_blocks`) evaluated
 * per recipient at personalise time.
 */
class ConditionalBlocksTest extends TestCase
{
    use RefreshDatabase;

    /** Shape of the MJML compiler output: marker div around the section, Outlook conditionals and nested divs inside. */
    private const HTML = '<body><div class="intro">Bonjour</div>'
        .'<div data-dynamic-rule="dyn_actifs">'
        .'<!--[if mso | IE]><table><tr><td><![endif]--><div style="margin:0 auto"><div class="col">Offre réservée aux actifs</div></div>'
        .'<!--[if mso | IE]></td></tr></table><![endif]--></div>'
        .'<div class="outro">Au revoir</div></body>';

    /**
     * @return array<string, mixed>
     */
    private function rule(string $status): array
    {
        return ['version' => 2, 'type' => 'group', 'operator' => 'and', 'children' => [
            ['type' => 'condition', 'field' => 'contact.status', 'operator' => 'in', 'value' => [$status]],
        ]];
    }

    /**
     * @param  array<string, mixed>|null  $condition
     */
    private function campaign(?array $condition, string $id = 'dyn_actifs'): Campaign
    {
        return Campaign::factory()->create(['design_json' => [
            'format' => 'grapesjs-mjml', 'version' => 1, 'mjml' => '<mjml></mjml>', 'project' => [], 'global_styles' => [],
            'dynamic_blocks' => [[
                'id' => $id,
                'type' => 'dynamic_content',
                'variants' => [['condition' => $condition, 'mjml' => '']],
                'fallback_mjml' => '',
            ]],
        ]]);
    }

    private function evaluate(string $html, Recipient $recipient, ?Campaign $campaign): string
    {
        return app(ConditionalBlocksStage::class)
            ->process(new RenderedEmail('S', $html), new RenderContext($recipient, null, $campaign))
            ->html;
    }

    public function test_stage_runs_in_the_personalise_phase(): void
    {
        $this->assertSame(RenderingStage::PHASE_PERSONALISE, app(ConditionalBlocksStage::class)->phase());
    }

    public function test_matching_recipient_keeps_the_block_without_the_marker(): void
    {
        $recipient = Recipient::factory()->create();
        $out = $this->evaluate(self::HTML, $recipient, $this->campaign($this->rule('active')));

        $this->assertStringContainsString('Offre réservée aux actifs', $out);
        $this->assertStringNotContainsString('data-dynamic-rule', $out);
        $this->assertStringContainsString('<!--[if mso | IE]></td></tr></table><![endif]--><div class="outro">', $out);
        $this->assertStringContainsString('Bonjour', $out);
        $this->assertStringContainsString('Au revoir', $out);
        // Idempotent.
        $this->assertSame($out, $this->evaluate($out, $recipient, $this->campaign($this->rule('active'))));
    }

    public function test_non_matching_recipient_loses_the_whole_block(): void
    {
        $recipient = Recipient::factory()->suppressed()->create();
        $out = $this->evaluate(self::HTML, $recipient, $this->campaign($this->rule('active')));

        $this->assertSame('<body><div class="intro">Bonjour</div><div class="outro">Au revoir</div></body>', $out);
    }

    public function test_malformed_rule_keeps_the_block_and_logs_a_warning(): void
    {
        Log::spy();
        $recipient = Recipient::factory()->suppressed()->create();

        $out = $this->evaluate(self::HTML, $recipient, $this->campaign(['version' => 2, 'type' => 'group', 'operator' => 'xor', 'children' => 'oops']));

        $this->assertStringContainsString('Offre réservée aux actifs', $out);
        $this->assertStringNotContainsString('data-dynamic-rule', $out);
        Log::shouldHaveReceived('warning')->withArgs(fn (string $message, array $context): bool => str_contains($message, 'malformed rule')
            && $context['dynamic_rule'] === 'dyn_actifs')->once();
    }

    public function test_unknown_rule_id_keeps_the_block_and_logs_a_warning(): void
    {
        Log::spy();
        $recipient = Recipient::factory()->suppressed()->create();

        $out = $this->evaluate(self::HTML, $recipient, $this->campaign($this->rule('active'), 'dyn_autre'));

        $this->assertStringContainsString('Offre réservée aux actifs', $out);
        Log::shouldHaveReceived('warning')->withArgs(fn (string $message): bool => str_contains($message, 'without a rule'))->once();
    }

    public function test_several_blocks_are_evaluated_independently(): void
    {
        $recipient = Recipient::factory()->create();
        $campaign = Campaign::factory()->create(['design_json' => ['dynamic_blocks' => [
            ['id' => 'a', 'type' => 'dynamic_content', 'variants' => [['condition' => $this->rule('active'), 'mjml' => '']], 'fallback_mjml' => ''],
            ['id' => 'b', 'type' => 'dynamic_content', 'variants' => [['condition' => $this->rule('suppressed'), 'mjml' => '']], 'fallback_mjml' => ''],
        ]]]);
        $html = '<div data-dynamic-rule="a"><div>A</div></div><p>milieu</p><div data-dynamic-rule="b"><div>B</div></div><p>fin</p>';

        $this->assertSame('<div>A</div><p>milieu</p><p>fin</p>', $this->evaluate($html, $recipient, $campaign));
    }

    public function test_html_without_markers_is_unchanged(): void
    {
        $email = new RenderedEmail('S', '<p>Rien</p>');
        $recipient = Recipient::factory()->create();

        $this->assertSame($email, app(ConditionalBlocksStage::class)->process($email, new RenderContext($recipient)));
    }

    public function test_personalised_message_html_is_evaluated_per_recipient(): void
    {
        $campaign = $this->campaign($this->rule('active'));
        $pipeline = app(RenderingPipeline::class);
        $active = Recipient::factory()->create(['email' => 'actif@exemple.dz']);
        $suppressed = Recipient::factory()->suppressed()->create(['email' => 'inactif@exemple.dz']);

        $kept = $pipeline->createMessage(['recipient_id' => $active->id, 'campaign_id' => $campaign->id, 'status' => 'queued'], 'S', self::HTML, $active, $campaign);
        $removed = $pipeline->createMessage(['recipient_id' => $suppressed->id, 'campaign_id' => $campaign->id, 'status' => 'queued'], 'S', self::HTML, $suppressed, $campaign);

        $this->assertInstanceOf(Message::class, $kept);
        $this->assertStringContainsString('Offre réservée aux actifs', (string) $kept->html_body);
        $this->assertStringNotContainsString('Offre réservée aux actifs', (string) $removed->html_body);
        $this->assertStringNotContainsString('data-dynamic-rule', (string) $kept->html_body.$removed->html_body);
    }
}
