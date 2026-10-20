<?php

namespace Tests\Feature\Console;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GeoIpUpdateRedactionTest extends TestCase
{
    public function test_download_errors_never_leak_the_licence_key(): void
    {
        $dir = storage_path('framework/testing/geoip-redact-'.uniqid());
        File::ensureDirectoryExists($dir);
        config(['tracking.geoip.database_path' => $dir.'/GeoLite2-City.mmdb', 'tracking.geoip.license_key' => 'sup3r secret&key']);
        Http::fake(fn () => throw new ConnectionException('cURL error 28: timeout for https://download.maxmind.com/?license_key=sup3r+secret%26key&suffix=tar.gz'));

        $this->artisan('geoip:update')
            ->doesntExpectOutputToContain('sup3r')
            ->expectsOutputToContain('GeoLite2 update failed')
            ->assertFailed();

        File::deleteDirectory($dir);
    }
}
