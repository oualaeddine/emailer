<?php

namespace App\Modules\Tracking\Support;

use Symfony\Component\HttpFoundation\IpUtils;

/**
 * docs/43-brevo-parity-plan.md §43.6.5 — flags Apple Mail Privacy
 * Protection proxy opens (`message_events.is_proxy_open`). The proxy
 * pre-fetches every remote image when the message is delivered to Apple
 * Mail, with a bare `Mozilla/5.0` user agent from Apple-owned egress ranges,
 * so the hit says nothing about a human opening the message. Heuristics
 * (any one is enough, all configurable in `config/tracking.php`):
 * - the user agent is exactly one of `apple_mpp.exact_user_agents`;
 * - the user agent contains one of `apple_mpp.user_agent_patterns`;
 * - the IP falls in one of `apple_mpp.cidrs` (Apple's 17.0.0.0/8 by default).
 */
class AppleMppDetector
{
    public function isProxyOpen(?string $userAgent, ?string $ip): bool
    {
        $ua = trim((string) $userAgent);
        $lower = mb_strtolower($ua);

        foreach ((array) config('tracking.apple_mpp.exact_user_agents', []) as $exact) {
            if ($ua !== '' && $lower === mb_strtolower((string) $exact)) {
                return true;
            }
        }

        foreach ((array) config('tracking.apple_mpp.user_agent_patterns', []) as $pattern) {
            if ($pattern !== '' && str_contains($lower, mb_strtolower((string) $pattern))) {
                return true;
            }
        }

        $cidrs = array_values(array_filter((array) config('tracking.apple_mpp.cidrs', [])));
        if ($ip !== null && $ip !== '' && $cidrs !== [] && filter_var($ip, FILTER_VALIDATE_IP) !== false) {
            return IpUtils::checkIp($ip, $cidrs);
        }

        return false;
    }

    /**
     * Whether an open may skip bot filtering as an Apple MPP proxy fetch
     * (stricter than {@see isProxyOpen()}): the user agent carries the
     * explicit proxy marker (`apple_mpp.user_agent_patterns`), or it is the
     * proxy's bare user agent (`apple_mpp.exact_user_agents`) AND the IP is
     * in Apple's egress ranges (`apple_mpp.cidrs`). Anything else goes
     * through the regular bot filter.
     */
    public function bypassesBotFilter(?string $userAgent, ?string $ip): bool
    {
        $ua = trim((string) $userAgent);
        if ($ua === '') {
            return false;
        }
        $lower = mb_strtolower($ua);

        foreach ((array) config('tracking.apple_mpp.user_agent_patterns', []) as $pattern) {
            if ($pattern !== '' && str_contains($lower, mb_strtolower((string) $pattern))) {
                return true;
            }
        }

        $exact = false;
        foreach ((array) config('tracking.apple_mpp.exact_user_agents', []) as $candidate) {
            $exact = $exact || $lower === mb_strtolower((string) $candidate);
        }

        $cidrs = array_values(array_filter((array) config('tracking.apple_mpp.cidrs', [])));

        return $exact && $ip !== null && $ip !== '' && $cidrs !== []
            && filter_var($ip, FILTER_VALIDATE_IP) !== false && IpUtils::checkIp($ip, $cidrs);
    }
}
