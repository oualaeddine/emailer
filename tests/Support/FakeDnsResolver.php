<?php

namespace Tests\Support;

use App\Modules\Senders\Contracts\DnsResolverContract;

class FakeDnsResolver implements DnsResolverContract
{
    /** @var array<string, list<string>> */
    public static array $txt = [];

    /** @var array<string, list<string>> */
    public static array $cname = [];

    public function txt(string $host): array
    {
        return self::$txt[strtolower($host)] ?? [];
    }

    public function cname(string $host): array
    {
        return self::$cname[strtolower($host)] ?? [];
    }

    public static function reset(): void
    {
        self::$txt = [];
        self::$cname = [];
    }
}
