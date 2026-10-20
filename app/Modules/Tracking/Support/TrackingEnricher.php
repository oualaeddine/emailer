<?php

namespace App\Modules\Tracking\Support;

use App\Modules\Tracking\Contracts\GeoIpResolver;

/**
 * docs/43-brevo-parity-plan.md §43.6.5 — turns a request context into the
 * `message_events` enrichment columns (user agent, device type, e-mail
 * client, GeoIP country / city, Apple MPP proxy-open flag).
 */
class TrackingEnricher
{
    public function __construct(
        private readonly UserAgentParser $agents,
        private readonly GeoIpResolver $geoIp,
        private readonly AppleMppDetector $mpp,
    ) {}

    /**
     * @return array{user_agent: string|null, ip: string|null, device_type: string, email_client: string|null, os: string|null, ip_country: string|null, ip_city: string|null, is_proxy_open: bool}
     */
    public function enrich(?TrackingContext $context, bool $detectProxy = false): array
    {
        $userAgent = $context?->userAgent !== null ? mb_substr(trim($context->userAgent), 0, 512) : null;
        $ip = $context?->ip !== null && filter_var($context->ip, FILTER_VALIDATE_IP) !== false ? $context->ip : null;

        $agent = $this->agents->parse($userAgent);
        $geo = $this->geoIp->lookup($ip);
        $isProxy = $detectProxy && $this->mpp->isProxyOpen($userAgent, $ip);

        $country = $geo['country'] ?? null;

        return [
            'user_agent' => $userAgent === '' ? null : $userAgent,
            'ip' => $ip,
            'device_type' => $agent['device_type'],
            'email_client' => $isProxy && $agent['email_client'] === null ? 'Apple Mail' : $agent['email_client'],
            'os' => $agent['os'],
            'ip_country' => is_string($country) && strlen($country) === 2 ? strtoupper($country) : null,
            'ip_city' => isset($geo['city']) && is_string($geo['city']) ? mb_substr($geo['city'], 0, 100) : null,
            'is_proxy_open' => $isProxy,
        ];
    }
}
