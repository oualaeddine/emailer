<?php

namespace App\Modules\Forms\Services;

use App\Modules\Forms\Contracts\CaptchaVerifier;
use App\Modules\Settings\Services\SettingsService;

/** Picks the verifier from settings `forms.captcha_provider` (none|hcaptcha|turnstile) + `forms.captcha_secret`. */
class SettingsCaptchaVerifier implements CaptchaVerifier
{
    public function __construct(private readonly SettingsService $settings) {}

    public function verify(?string $token, ?string $ip): bool
    {
        return $this->delegate()->verify($token, $ip);
    }

    private function delegate(): CaptchaVerifier
    {
        $provider = (string) $this->settings->get('forms.captcha_provider', 'none');
        $secret = (string) $this->settings->get('forms.captcha_secret', '');

        if (isset(HttpCaptchaVerifier::ENDPOINTS[$provider]) && $secret !== '') {
            return new HttpCaptchaVerifier($provider, $secret);
        }

        return new NullCaptcha;
    }
}
