<?php

namespace Tests\Feature\Modules\Templates;

use App\Domain\Enums\RoleName;
use App\Modules\Campaigns\Events\CampaignDesignSaved;
use App\Modules\Identity\Models\User;
use App\Modules\Templates\Events\TemplateSaved;
use App\Modules\Templates\Jobs\RenderThumbnailJob;
use App\Modules\Templates\Models\Template;
use App\Modules\Templates\Services\TemplateThumbnailService;
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

    public function test_unknown_models_and_saved_sections_are_ignored(): void
    {
        $service = app(TemplateThumbnailService::class);

        $this->assertNull($service->renderFor('template', 999999));
        $this->assertNull($service->renderFor('saved_section', 1));
    }
}
