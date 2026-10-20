<?php

namespace App\Modules\Forms\Contracts;

use App\Modules\Forms\Services\SettingsCaptchaVerifier;
use Illuminate\Container\Attributes\Bind;

/** Captcha hook of the public form submit (docs/45 §45.7.4). */
#[Bind(SettingsCaptchaVerifier::class)]
interface CaptchaVerifier
{
    public function verify(?string $token, ?string $ip): bool;
}
