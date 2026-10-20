<?php

namespace Tests\Feature\Modules\Templates;

use App\Domain\Enums\RoleName;
use App\Modules\DeliveryEngine\Jobs\SendEmailJob;
use App\Modules\Identity\Models\User;
use App\Modules\Templates\Models\Template;
use App\Modules\Templates\Services\HtmlSanitizer;
use App\Modules\Templates\Services\MjmlCompiler;
use App\Modules\Templates\Services\TemplateService;
use App\Modules\Tracking\Models\Message;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Spatie\Mjml\MjmlResult;
use Tests\TestCase;

/** Wave 3 review fixes: B1 mj-include, S6 version design snapshot, N3 test-send merge tags. */
class TemplateReviewFixesTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->manager = User::factory()->withRole(RoleName::MarketingManager)->create();
    }

    public function test_mj_include_is_rejected_by_the_validation_endpoint(): void
    {
        $mjml = '<mjml><mj-body><mj-include path="/etc/passwd" /></mj-body></mjml>';

        $this->actingAs($this->manager)->postJson('/api/v1/templates/mjml/validate', ['mjml' => $mjml])
            ->assertStatus(422)->assertJsonValidationErrors(['mjml'])
            ->assertJsonPath('errors.mjml.0', MjmlCompiler::INCLUDE_FORBIDDEN);
    }

    public function test_compiler_refuses_mj_include_without_invoking_node(): void
    {
        $compiler = new class(new HtmlSanitizer) extends MjmlCompiler
        {
            public bool $called = false;

            protected function convert(string $mjml): MjmlResult
            {
                $this->called = true;

                return new MjmlResult(['html' => '', 'errors' => []]);
            }
        };

        $result = $compiler->compile("<mjml><mj-body>\n< MJ-INCLUDE path=\"file:///etc/passwd\"/></mj-body></mjml>");

        $this->assertFalse($compiler->called);
        $this->assertSame(MjmlCompiler::INCLUDE_FORBIDDEN, $result->errors[0]['message']);
    }

    public function test_restoring_a_version_restores_design_json_and_editor_type(): void
    {
        $service = app(TemplateService::class);
        $template = $service->create($this->manager, 'T', '<p>v1</p>', null, ['editor_type' => 'dnd', 'design_json' => ['blocks' => [1]]]);
        $v1 = $template->versions()->where('version_number', 1)->firstOrFail();
        $this->assertSame(['blocks' => [1]], $v1->design_json);
        $this->assertSame('dnd', $v1->editor_type);

        $template->update(['design_json' => ['blocks' => [2]]]);
        $service->update($template->fresh(), $this->manager, '<p>v2</p>');

        $restored = $service->restoreVersion($template->fresh(), $v1, $this->manager);
        $this->assertSame('<p>v1</p>', $restored->html_content);
        $this->assertSame(['blocks' => [1]], $restored->design_json);
        $this->assertSame('dnd', $restored->editor_type);
    }

    public function test_restoring_a_version_without_design_resets_to_html(): void
    {
        $service = app(TemplateService::class);
        $template = $service->create($this->manager, 'T', '<p>v1</p>');
        $v1 = $template->versions()->firstOrFail();
        $template->update(['editor_type' => 'dnd', 'design_json' => ['blocks' => [9]]]);

        $restored = $service->restoreVersion($template->fresh(), $v1, $this->manager);

        $this->assertNull($restored->design_json);
        $this->assertSame('html', $restored->editor_type);
    }

    public function test_template_test_send_resolves_merge_tags(): void
    {
        Bus::fake();
        $template = Template::factory()->create(['html_content' => '<p>Bonjour {{ contact.PRENOM }}</p>', 'subject' => 'Salut']);

        $this->actingAs($this->manager)->postJson("/api/v1/templates/{$template->uuid}/test-send", ['emails' => ['essai@exemple.dz']])
            ->assertStatus(202);

        Bus::assertDispatched(SendEmailJob::class);
        $message = Message::query()->where('subject', '[TEST] Salut')->firstOrFail();
        $this->assertStringNotContainsString('{{ contact.PRENOM }}', (string) $message->html_body);
        $this->assertTrue((bool) $message->is_test);
    }
}
