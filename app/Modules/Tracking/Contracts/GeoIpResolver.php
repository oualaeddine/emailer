<?php

namespace App\Modules\Tracking\Contracts;

/**
 * docs/43-brevo-parity-plan.md §43.6.5 — IP → country / city, resolved
 * locally (MaxMind GeoLite2). Tests bind a fake implementation.
 */
interface GeoIpResolver
{
    /**
     * @return array{country: string|null, city: string|null}|null null when unknown / no database
     */
    public function lookup(?string $ip): ?array;
}
