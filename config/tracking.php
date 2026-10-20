<?php

/*
|--------------------------------------------------------------------------
| Tracking enrichment & campaign statistics (WP-70)
|--------------------------------------------------------------------------
| docs/43-brevo-parity-plan.md §43.6.3, §43.6.5 and
| docs/45-brevo-api-contracts.md §45.6.4. Nothing leaves the server: user
| agents are parsed locally (matomo/device-detector) and IPs are resolved
| against a local MaxMind GeoLite2 database (geoip2/geoip2).
*/

return [

    'geoip' => [
        // Local GeoLite2-City database; lookups return null when absent.
        'database_path' => env('GEOIP_DATABASE_PATH', storage_path('app/geoip/GeoLite2-City.mmdb')),
        // `php artisan geoip:update` (monthly) is a no-op with a warning without a licence key.
        'license_key' => env('MAXMIND_LICENSE_KEY'),
        'edition' => env('GEOIP_EDITION', 'GeoLite2-City'),
        'download_url' => env('GEOIP_DOWNLOAD_URL', 'https://download.maxmind.com/app/geoip_download'),
        'timeout' => (int) env('GEOIP_DOWNLOAD_TIMEOUT', 120),
    ],

    // Apple Mail Privacy Protection proxy opens (counted in viewed / uniqueViews /
    // appleMppOpens, excluded from trackableViews — doc 45 §45.6.4).
    'apple_mpp' => [
        // The proxy fetches images with this bare user agent (exact match, case-insensitive).
        'exact_user_agents' => ['Mozilla/5.0'],
        // Case-insensitive substrings of a proxy user agent.
        'user_agent_patterns' => ['applemailproxy', 'apple-mail-proxy'],
        // Apple-owned egress ranges used by the proxy.
        'cidrs' => array_values(array_filter(explode(',', (string) env('TRACKING_APPLE_MPP_CIDRS', '17.0.0.0/8')))),
    ],

    'stats' => [
        // Request paths never aggregate raw events for campaigns above this many
        // messages; they read the rollups and dispatch RollupCampaignStatsJob instead.
        'live_threshold' => (int) env('TRACKING_STATS_LIVE_THRESHOLD', 100000),
        // Rollups older than this are refreshed (inline below the threshold, queued above it).
        'max_age_seconds' => (int) env('TRACKING_STATS_MAX_AGE', 300),
        // Breakdown cache (statsByDomain / statsByDevice / statsByBrowser / statsByCountry).
        'breakdown_ttl_seconds' => 7 * 86400,
        // Account averages ("vs moyenne du compte").
        'averages_ttl_seconds' => 3600,
        'domain_limit' => 100,
        // The hourly scheduler re-rolls campaigns active in this window.
        'active_window_days' => 30,
    ],

];
