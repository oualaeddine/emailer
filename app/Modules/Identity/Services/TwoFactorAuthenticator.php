<?php

namespace App\Modules\Identity\Services;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

/**
 * docs/28-security.md §28.1 — TOTP two-factor primitives.
 *
 * Wraps {@see Google2FA} so the rest of the Identity module never touches the
 * library directly: secret generation, otpauth QR rendering, code
 * verification (with a ±1 step window to tolerate clock skew), and one-time
 * recovery-code generation/consumption.
 */
class TwoFactorAuthenticator
{
    /**
     * Number of 30s steps of clock drift tolerated either side of "now".
     */
    private const WINDOW = 1;

    private const RECOVERY_CODE_COUNT = 8;

    public function __construct(private readonly Google2FA $google2fa) {}

    public function generateSecret(): string
    {
        return $this->google2fa->generateSecretKey();
    }

    /**
     * The otpauth:// URI an authenticator app scans, wrapped in an SVG QR code
     * so the frontend can render it inline without a client-side QR library.
     */
    public function qrCodeSvg(string $companyName, string $holder, string $secret): string
    {
        $url = $this->google2fa->getQRCodeUrl($companyName, $holder, $secret);

        $writer = new Writer(new ImageRenderer(
            new RendererStyle(192, 1),
            new SvgImageBackEnd,
        ));

        return $writer->writeString($url);
    }

    public function verify(string $secret, string $code): bool
    {
        $code = trim($code);

        if ($code === '') {
            return false;
        }

        return $this->google2fa->verifyKey($secret, $code, self::WINDOW);
    }

    /**
     * @return list<string> freshly minted single-use recovery codes
     */
    public function generateRecoveryCodes(): array
    {
        return array_map(
            static fn (): string => Str::upper(Str::random(5)).'-'.Str::upper(Str::random(5)),
            range(1, self::RECOVERY_CODE_COUNT),
        );
    }
}
