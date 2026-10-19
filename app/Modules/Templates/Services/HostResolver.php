<?php

namespace App\Modules\Templates\Services;

/**
 * DNS resolution seam for the SSRF guard (docs/45 §45.4.4). Bound in tests to
 * a deterministic fake; production resolves A and AAAA records.
 */
class HostResolver
{
    /**
     * @return list<string> resolved IP addresses (empty when the host does not resolve)
     */
    public function resolve(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return [$host];
        }

        $ips = [];
        foreach (@dns_get_record($host, DNS_A | DNS_AAAA) ?: [] as $record) {
            $ip = $record['ip'] ?? $record['ipv6'] ?? null;
            if (is_string($ip)) {
                $ips[] = $ip;
            }
        }

        return array_values(array_unique($ips));
    }
}
