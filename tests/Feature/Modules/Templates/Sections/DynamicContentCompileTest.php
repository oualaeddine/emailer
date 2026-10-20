<?php

namespace Tests\Feature\Modules\Templates\Sections;

use App\Modules\Campaigns\Models\Campaign;
use App\Modules\DeliveryEngine\Rendering\RenderContext;
use App\Modules\DeliveryEngine\Rendering\RenderedEmail;
use App\Modules\DeliveryEngine\Rendering\Stages\ConditionalBlocksStage;
use App\Modules\Recipients\Models\Recipient;
use App\Modules\Templates\Sections\Services\PremadeSectionCatalog;
use App\Modules\Templates\Services\MjmlCompiler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\Process\ExecutableFinder;
use Tests\TestCase;

/**
 * WP-61 — the EmailBuilder's dynamic-content markers and the pre-made
 * sections against the real MJML compiler (skipped without node / mjml).
 */
class DynamicContentCompileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $node = (new ExecutableFinder)->find('node', null, ['/usr/local/bin', '/opt/homebrew/bin']);
        if ($node === null) {
            $this->markTestSkipped('Binaire node introuvable : compilation MJML réelle non testée.');
        }
        if (! is_file(base_path('node_modules/mjml/package.json'))) {
            $this->markTestSkipped('Paquet npm mjml absent (npm install) : compilation MJML réelle non testée.');
        }
    }

    public function test_marker_survives_compilation_and_is_resolved_per_recipient(): void
    {
        $mjml = '<mjml lang="fr"><mj-body>'
            .'<mj-section><mj-column><mj-text>Pour tous</mj-text></mj-column></mj-section>'
            .'<mj-raw><div data-dynamic-rule="dyn_abc123"></mj-raw>'
            .'<mj-wrapper padding="0px"><mj-section><mj-column><mj-text>Réservé aux actifs</mj-text></mj-column></mj-section></mj-wrapper>'
            .'<mj-raw></div></mj-raw>'
            .'<mj-section><mj-column><mj-text>Fin</mj-text></mj-column></mj-section>'
            .'</mj-body></mjml>';

        $compiled = app(MjmlCompiler::class)->compile($mjml);
        $this->assertSame([], $compiled->errors);
        $this->assertStringContainsString('<div data-dynamic-rule="dyn_abc123">', $compiled->html);

        $campaign = Campaign::factory()->create(['design_json' => ['dynamic_blocks' => [[
            'id' => 'dyn_abc123', 'type' => 'dynamic_content', 'fallback_mjml' => '',
            'variants' => [['mjml' => '', 'condition' => ['version' => 2, 'type' => 'group', 'operator' => 'and', 'children' => [
                ['type' => 'condition', 'field' => 'contact.status', 'operator' => 'in', 'value' => ['active']],
            ]]]],
        ]]]]);
        $stage = app(ConditionalBlocksStage::class);

        $kept = $stage->process(new RenderedEmail('S', $compiled->html), new RenderContext(Recipient::factory()->create(), null, $campaign))->html;
        $removed = $stage->process(new RenderedEmail('S', $compiled->html), new RenderContext(Recipient::factory()->suppressed()->create(), null, $campaign))->html;

        $this->assertStringContainsString('Réservé aux actifs', $kept);
        $this->assertStringNotContainsString('Réservé aux actifs', $removed);
        foreach ([$kept, $removed] as $html) {
            $this->assertStringNotContainsString('data-dynamic-rule', $html);
            $this->assertStringContainsString('Pour tous', $html);
            $this->assertStringContainsString('Fin', $html);
            $this->assertSame(substr_count($html, '<div'), substr_count($html, '</div>'));
        }
    }

    public function test_every_premade_section_compiles_without_errors(): void
    {
        foreach (app(PremadeSectionCatalog::class)->all() as $section) {
            $result = app(MjmlCompiler::class)->compile('<mjml lang="fr"><mj-body>'.$section['mjml'].'</mj-body></mjml>');

            $this->assertSame([], $result->errors, $section['key']);
        }
    }
}
