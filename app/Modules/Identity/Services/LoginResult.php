<?php

namespace App\Modules\Identity\Services;

use App\Modules\Identity\Models\User;

/**
 * Outcome of a first-factor (email + password) attempt.
 *
 * docs/28-security.md §28.1 — when the credentials are correct but the account
 * has TOTP enabled, the session is deliberately NOT started; the caller must
 * drive the second-factor challenge before {@see AuthService::completeLogin()}.
 */
final class LoginResult
{
    private function __construct(
        public readonly bool $succeeded,
        public readonly bool $requiresTwoFactor,
        public readonly ?User $user,
    ) {}

    public static function failed(): self
    {
        return new self(false, false, null);
    }

    public static function authenticated(User $user): self
    {
        return new self(true, false, $user);
    }

    public static function twoFactorRequired(User $user): self
    {
        return new self(true, true, $user);
    }
}
