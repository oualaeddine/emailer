<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use PharData;
use RecursiveIteratorIterator;
use Throwable;

/**
 * docs/43-brevo-parity-plan.md §43.6.5 / doc 45 §45.6.4 (WP-70) — downloads
 * the MaxMind GeoLite2-City database used to resolve open / click countries
 * (scheduled monthly in routes/console.php). Requires a (free) MaxMind
 * licence key in `MAXMIND_LICENSE_KEY` (`tracking.geoip.license_key`);
 * without one the command is a no-op with a warning, and lookups keep
 * returning null.
 */
class GeoIpUpdateCommand extends Command
{
    protected $signature = 'geoip:update';

    protected $description = 'Download the MaxMind GeoLite2-City database used for open/click geolocation';

    public function handle(): int
    {
        $licenseKey = (string) config('tracking.geoip.license_key', '');
        if (trim($licenseKey) === '') {
            $this->warn('MAXMIND_LICENSE_KEY is not configured: GeoLite2 database not updated (geolocation stays disabled).');

            return self::SUCCESS;
        }

        $target = (string) config('tracking.geoip.database_path');
        $edition = (string) config('tracking.geoip.edition', 'GeoLite2-City');
        $workDir = storage_path('app/geoip/tmp-'.Str::random(8));
        File::ensureDirectoryExists($workDir);
        $archive = $workDir.'/'.$edition.'.tar.gz';

        try {
            $response = Http::timeout((int) config('tracking.geoip.timeout', 120))
                ->withOptions(['sink' => $archive])
                ->get((string) config('tracking.geoip.download_url'), [
                    'edition_id' => $edition,
                    'license_key' => $licenseKey,
                    'suffix' => 'tar.gz',
                ]);

            if (! $response->successful()) {
                $this->error("GeoLite2 download failed (HTTP {$response->status()}).");

                return self::FAILURE;
            }

            if (! is_file($archive) || filesize($archive) === 0) {
                File::put($archive, $response->body());
            }

            $mmdb = $this->extractDatabase($archive, $workDir, $edition);
            if ($mmdb === null) {
                $this->error('GeoLite2 archive did not contain a .mmdb database.');

                return self::FAILURE;
            }

            File::ensureDirectoryExists(dirname($target));
            File::copy($mmdb, $target.'.new');
            File::move($target.'.new', $target);

            $this->info("GeoLite2 database updated: {$target}");

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('GeoLite2 update failed: '.str_replace([$licenseKey, rawurlencode($licenseKey), urlencode($licenseKey)], '***', $e->getMessage()));

            return self::FAILURE;
        } finally {
            File::deleteDirectory($workDir);
        }
    }

    private function extractDatabase(string $archive, string $workDir, string $edition): ?string
    {
        $tar = new PharData($archive);
        $tar->extractTo($workDir.'/x', null, true);

        $iterator = new RecursiveIteratorIterator(new \RecursiveDirectoryIterator($workDir.'/x', \FilesystemIterator::SKIP_DOTS));
        $fallback = null;
        foreach ($iterator as $file) {
            if (! $file->isFile() || strtolower($file->getExtension()) !== 'mmdb') {
                continue;
            }
            if ($file->getBasename('.mmdb') === $edition) {
                return $file->getPathname();
            }
            $fallback ??= $file->getPathname();
        }

        return $fallback;
    }
}
