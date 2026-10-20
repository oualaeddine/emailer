<?php

namespace Tests\Feature\Modules\Templates;

use App\Domain\Enums\RoleName;
use App\Modules\Campaigns\Events\CampaignDesignSaved;
use App\Modules\Identity\Models\User;
use App\Modules\Templates\Events\TemplateSaved;
use App\Modules\Templates\Jobs\RenderThumbnailJob;
use App\Modules\Templates\Models\Template;
use App\Modules\Templates\Sections\Http\Resources\SavedSectionResource;
use App\Modules\Templates\Sections\Models\SavedSection;
use App\Modules\Templates\Sections\Services\SavedSectionService;
use App\Modules\Templates\Services\MjmlCompiler;
use App\Modules\Templates\Services\CompiledMjml;
use App\Modules\Templates\Services\TemplateThumbnailService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * docs/45-brevo-api-contracts.md §45.6.2 / §45.9 — thumbnails (WP-62).
 */
class TemplateThumbnailTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->manager = User::factory()->withRole(RoleName::MarketingManager)->create();
    }

    public function test_saving_a_template_dispatches_the_thumbnail_job(): void
    {
        config(['templates.thumbnails' => true]);
        Queue::fake();

        $this->actingAs($this->manager)->postJson('/api/v1/templates', ['name' => 'T', 'html_content' => '<p>x</p>'])->assertCreated();

        Queue::assertPushed(RenderThumbnailJob::class, fn (RenderThumbnailJob $job) => $job->modelType === 'template');
    }

    public function test_campaign_design_saved_dispatches_the_thumbnail_job(): void
    {
        config(['templates.thumbnails' => true]);
        Queue::fake();

        CampaignDesignSaved::dispatch(42, 'dnd');

        Queue::assertPushed(RenderThumbnailJob::class, fn (RenderThumbnailJob $job) => $job->modelType === 'campaign' && $job->modelId === 42);
    }

    public function test_listener_is_disabled_under_tests_by_default(): void
    {
        Queue::fake();

        TemplateSaved::dispatch(Template::factory()->create()->id);

        Queue::assertNothingPushed();
    }

    public function test_thumbnail_endpoint_returns_202_and_queues_the_job(): void
    {
        Queue::fake();
        $template = Template::factory()->create();

        $this->actingAs($this->manager)->postJson("/api/v1/templates/{$template->uuid}/thumbnail")
            ->assertStatus(202)->assertJsonPath('data.id', $template->uuid);

        Queue::assertPushed(RenderThumbnailJob::class, fn (RenderThumbnailJob $job) => $job->modelId === $template->id);

        $operator = User::factory()->withRole(RoleName::MarketingOperator)->create();
        $this->actingAs($operator)->postJson("/api/v1/templates/{$template->uuid}/thumbnail")->assertForbidden();
    }

    public function test_job_stores_the_png_on_the_public_disk(): void
    {
        Storage::fake('public');
        $template = Template::factory()->create();
        $this->app->instance(TemplateThumbnailService::class, new class extends TemplateThumbnailService
        {
            public function renderHtml(string $html): ?string
            {
                return "\x89PNG-fake";
            }
        });

        (new RenderThumbnailJob('template', $template->id))->handle(app(TemplateThumbnailService::class));

        $path = "thumbnails/templates/{$template->uuid}.png";
        $this->assertSame($path, $template->fresh()->thumbnail_path);
        Storage::disk('public')->assertExists($path);

        $this->actingAs($this->manager)->getJson("/api/v1/templates/{$template->uuid}")
            ->assertJsonPath('data.thumbnail_url', Storage::disk('public')->url($path));
    }

    public function test_job_is_a_graceful_no_op_when_the_renderer_is_unavailable(): void
    {
        Storage::fake('public');
        config(['templates.node_binary' => '/nonexistent/node']);
        $template = Template::factory()->create();

        (new RenderThumbnailJob('template', $template->id))->handle(app(TemplateThumbnailService::class));

        $this->assertNull($template->fresh()->thumbnail_path);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_creating_a_saved_section_dispatches_the_thumbnail_job_but_renaming_does_not(): void
    {
        config(['templates.thumbnails' => true]);
        Bus::fake();

        $service = app(SavedSectionService::class);
        $section = $service->create('S', '<mj-section><mj-column><mj-text>Hi</mj-text></mj-column></mj-section>', [], $this->manager);

        Bus::assertDispatched(RenderThumbnailJob::class, fn (RenderThumbnailJob $job) => $job->modelType === 'saved_section' && $job->modelId === $section->id);

        Bus::fake();
        $service->rename($section, 'Renamed');
        Bus::assertNotDispatched(RenderThumbnailJob::class);
    }

    public function test_job_renders_a_saved_section_and_the_resource_exposes_the_url(): void
    {
        Storage::fake('public');
        $section = SavedSection::factory()->create();
        $this->assertNull((new SavedSectionResource($section))->toArray(new Request)['thumbnail_url']);

        $this->app->instance(MjmlCompiler::class, new class(app(\App\Modules\Templates\Services\HtmlSanitizer::class)) extends MjmlCompiler
        {
            public function compile(string $mjml): CompiledMjml
            {
                return new CompiledMjml('<html><body>ok</body></html>', []);
            }
        });
        $this->app->instance(TemplateThumbnailService::class, new class extends TemplateThumbnailService
        {
            public function renderHtml(string $html): ?string
            {
                return "\x89PNG-fake";
            }
        });

        (new RenderThumbnailJob('saved_section', $section->id))->handle(app(TemplateThumbnailService::class));

        $path = "thumbnails/saved_sections/{$section->uuid}.png";
        $this->assertSame($path, $section->fresh()->thumbnail_path);
        Storage::disk('public')->assertExists($path);
        $this->assertSame(Storage::disk('public')->url($path), (new SavedSectionResource($section->fresh()))->toArray(new Request)['thumbnail_url']);
    }

    public function test_unknown_models_and_saved_sections_are_ignored(): void
    {
        $service = app(TemplateThumbnailService::class);

        $this->assertNull($service->renderFor('template', 999999));
        $this->assertNull($service->renderFor('saved_section', 999999));
    }

    public function test_html_goes_to_the_renderer_on_stdin_not_as_an_argument(): void
    {
        if (! is_dir(base_path('node_modules/playwright'))) {
            $this->markTestSkipped('playwright not installed');
        }
        $service = new class extends TemplateThumbnailService
        {
            public array $command = [];

            public string $stdin = '';

            protected function execute(array $command, string $stdin = ''): bool
            {
                $this->command = $command;
                $this->stdin = $stdin;
                file_put_contents(end($command), 'PNGDATA');

                return true;
            }
        };

        $html = '<img src="http://169.254.169.254/latest"><p>$(rm -rf /)</p>';
        $this->assertSame('PNGDATA', $service->renderHtml($html));
        $this->assertSame($html, $service->stdin);
        $this->assertSame('-', $service->command[2]);
        $this->assertNotContains($html, $service->command);
    }

    public function test_renderer_url_policy_blocks_private_hosts_and_allows_public_https(): void
    {
        $node = (new \Symfony\Component\Process\ExecutableFinder)->find('node');
        if ($node === null) {
            $this->markTestSkipped('node not available');
        }
        $script = <<<'JS'
        import { isAllowedUrl } from process.argv[1];
        const resolver = async (h) => h === 'evil.test' ? [{ address: '10.0.0.5' }] : [{ address: '93.184.216.34' }];
        const urls = ['data:image/png;base64,AA', 'https://example.com/a.png', 'http://example.com/a.png', 'https://127.0.0.1/', 'https://169.254.169.254/latest/meta-data', 'https://[::1]/', 'https://evil.test/x', 'https://localhost/', 'file:///etc/passwd'];
        const out = [];
        for (const u of urls) out.push(await isAllowedUrl(u, resolver));
        console.log(JSON.stringify(out));
        JS;
        $script = str_replace('process.argv[1]', json_encode('file://'.base_path('scripts/thumbnail.mjs')), $script);
        $process = new \Symfony\Component\Process\Process([$node, '--input-type=module', '-e', $script]);
        $process->run();

        $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
        $this->assertSame([true, true, false, false, false, false, false, false, false], json_decode(trim($process->getOutput()), true));
    }
}
