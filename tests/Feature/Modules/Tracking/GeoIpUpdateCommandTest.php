<?php

namespace Tests\Feature\Modules\Tracking;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use PharData;
use Tests\TestCase;

/**
 * doc 45 §45.6.4 (WP-70) — `geoip:update`.
 */
class GeoIpUpdateCommandTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = storage_path('framework/testing/geoip-'.uniqid());
        File::ensureDirectoryExists($this->dir);
        config(['tracking.geoip.database_path' => $this->dir.'/GeoLite2-City.mmdb']);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dir);
        parent::tearDown();
    }

    public function test_it_is_a_no_op_with_a_warning_without_a_licence_key(): void
    {
        config(['tracking.geoip.license_key' => null]);
        Http::fake();

        $this->artisan('geoip:update')
            ->expectsOutputToContain('MAXMIND_LICENSE_KEY is not configured')
            ->assertSuccessful();

        Http::assertNothingSent();
        $this->assertFileDoesNotExist($this->dir.'/GeoLite2-City.mmdb');
    }

    public function test_it_downloads_and_installs_the_database_with_a_licence_key(): void
    {
        config(['tracking.geoip.license_key' => 'secret-key']);

        $build = $this->dir.'/build';
        File::ensureDirectoryExists($build.'/GeoLite2-City_20261020');
        File::put($build.'/GeoLite2-City_20261020/GeoLite2-City.mmdb', 'fake-mmdb-bytes');
        $tar = new PharData($this->dir.'/archive.tar');
        $tar->buildFromDirectory($build);
        $tar->compress(\Phar::GZ);
        $gz = File::get($this->dir.'/archive.tar.gz');

        Http::fake(['download.maxmind.com/*' => Http::response($gz, 200)]);

        $this->artisan('geoip:update')->assertSuccessful();

        Http::assertSent(fn ($request): bool => str_contains($request->url(), 'license_key=secret-key')
            && str_contains($request->url(), 'edition_id=GeoLite2-City'));
        $this->assertSame('fake-mmdb-bytes', File::get($this->dir.'/GeoLite2-City.mmdb'));
    }
}
