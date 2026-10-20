<?php

namespace Tests\Feature\Modules\Tracking\Fakes;

use App\Modules\Tracking\Contracts\GeoIpResolver;

class FakeGeoIpResolver implements GeoIpResolver
{
    /** @var list<string|null> */
    public array $lookups = [];

    /**
     * @param  array<string, array{country: string|null, city: string|null}>  $map
     */
    public function __construct(private readonly array $map = []) {}

    public function lookup(?string $ip): ?array
    {
        $this->lookups[] = $ip;

        return $ip !== null ? ($this->map[$ip] ?? null) : null;
    }
}
