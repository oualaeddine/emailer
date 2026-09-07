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
    /**
     * Per-(email, IP) lockout threshold. The IP is client-influenced behind a
     * proxy, so this alone is bypassable by rotating source addresses; the
     * per-email limit below is the real brute-force ceiling (§28.1).
     */
    private const MAX_ATTEMPTS = 5;

    /**
     * Per-email lockout threshold, independent of source IP. Caps total failed
     * attempts against a single account no matter how many addresses they come
     * from, closing the IP-rotation bypass of the per-IP limit.
     */
    private const MAX_ATTEMPTS_PER_EMAIL = 10;

    public function __construct(
        private readonly StatefulGuard $guard,
        private readonly Request $request,
    ) {}

    /**
     * Verify first-factor credentials. On success with TOTP enabled the guard
     * session is NOT started — {@see LoginResult::requiresTwoFactor} is set and
     * the caller must complete the challenge via {@see completeLogin()}.
     *
     * @throws TooManyLoginAttemptsException
     */
    public function attempt(string $email, string $password, bool $remember = false): LoginResult
    {
        $this->assertNotLockedOut($email);

        $provider = $this->guard->getProvider();
        $user = $provider->retrieveByCredentials(['email' => $email, 'is_active' => true]);

        if ($user === null || ! $provider->validateCredentials($user, ['password' => $password])) {
            $this->recordFailure($email);

            return LoginResult::failed();
        }

        $this->clearLockout($email);

        /** @var User $user */
        if ($user->hasTwoFactorEnabled()) {
            return LoginResult::twoFactorRequired($user);
        }

        $this->establishSession($user, $remember);

        return LoginResult::authenticated($user);
    }

    /**
     * Start the authenticated session after a passed second-factor challenge.
     */
    public function completeLogin(User $user, bool $remember = false): void
    {
        $this->establishSession($user, $remember);
    }

    public function logout(): void
    {
        $this->guard->logout();
        $this->request->session()->invalidate();
        $this->request->session()->regenerateToken();
    }

    private function establishSession(User $user, bool $remember): void
    {
        $this->guard->login($user, $remember);

        $user->forceFill(['last_login_at' => now()])->save();

        UserLoggedIn::dispatch($user);
    }

    /**
     * @throws TooManyLoginAttemptsException
     */
    private function assertNotLockedOut(string $email): void
    {
        if (RateLimiter::tooManyAttempts($this->ipThrottleKey($email), self::MAX_ATTEMPTS)) {
            throw new TooManyLoginAttemptsException(RateLimiter::availableIn($this->ipThrottleKey($email)));
        }

        if (RateLimiter::tooManyAttempts($this->emailThrottleKey($email), self::MAX_ATTEMPTS_PER_EMAIL)) {
            throw new TooManyLoginAttemptsException(RateLimiter::availableIn($this->emailThrottleKey($email)));
        }
    }

    private function recordFailure(string $email): void
    {
        $ipKey = $this->ipThrottleKey($email);
        $emailKey = $this->emailThrottleKey($email);

        RateLimiter::hit($ipKey, $this->decaySeconds(RateLimiter::attempts($ipKey) + 1));
        RateLimiter::hit($emailKey, 900);

        UserLoginFailed::dispatch($email);
    }

    private function clearLockout(string $email): void
    {
        RateLimiter::clear($this->ipThrottleKey($email));
        RateLimiter::clear($this->emailThrottleKey($email));
    }

    private function ipThrottleKey(string $email): string
    {
        return Str::lower($email).'|'.$this->request->ip();
    }

    private function emailThrottleKey(string $email): string
    {
        return 'login-email|'.Str::lower($email);
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
