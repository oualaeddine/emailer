<?php

namespace App\Modules\Senders\Support;

use App\Modules\Senders\Contracts\DnsResolverContract;

class SystemDnsResolver implements DnsResolverContract
{
    public function txt(string $host): array
    {
        $out = [];
        foreach (@dns_get_record($host, DNS_TXT) ?: [] as $record) {
            $out[] = (string) ($record['txt'] ?? implode('', $record['entries'] ?? []));
        }

        return $out;
    }

    public function cname(string $host): array
    {
        $out = [];
        foreach (@dns_get_record($host, DNS_CNAME) ?: [] as $record) {
            if (isset($record['target'])) {
                $out[] = (string) $record['target'];
            }
        }

        return $out;
    }
}
