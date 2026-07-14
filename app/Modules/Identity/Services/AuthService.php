<?php

namespace App\Modules\Identity\Services;

use App\Domain\Events\UserLoggedIn;
use App\Domain\Events\UserLoginFailed;
use App\Modules\Identity\Exceptions\TooManyLoginAttemptsException;
use App\Modules\Identity\Models\User;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * docs/28-security.md §28.1 — Authentication.
 * Sanctum SPA session auth (cookie-based) — this service wraps the session
 * guard's attempt/logout, adds the documented lockout policy, and raises
 * the Domain Events that drive audit logging (docs/31-events.md).
 */
class AuthService
{
    private const MAX_ATTEMPTS = 5;

    public function __construct(
        private readonly StatefulGuard $guard,
        private readonly Request $request,
    ) {
    }

    /**
     * @throws TooManyLoginAttemptsException
     */
    public function attempt(string $email, string $password, bool $remember = false): bool
    {
        $throttleKey = $this->throttleKey($email);

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_ATTEMPTS)) {
            throw new TooManyLoginAttemptsException(RateLimiter::availableIn($throttleKey));
        }

        $credentials = ['email' => $email, 'password' => $password, 'is_active' => true];

        if (! $this->guard->attempt($credentials, $remember)) {
            RateLimiter::hit($throttleKey, $this->decaySeconds(RateLimiter::attempts($throttleKey) + 1));
            UserLoginFailed::dispatch($email);

            return false;
        }

        RateLimiter::clear($throttleKey);

        /** @var User $user */
        $user = $this->guard->user();
        $user->forceFill(['last_login_at' => now()])->save();

        UserLoggedIn::dispatch($user);

        return true;
    }

    public function logout(): void
    {
        $this->guard->logout();
        $this->request->session()->invalidate();
        $this->request->session()->regenerateToken();
    }

    private function throttleKey(string $email): string
    {
        return Str::lower($email).'|'.$this->request->ip();
    }

    /**
     * Exponential backoff: attempts beyond the threshold lock out for
     * progressively longer windows (docs/28-security.md §28.1).
     */
    private function decaySeconds(int $attempts): int
    {
        return min(60 * 2 ** max(0, $attempts - self::MAX_ATTEMPTS), 3600);
    }
}
