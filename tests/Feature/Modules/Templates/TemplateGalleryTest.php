<?php

namespace Tests\Feature\Modules\Templates;

use App\Domain\Enums\RoleName;
use App\Modules\Identity\Models\User;
use App\Modules\Templates\Models\Template;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\TemplateGallerySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * docs/45-brevo-api-contracts.md §45.6.2 — gallery (WP-62).
 */
class TemplateGalleryTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->manager = User::factory()->withRole(RoleName::MarketingManager)->create();
    }

    public function test_seeder_creates_twelve_original_designs_and_is_idempotent(): void
    {
        $this->seed(TemplateGallerySeeder::class);
        $this->seed(TemplateGallerySeeder::class);

        $this->assertSame(12, Template::query()->where('is_gallery', true)->count());

        $template = Template::query()->where('is_gallery', true)->where('name', 'Newsletter classique')->firstOrFail();
        $this->assertSame('dnd', $template->editor_type);
        $this->assertSame('newsletter', $template->gallery_category);
        $this->assertSame('grapesjs-mjml', $template->design_json['format']);
        $this->assertStringStartsWith('<mjml', $template->design_json['mjml']);
        $this->assertStringContainsString('<html', $template->html_content);
        $this->assertStringContainsString('@media', $template->html_content);
    }

    public function test_gallery_endpoint_lists_templates_with_category_counts(): void
    {
        $this->seed(TemplateGallerySeeder::class);
        Template::factory()->create(['name' => 'Perso']);

        $response = $this->actingAs($this->manager)->getJson('/api/v1/templates/gallery');
        $response->assertOk()->assertJsonCount(12, 'data')->assertJsonPath('data.0.is_gallery', true);

        $counts = collect($response->json('meta.categories'))->pluck('count', 'key');
        $this->assertSame(2, $counts['newsletter']);
        $this->assertSame(12, $counts->sum());

        $filtered = $this->actingAs($this->manager)->getJson('/api/v1/templates/gallery?category=newsletter');
        $filtered->assertOk()->assertJsonCount(2, 'data');
        $this->assertSame(12, collect($filtered->json('meta.categories'))->sum('count'));
    }

    public function test_gallery_is_readable_with_campaigns_create_only(): void
    {
        $this->seed(TemplateGallerySeeder::class);
        $user = User::factory()->withRole(RoleName::MarketingOperator)->create();

        $this->actingAs($user)->getJson('/api/v1/templates/gallery')->assertOk();
    }

    public function test_gallery_templates_are_read_only(): void
    {
        $template = Template::factory()->gallery()->create();

        $this->actingAs($this->manager)->patchJson("/api/v1/templates/{$template->uuid}", ['name' => 'Autre'])->assertStatus(409);
        $this->actingAs($this->manager)->patchJson("/api/v1/templates/{$template->uuid}", ['folder_id' => null])->assertStatus(409);
        $this->actingAs($this->manager)->deleteJson("/api/v1/templates/{$template->uuid}")->assertStatus(409);
        $this->actingAs($this->manager)->postJson("/api/v1/templates/{$template->uuid}/archive")->assertStatus(409);
        $this->assertNotNull($template->fresh());
    }

    public function test_gallery_template_can_be_duplicated_into_my_templates(): void
    {
        $this->seed(TemplateGallerySeeder::class);
        $source = Template::query()->where('is_gallery', true)->firstOrFail();

        $copy = $this->actingAs($this->manager)->postJson("/api/v1/templates/{$source->uuid}/duplicate", ['name' => 'Ma copie']);
        $copy->assertCreated()
            ->assertJsonPath('data.is_gallery', false)
            ->assertJsonPath('data.gallery_category', null)
            ->assertJsonPath('data.name', 'Ma copie')
            ->assertJsonPath('data.editor_type', 'dnd');
        $this->assertNotNull($copy->json('data.design_json'));

        $this->actingAs($this->manager)->getJson('/api/v1/templates?is_gallery=0&scope=marketing')->assertJsonCount(1, 'data');
    }

    public function test_category_counts_use_portable_aliases(): void
    {
        Template::factory()->count(2)->create(['is_gallery' => true, 'gallery_category' => 'newsletter']);
        Template::factory()->create(['is_gallery' => true, 'gallery_category' => 'promo']);

        $counts = app(\App\Modules\Templates\Services\TemplateGalleryService::class)->categoryCounts();

        $this->assertSame([['key' => 'newsletter', 'count' => 2], ['key' => 'promo', 'count' => 1]], $counts);
    }
}
