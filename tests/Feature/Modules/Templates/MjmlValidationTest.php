<?php

namespace Tests\Feature\Modules\Templates;

use App\Domain\Enums\RoleName;
use App\Modules\Identity\Models\User;
use App\Modules\Templates\Services\HtmlSanitizer;
use App\Modules\Templates\Services\MjmlCompiler;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Spatie\Mjml\MjmlResult;
use Tests\TestCase;

/**
 * docs/45-brevo-api-contracts.md §45.6.1 — WP-60: POST /templates/mjml/validate.
 * The compiler is stubbed here; the real Node compile is covered by MjmlCompilerTest.
 */
class MjmlValidationTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/api/v1/templates/mjml/validate';

    private const MJML = '<mjml lang="fr"><mj-body><mj-section><mj-column><mj-text>Bonjour {{ contact.PRENOM }}</mj-text></mj-column></mj-section></mj-body></mjml>';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    /**
     * @param  array<string, mixed>|null  $result  raw mjml result; null = compiler unavailable
     */
    private function fakeCompiler(?array $result): void
    {
        $this->app->instance(MjmlCompiler::class, new class($result, new HtmlSanitizer) extends MjmlCompiler
        {
            /** @param array<string, mixed>|null $result */
            public function __construct(private ?array $result, HtmlSanitizer $sanitizer)
            {
                parent::__construct($sanitizer);
            }

            protected function convert(string $mjml): MjmlResult
            {
                if ($this->result === null) {
                    throw new RuntimeException('node: command not found');
                }

                return new MjmlResult($this->result);
            }
        });
    }

    private function user(RoleName $role): User
    {
        return User::factory()->withRole($role)->create();
    }

    public function test_valid_mjml_returns_sanitised_server_html(): void
    {
        $this->fakeCompiler([
            'html' => '<!doctype html><html><body><p onclick="x()">Bonjour</p><script>alert(1)</script></body></html>',
            'errors' => [],
        ]);

        $response = $this->actingAs($this->user(RoleName::MarketingManager))
            ->postJson(self::URL, ['mjml' => self::MJML]);

        $response->assertOk()
            ->assertJsonPath('data.valid', true)
            ->assertJsonPath('data.errors', [])
            ->assertJsonStructure(['data' => ['valid', 'html', 'errors', 'size_bytes']]);

        $html = $response->json('data.html');
        $this->assertStringContainsString('Bonjour', $html);
        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('onclick', $html);
        $this->assertSame(strlen($html), $response->json('data.size_bytes'));
    }

    public function test_invalid_mjml_returns_errors_with_line_and_tag(): void
    {
        $this->fakeCompiler([
            'html' => '<html></html>',
            'errors' => [
                ['line' => 3, 'message' => "Element mj-bogus doesn't exist or is not registered", 'tagName' => 'mj-bogus'],
            ],
        ]);

        $this->actingAs($this->user(RoleName::MarketingManager))
            ->postJson(self::URL, ['mjml' => self::MJML])
            ->assertOk()
            ->assertJsonPath('data.valid', false)
            ->assertJsonPath('data.errors.0.line', 3)
            ->assertJsonPath('data.errors.0.tag_name', 'mj-bogus')
            ->assertJsonPath('data.errors.0.message', "Element mj-bogus doesn't exist or is not registered");
    }

    public function test_request_validation_returns_422(): void
    {
        $this->fakeCompiler(['html' => '<html></html>', 'errors' => []]);
        $manager = $this->user(RoleName::MarketingManager);

        $this->actingAs($manager)->postJson(self::URL, [])
            ->assertStatus(422)->assertJsonValidationErrors(['mjml']);

        $this->actingAs($manager)->postJson(self::URL, ['mjml' => '<html><body>pas du MJML</body></html>'])
            ->assertStatus(422)->assertJsonValidationErrors(['mjml']);

        $tooLarge = '<mjml><mj-body><mj-raw>'.str_repeat('a', 1024 * 1024).'</mj-raw></mj-body></mjml>';
        $this->actingAs($manager)->postJson(self::URL, ['mjml' => $tooLarge])
            ->assertStatus(422)->assertJsonValidationErrors(['mjml']);
    }

    public function test_unavailable_compiler_without_trust_reports_error(): void
    {
        $this->fakeCompiler(null);
        config(['editor.trust_client_html' => false]);

        $this->actingAs($this->user(RoleName::MarketingManager))
            ->postJson(self::URL, ['mjml' => self::MJML, 'html' => '<html><body>client</body></html>'])
            ->assertOk()
            ->assertJsonPath('data.valid', false)
            ->assertJsonPath('data.html', '')
            ->assertJsonPath('data.errors.0.message', MjmlCompiler::UNAVAILABLE);
    }

    public function test_unavailable_compiler_with_trust_uses_sanitised_client_html(): void
    {
        $this->fakeCompiler(null);
        config(['editor.trust_client_html' => true]);

        $response = $this->actingAs($this->user(RoleName::MarketingManager))
            ->postJson(self::URL, ['mjml' => self::MJML, 'html' => '<html><body><a href="javascript:alert(1)">client</a></body></html>']);

        $response->assertOk()->assertJsonPath('data.valid', true)->assertJsonPath('data.errors', []);
        $this->assertStringContainsString('client', $response->json('data.html'));
        $this->assertStringNotContainsString('javascript:', $response->json('data.html'));
    }

    public function test_trust_flag_defaults_on_in_testing(): void
    {
        $this->assertTrue(config('editor.trust_client_html'));
    }

    public function test_permissions(): void
    {
        $this->fakeCompiler(['html' => '<html></html>', 'errors' => []]);

        // campaigns.create without templates.manage is enough.
        $this->actingAs($this->user(RoleName::MarketingOperator))
            ->postJson(self::URL, ['mjml' => self::MJML])
            ->assertOk();

        $this->actingAs($this->user(RoleName::Viewer))
            ->postJson(self::URL, ['mjml' => self::MJML])
            ->assertForbidden();
    }

    public function test_guest_is_unauthorized(): void
    {
        $this->postJson(self::URL, ['mjml' => self::MJML])->assertUnauthorized();
    }
}
