<?php

namespace App\Modules\Forms\Services;

use App\Modules\Forms\Contracts\CaptchaVerifier;
use Illuminate\Support\Facades\Http;
use Throwable;

/** hCaptcha / Cloudflare Turnstile `siteverify` call. */
class HttpCaptchaVerifier implements CaptchaVerifier
{
    public const ENDPOINTS = [
        'hcaptcha' => 'https://hcaptcha.com/siteverify',
        'turnstile' => 'https://challenges.cloudflare.com/turnstile/v0/siteverify',
    ];

    public function __construct(private readonly string $provider, private readonly string $secret) {}

    public function verify(?string $token, ?string $ip): bool
    {
        if ($token === null || $token === '') {
            return false;
        }

        try {
            $response = Http::asForm()->timeout(5)->post(self::ENDPOINTS[$this->provider], array_filter([
                'secret' => $this->secret, 'response' => $token, 'remoteip' => $ip,
            ]));

            return $response->ok() && $response->json('success') === true;
        } catch (Throwable) {
            return false;
        }
    }
}
