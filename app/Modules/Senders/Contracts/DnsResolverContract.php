<?php

namespace App\Modules\Senders\Contracts;

/** DNS lookups used by VerifyDomainJob (docs/43 §43.4.14). Tests bind FakeDnsResolver. */
interface DnsResolverContract
{
    /**
     * @return list<string> TXT strings (multi-part records already joined)
     */
    public function txt(string $host): array;

    /**
     * @return list<string> CNAME targets
     */
    public function cname(string $host): array;
}
