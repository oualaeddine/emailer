<?php

namespace Tests\Feature\Modules\Templates;

use App\Domain\Enums\RoleName;
use App\Modules\DeliveryEngine\Jobs\SendEmailJob;
use App\Modules\Identity\Models\User;
use App\Modules\Templates\Models\Template;
use App\Modules\Templates\Services\HostResolver;
use App\Modules\Tracking\Models\Message;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;
use ZipArchive;

/**
 * docs/45-brevo-api-contracts.md §45.4.4 / §45.8 slot C — WP-44.
 */
class TemplateV2Test extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->manager = User::factory()->withRole(RoleName::MarketingManager)->create();
    }

    /**
     * @param  array<string, list<string>>  $map host => ips
     */
    private function fakeDns(array $map): void
    {
        $this->app->instance(HostResolver::class, new class($map) extends HostResolver
        {
            /** @param array<string, list<string>> $map */
            public function __construct(private array $map) {}

            public function resolve(string $host): array
            {
                return $this->map[$host] ?? parent::resolve($host);
            }
        });
    }

    public function test_resources_expose_v2_fields_and_detail_has_design(): void
    {
        $template = Template::factory()->create(['editor_type' => 'dnd', 'design_json' => ['rows' => []], 'subject' => 'Salut']);

        $detail = $this->actingAs($this->manager)->getJson("/api/v1/templates/{$template->uuid}");
        $detail->assertOk()
            ->assertJsonPath('data.editor_type', 'dnd')
            ->assertJsonPath('data.scope', 'marketing')
            ->assertJsonPath('data.is_gallery', false)
            ->assertJsonPath('data.subject', 'Salut')
            ->assertJsonPath('data.design_json.rows', [])
            ->assertJsonPath('data.versions_count', 0);

        $list = $this->actingAs($this->manager)->getJson('/api/v1/templates?per_page=10&is_gallery=0&scope=marketing');
        $list->assertOk()->assertJsonPath('data.0.html_content', null)->assertJsonPath('meta.total', 1);
    }

    public function test_list_filters_gallery_and_mine(): void
    {
        Template::factory()->create(['name' => 'Mine']);
        Template::factory()->gallery()->create(['name' => 'Gal']);

        $this->actingAs($this->manager)->getJson('/api/v1/templates?is_gallery=1&per_page=10')
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Gal');
        $this->actingAs($this->manager)->getJson('/api/v1/templates?is_gallery=0&scope=marketing&per_page=10')
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Mine');
        $this->actingAs($this->manager)->getJson('/api/v1/templates?sort=bogus&per_page=10')->assertStatus(422);
    }

    public function test_migration_backfills_editor_type_html(): void
    {
        $this->assertTrue(Schema::hasColumns('templates', ['editor_type', 'design_json', 'folder_id', 'scope', 'is_gallery', 'gallery_category', 'subject']));

        $id = \DB::table('templates')->insertGetId([
            'uuid' => (string) \Str::uuid(), 'name' => 'Legacy', 'html_content' => '<p>x</p>', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->assertSame('html', \DB::table('templates')->where('id', $id)->value('editor_type'));
    }

    public function test_duplicating_a_gallery_template_creates_a_non_gallery_copy(): void
    {
        $gallery = Template::factory()->gallery('promotion')->create(['name' => 'Soldes', 'html_content' => '<p>soldes</p>']);

        $response = $this->actingAs($this->manager)->postJson("/api/v1/templates/{$gallery->uuid}/duplicate", ['scope' => 'transactional']);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Copie de Soldes')
            ->assertJsonPath('data.is_gallery', false)
            ->assertJsonPath('data.gallery_category', null)
            ->assertJsonPath('data.scope', 'transactional')
            ->assertJsonPath('data.html_content', '<p>soldes</p>')
            ->assertJsonPath('data.versions_count', 1);
        $this->assertNotSame($gallery->uuid, $response->json('data.id'));
    }

    public function test_test_send_validates_between_one_and_five_addresses(): void
    {
        Bus::fake();
        $template = Template::factory()->create();
        $url = "/api/v1/templates/{$template->uuid}/test-send";

        $this->actingAs($this->manager)->postJson($url, ['emails' => []])->assertStatus(422);
        $this->actingAs($this->manager)->postJson($url, ['emails' => array_map(fn (int $i) => "t{$i}@exemple.dz", range(1, 6))])->assertStatus(422);
        $this->actingAs($this->manager)->postJson($url, ['emails' => ['pas-un-email']])->assertStatus(422);

        $ok = $this->actingAs($this->manager)->postJson($url, ['emails' => ['a@exemple.dz', 'b@exemple.dz'], 'subject' => 'Essai']);
        $ok->assertStatus(202)->assertJsonPath('data.queued', 2);
        Bus::assertDispatched(SendEmailJob::class, 2);
        $this->assertSame(2, Message::query()->where('subject', '[TEST] Essai')->count());
    }

    public function test_operator_cannot_test_send_or_import(): void
    {
        $operator = User::factory()->withRole(RoleName::MarketingOperator)->create();
        $template = Template::factory()->create();

        $this->actingAs($operator)->postJson("/api/v1/templates/{$template->uuid}/test-send", ['emails' => ['a@exemple.dz']])->assertForbidden();
        $this->actingAs($operator)->postJson('/api/v1/templates/import-from-url', ['url' => 'https://exemple.dz'])->assertForbidden();
    }

    public function test_import_from_url_returns_html_and_strips_scripts(): void
    {
        $this->fakeDns(['exemple.dz' => ['93.184.216.34']]);
        Http::fake(['https://exemple.dz/*' => Http::response(
            '<html><head><title>Ma news</title><script>alert(1)</script></head><body><p>Bonjour</p></body></html>',
            200,
            ['Content-Type' => 'text/html; charset=utf-8'],
        )]);

        $this->actingAs($this->manager)->postJson('/api/v1/templates/import-from-url', ['url' => 'https://exemple.dz/news.html'])
            ->assertOk()
            ->assertJsonPath('data.title', 'Ma news')
            ->assertJsonPath('data.warnings.0', 'Les scripts ont été supprimés.')
            ->assertJsonMissing(['html' => 'alert(1)']);
    }

    public function test_import_from_url_refuses_private_hosts_non_html_and_bad_schemes(): void
    {
        $this->fakeDns(['interne.exemple.dz' => ['10.0.0.5'], 'meta.exemple.dz' => ['169.254.169.254'], 'v6.exemple.dz' => ['::1'], 'exemple.dz' => ['93.184.216.34']]);
        Http::fake(['https://exemple.dz/*' => Http::response('{"a":1}', 200, ['Content-Type' => 'application/json'])]);

        foreach (['https://interne.exemple.dz/x', 'http://meta.exemple.dz/', 'http://v6.exemple.dz/', 'http://127.0.0.1/', 'ftp://exemple.dz/a.html', 'https://exemple.dz/data.json'] as $url) {
            $this->actingAs($this->manager)->postJson('/api/v1/templates/import-from-url', ['url' => $url])
                ->assertStatus(422, $url)->assertJsonValidationErrors('url');
        }
        Http::assertSentCount(1);
    }

    public function test_import_from_url_rechecks_each_redirect(): void
    {
        $this->fakeDns(['exemple.dz' => ['93.184.216.34'], 'interne.exemple.dz' => ['192.168.1.10']]);
        Http::fake(['https://exemple.dz/*' => Http::response('', 302, ['Location' => 'http://interne.exemple.dz/secret'])]);

        $this->actingAs($this->manager)->postJson('/api/v1/templates/import-from-url', ['url' => 'https://exemple.dz/go'])
            ->assertStatus(422)->assertJsonValidationErrors('url');
        Http::assertSentCount(1);
    }

    public function test_import_from_url_refuses_more_than_three_redirects(): void
    {
        $this->fakeDns(['exemple.dz' => ['93.184.216.34']]);
        Http::fake(['https://exemple.dz/*' => Http::response('', 302, ['Location' => '/again'])]);

        $this->actingAs($this->manager)->postJson('/api/v1/templates/import-from-url', ['url' => 'https://exemple.dz/go'])
            ->assertStatus(422);
        Http::assertSentCount(4);
    }

    public function test_import_from_url_refuses_oversized_bodies(): void
    {
        $this->fakeDns(['exemple.dz' => ['93.184.216.34']]);
        Http::fake(['https://exemple.dz/*' => Http::response(str_repeat('a', 2 * 1024 * 1024 + 1), 200, ['Content-Type' => 'text/html'])]);

        $this->actingAs($this->manager)->postJson('/api/v1/templates/import-from-url', ['url' => 'https://exemple.dz/big'])
            ->assertStatus(422);
    }

    public function test_import_zip_stores_images_and_rewrites_src(): void
    {
        Storage::fake('public');
        $path = tempnam(sys_get_temp_dir(), 'zip');
        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::OVERWRITE);
        $zip->addFromString('news/index.html', '<html><head><title>Zip</title></head><body><img src="images/logo.png"><script>x()</script></body></html>');
        $zip->addFromString('news/images/logo.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='));
        $zip->close();

        $response = $this->actingAs($this->manager)->post('/api/v1/templates/import-zip', [
            'file' => new UploadedFile($path, 'news.zip', 'application/zip', null, true),
        ], ['Accept' => 'application/json']);

        $response->assertOk()->assertJsonPath('data.title', 'Zip')->assertJsonPath('data.assets.0.filename', 'logo.png');
        $assetUrl = $response->json('data.assets.0.url');
        $this->assertStringContainsString('/imports/', $assetUrl);
        $this->assertStringContainsString('src="'.$assetUrl.'"', $response->json('data.html'));
        $this->assertStringNotContainsString('<script', $response->json('data.html'));
        $this->assertCount(1, Storage::disk('public')->allFiles());
    }

    public function test_import_zip_requires_exactly_one_html_file(): void
    {
        Storage::fake('public');
        $path = tempnam(sys_get_temp_dir(), 'zip');
        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::OVERWRITE);
        $zip->addFromString('logo.png', 'PNG');
        $zip->close();

        $this->actingAs($this->manager)->post('/api/v1/templates/import-zip', [
            'file' => new UploadedFile($path, 'n.zip', 'application/zip', null, true),
        ], ['Accept' => 'application/json'])->assertStatus(422)->assertJsonValidationErrors('file');
    }
}
