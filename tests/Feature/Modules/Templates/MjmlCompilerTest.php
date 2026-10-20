<?php

namespace Tests\Feature\Modules\Templates;

use App\Domain\Enums\RoleName;
use App\Modules\Identity\Models\User;
use App\Modules\Templates\Services\MjmlCompiler;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\Process\ExecutableFinder;
use Tests\TestCase;

/**
 * docs/46-editor-spike.md §4 — MjmlCompiler against the real `node` binary
 * and the npm `mjml@4.18.0` package. Skipped when either is missing.
 */
class MjmlCompilerTest extends TestCase
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

    public function test_compiles_valid_mjml_to_html_keeping_comments_and_merge_tags(): void
    {
        $mjml = '<mjml lang="fr"><mj-head><mj-attributes><mj-all font-family="Arial, sans-serif"></mj-all></mj-attributes></mj-head>'
            .'<mj-body><mj-section><mj-column>'
            .'<mj-raw><!--pj-dc:block1:0--></mj-raw>'
            .'<mj-text>Bonjour {{ contact.PRENOM }}</mj-text>'
            .'<mj-button href="https://exemple.dz">Découvrir</mj-button>'
            .'</mj-column></mj-section></mj-body></mjml>';

        $result = app(MjmlCompiler::class)->compile($mjml);

        $this->assertTrue($result->compilerAvailable);
        $this->assertSame([], $result->errors);
        $this->assertTrue($result->isValid());
        $this->assertStringContainsString('<!doctype html>', strtolower($result->html));
        $this->assertStringContainsString('lang="fr"', $result->html);
        $this->assertStringContainsString('Bonjour {{ contact.PRENOM }}', $result->html);
        $this->assertStringContainsString('<!--pj-dc:block1:0-->', $result->html);
        $this->assertStringContainsString('https://exemple.dz', $result->html);
    }

    public function test_reports_unknown_tags_and_illegal_attributes(): void
    {
        $mjml = "<mjml>\n<mj-body>\n<mj-section><mj-column>\n<mj-text foo=\"1\">x</mj-text>\n<mj-bogus></mj-bogus>\n</mj-column></mj-section></mj-body></mjml>";

        $result = app(MjmlCompiler::class)->compile($mjml);

        $this->assertFalse($result->isValid());
        $tags = array_column($result->errors, 'tag_name');
        $this->assertContains('mj-text', $tags);
        $this->assertContains('mj-bogus', $tags);
        $this->assertNotNull($result->errors[0]['line']);
    }

    public function test_sanitises_raw_html_blocks(): void
    {
        $mjml = '<mjml><mj-body><mj-section><mj-column><mj-raw><p onmouseover="steal()">Hi</p><script>alert(1)</script></mj-raw></mj-column></mj-section></mj-body></mjml>';

        $result = app(MjmlCompiler::class)->compile($mjml);

        $this->assertStringContainsString('Hi', $result->html);
        $this->assertStringNotContainsString('<script', $result->html);
        $this->assertStringNotContainsString('onmouseover', $result->html);
    }

    public function test_unparseable_input_is_an_error_not_an_exception(): void
    {
        $result = app(MjmlCompiler::class)->compile('pas du mjml');

        $this->assertFalse($result->isValid());
        $this->assertSame(MjmlCompiler::PARSE_FAILED, $result->errors[0]['message']);
    }

    public function test_endpoint_with_real_compiler(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->withRole(RoleName::MarketingManager)->create();

        $this->actingAs($user)->postJson('/api/v1/templates/mjml/validate', [
            'mjml' => '<mjml><mj-body><mj-section><mj-column><mj-text>OK</mj-text></mj-column></mj-section></mj-body></mjml>',
        ])->assertOk()->assertJsonPath('data.valid', true);
    }
}
