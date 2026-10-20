<?php

namespace Tests\Feature\Modules\Templates;

use App\Domain\Enums\RoleName;
use App\Modules\DeliveryEngine\Jobs\SendEmailJob;
use App\Modules\Identity\Models\User;
use App\Modules\Recipients\Models\Recipient;
use App\Modules\Templates\Models\Template;
use App\Modules\Templates\Services\HostResolver;
use App\Modules\Templates\Services\HtmlSanitizer;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

class TemplateSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    /** @return array<string, array{string, string}> */
    public static function vectors(): array
    {
        return [
            'onclick double quotes' => ['<a href="https://x.test" onclick="steal()">x</a>', 'onclick'],
            'onerror single quotes' => ["<img src='a.png' onerror='steal()'>", 'onerror'],
            'onload unquoted' => ['<body onload=steal()>hi</body>', 'onload'],
            'slash separator' => ['<img/src=x/onerror=steal()>', 'onerror'],
            'javascript href' => ['<a href="javascript:steal()">x</a>', 'javascript:'],
            'entity obfuscated javascript' => ['<a href="jav&#x09;ascript:steal()">x</a>', 'script:'],
            'vbscript' => ['<a href=\'vbscript:msgbox\'>x</a>', 'vbscript'],
            'data html src' => ['<img src="data:text/html;base64,PHNjcmlwdD4=">', 'data:text/html'],
            'form action' => ['<form action="javascript:steal()"><input></form>', 'javascript:'],
            'iframe' => ['<iframe src="https://evil.test"></iframe>', '<iframe'],
            'object' => ['<object data="x.swf"></object>', '<object'],
            'embed' => ['<embed src="x.swf">', '<embed'],
            'base' => ['<base href="https://evil.test/">', '<base'],
            'form' => ['<form method="post" action="https://evil.test"><input name="a"></form>', '<form'],
            'meta refresh' => ['<meta http-equiv="refresh" content="0;url=https://evil.test">', 'http-equiv'],
        ];
    }

    /** @dataProvider vectors */
    public function test_sanitizer_strips_vector(string $html, string $needle): void
    {
        $result = (new HtmlSanitizer)->clean('<p>keep</p>'.$html);

        $this->assertStringNotContainsStringIgnoringCase($needle, $result['html']);
        $this->assertStringContainsString('<p>keep</p>', $result['html']);
        $this->assertContains(HtmlSanitizer::ACTIVE_CONTENT_WARNING, $result['warnings']);
    }

    public function test_sanitizer_keeps_ordinary_markup(): void
    {
        $html = '<html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width"><title>T</title></head>'
            .'<body><a href="https://ok.test/x?a=1" title="online">go</a><img src="https://ok.test/a.png"><a href="mailto:a@b.c">m</a></body></html>';

        $result = (new HtmlSanitizer)->clean($html);

        $this->assertSame($html, $result['html']);
        $this->assertSame([], $result['warnings']);
    }

    public function test_import_from_url_rejects_declared_content_length_over_cap(): void
    {
        $this->app->instance(HostResolver::class, new class extends HostResolver
        {
            public function resolve(string $host): array
            {
                return ['93.184.216.34'];
            }
        });
        Http::fake(['*' => Http::response('<p>small</p>', 200, ['Content-Type' => 'text/html', 'Content-Length' => (string) (3 * 1024 * 1024)])]);
        $manager = User::factory()->withRole(RoleName::MarketingManager)->create();

        $this->actingAs($manager)->postJson('/api/v1/templates/import-from-url', ['url' => 'https://exemple.dz/big'])
            ->assertStatus(422)->assertJsonPath('errors.url.0', 'Le contenu dépasse la taille maximale de 2 Mo.');
    }

    public function test_test_send_reuses_soft_deleted_contact_and_is_rate_limited(): void
    {
        Bus::fake([SendEmailJob::class]);
        $manager = User::factory()->withRole(RoleName::MarketingManager)->create();
        $template = Template::factory()->create();
        $contact = Recipient::factory()->create(['email' => 'gone@exemple.dz']);
        $contact->delete();

        $this->actingAs($manager)->postJson("/api/v1/templates/{$template->uuid}/test-send", ['emails' => ['gone@exemple.dz']])
            ->assertStatus(202);
        $this->assertTrue(Recipient::withTrashed()->find($contact->id)->trashed());
        $this->assertSame(1, Recipient::withTrashed()->where('email', 'gone@exemple.dz')->count());

        RateLimiter::clear("template-test-send:{$template->id}");
        for ($i = 0; $i < 10; $i++) {
            RateLimiter::hit("template-test-send:{$template->id}", 3600);
        }
        $this->actingAs($manager)->postJson("/api/v1/templates/{$template->uuid}/test-send", ['emails' => ['a@exemple.dz']])
            ->assertStatus(429);
    }

    public function test_zip_import_skips_files_that_are_not_images(): void
    {
        Storage::fake('public');
        $path = tempnam(sys_get_temp_dir(), 'zip');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::OVERWRITE);
        $zip->addFromString('index.html', '<html><body><img src="fake.png"></body></html>');
        $zip->addFromString('fake.png', '<?php echo "pwn";');
        $zip->close();
        $manager = User::factory()->withRole(RoleName::MarketingManager)->create();

        $response = $this->actingAs($manager)->post('/api/v1/templates/import-zip', [
            'file' => new UploadedFile($path, 'site.zip', 'application/zip', null, true),
        ], ['Accept' => 'application/json']);

        $response->assertOk()->assertJsonPath('data.assets', []);
        $this->assertStringContainsString("n'est pas une image valide", $response->json('data.warnings.0'));
        $this->assertSame([], Storage::disk('public')->allFiles());
    }
}
