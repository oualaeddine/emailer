<?php

namespace App\Modules\Tracking\Support;

use DeviceDetector\DeviceDetector;
use Throwable;

/**
 * docs/43-brevo-parity-plan.md §43.6.5 / doc 45 §45.6.4 — parses the
 * `User-Agent` of an open / click locally with `matomo/device-detector`.
 *
 * - `device_type`: `desktop` | `mobile` | `tablet` | `unknown` (statsByDevice keys);
 * - `email_client`: mail client or browser name (statsByBrowser key). Web-mail
 *   image proxies and native clients that device-detector reports as a plain
 *   browser (Apple Mail is "Safari" without a `Safari/` token) are mapped first;
 * - `os`: operating system name (inner statsByDevice key), or null.
 *
 * @phpstan-type ParsedAgent array{device_type: string, email_client: string|null, os: string|null}
 */
class UserAgentParser
{
    private const DEVICE_MAP = [
        'desktop' => 'desktop',
        'smartphone' => 'mobile',
        'feature phone' => 'mobile',
        'phablet' => 'mobile',
        'wearable' => 'mobile',
        'portable media player' => 'mobile',
        'tablet' => 'tablet',
    ];

    /** Ordered regex → client name for clients device-detector does not name as such. */
    private const CLIENT_PATTERNS = [
        '/GoogleImageProxy/i' => 'Gmail',
        '/YahooMailProxy/i' => 'Yahoo Mail',
        '/AppleMailProxy|Apple-Mail-Proxy/i' => 'Apple Mail',
        '/Outlook-(iOS|Android)/i' => 'Outlook',
        '/Microsoft Outlook|MSOffice|ms-office/i' => 'Outlook',
        '/Thunderbird/i' => 'Thunderbird',
        '/Gmail/i' => 'Gmail',
        '/\bMail\/\d/' => 'Apple Mail',
    ];

    /** @var array<string, array{device_type: string, email_client: string|null, os: string|null}> */
    private array $memo = [];

    /**
     * @return array{device_type: string, email_client: string|null, os: string|null}
     */
    public function parse(?string $userAgent): array
    {
        $ua = trim((string) $userAgent);
        if ($ua === '') {
            return ['device_type' => 'unknown', 'email_client' => null, 'os' => null];
        }

        if (isset($this->memo[$ua])) {
            return $this->memo[$ua];
        }

        $device = 'unknown';
        $client = null;
        $os = null;

        try {
            $detector = new DeviceDetector($ua);
            $detector->parse();
            $device = self::DEVICE_MAP[strtolower((string) $detector->getDeviceName())] ?? 'unknown';
            $name = $detector->getClient('name');
            $client = is_string($name) && $name !== '' && $name !== 'UNK' ? $name : null;
            $osName = $detector->getOs('name');
            $os = is_string($osName) && $osName !== '' && $osName !== 'UNK' ? $osName : null;
        } catch (Throwable) {
            // Unparseable agent: keep the unknown defaults.
        }

        $client = $this->knownClient($ua) ?? $client;

        if (count($this->memo) > 500) {
            $this->memo = [];
        }

        return $this->memo[$ua] = [
            'device_type' => $device,
            'email_client' => $client !== null ? mb_substr($client, 0, 64) : null,
            'os' => $os !== null ? mb_substr($os, 0, 64) : null,
        ];
    }

    private function knownClient(string $ua): ?string
    {
        foreach (self::CLIENT_PATTERNS as $pattern => $name) {
            if (preg_match($pattern, $ua) === 1) {
                return $name;
            }
        }

        // Apple Mail (macOS / iOS) renders with WebKit but, unlike Safari,
        // sends no `Safari/` token.
        if (preg_match('/AppleWebKit/i', $ua) === 1
            && preg_match('/Macintosh|iPhone|iPad|iPod/i', $ua) === 1
            && preg_match('/Safari\/|Chrome|CriOS|FxiOS|EdgiOS|OPiOS/i', $ua) !== 1) {
            return 'Apple Mail';
        }

        return null;
    }
}
