<?php

namespace App\Modules\Forms\Services;

use App\Modules\Forms\Contracts\CaptchaVerifier;

/** No provider configured: every submission passes. */
class NullCaptcha implements CaptchaVerifier
{
    public function verify(?string $token, ?string $ip): bool
    {
        return true;
    }
}
