<?php

namespace App\Modules\Tracking\Support;

use App\Modules\Tracking\Contracts\GeoIpResolver;
use GeoIp2\Database\Reader;
use Throwable;

/**
 * Reads `config('tracking.geoip.database_path')` (GeoLite2-City, refreshed
 * monthly by `geoip:update`). Absent / unreadable database, private IPs and
 * unknown addresses all resolve to null — enrichment never fails a request.
 */
class MaxMindGeoIpResolver implements GeoIpResolver
{
    private ?Reader $reader = null;

    private bool $opened = false;

    public function __construct(private readonly ?string $databasePath = null) {}

    public function lookup(?string $ip): ?array
    {
        if ($ip === null || $ip === '' || filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return null;
        }

        $reader = $this->reader();
        if ($reader === null) {
            return null;
        }

        try {
            $record = $reader->city($ip);
        } catch (Throwable) {
            return null;
        }

        $country = $record->country->isoCode;
        $city = $record->city->name;

        return [
            'country' => is_string($country) && strlen($country) === 2 ? strtoupper($country) : null,
            'city' => is_string($city) ? mb_substr($city, 0, 100) : null,
        ];
    }

    private function reader(): ?Reader
    {
        if (! $this->opened) {
            $this->opened = true;
            $path = $this->databasePath ?? (string) config('tracking.geoip.database_path');
            if ($path !== '' && is_file($path) && is_readable($path)) {
                try {
                    $this->reader = new Reader($path);
                } catch (Throwable) {
                    $this->reader = null;
                }
            }
        }

        return $this->reader;
    }
}
