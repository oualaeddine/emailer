<?php

namespace App\Modules\Identity\Http\Controllers;

use App\Domain\Events\TwoFactorChallengeFailed;
use App\Http\Controllers\Controller;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\AuthService;
use App\Modules\Identity\Services\TwoFactorAuthenticator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * docs/28-security.md §28.1 — TOTP second factor at login.
 *
 * Reached only when {@see AuthenticatedSessionController} has verified the
 * password of a 2FA-enabled account and parked the pending identity in the
 * session (guarded by the `two-factor.pending` middleware). Accepts either a
 * 6-digit authenticator code or a single-use recovery code.
 */
class TwoFactorChallengeController extends Controller
{
    private const MAX_ATTEMPTS = 5;

    public function __construct(
        private readonly AuthService $authService,
        private readonly TwoFactorAuthenticator $authenticator,
    ) {}

    public function create(): Response|RedirectResponse
    {
        if (! $this->pendingUser()) {
            return redirect()->route('login');
        }

        return Inertia::render('Auth/TwoFactorChallenge');
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $this->pendingUser();

        if (! $user) {
            return redirect()->route('login');
        }

        $validated = $request->validate([
            'code' => ['nullable', 'string'],
            'recovery_code' => ['nullable', 'string'],
        ]);

        $code = trim((string) ($validated['code'] ?? ''));
        $recoveryCode = trim((string) ($validated['recovery_code'] ?? ''));

        // Errors belong under the field the user is actually looking at.
        $field = $code === '' && $recoveryCode !== '' ? 'recovery_code' : 'code';

        $this->assertNotLockedOut($user, $field);

        $passed = match (true) {
            $code !== '' => $this->authenticator->verify($user->two_factor_secret, $code),
            $recoveryCode !== '' => $this->consumeRecoveryCode($user, $recoveryCode),
            default => false,
        };

        if (! $passed) {
            RateLimiter::hit($this->throttleKey($user), 300);
            TwoFactorChallengeFailed::dispatch($user);

            throw ValidationException::withMessages([
                $field => ['Le code de vérification est invalide.'],
            ]);
        }

        RateLimiter::clear($this->throttleKey($user));

        $remember = (bool) $request->session()->pull('two_factor.login.remember', false);
        $request->session()->forget('two_factor.login.id');

        $this->authService->completeLogin($user, $remember);

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    /**
     * Abandon the pending challenge: forget the parked identity and return to login.
     */
    public function cancel(Request $request): RedirectResponse
    {
        $request->session()->forget(['two_factor.login.id', 'two_factor.login.remember']);

        return redirect()->route('login');
    }

    private function pendingUser(): ?User
    {
        $id = request()->session()->get('two_factor.login.id');

        if ($id === null) {
            return null;
        }

        /** @var User|null $user */
        $user = User::query()->where('is_active', true)->find($id);

        return $user?->hasTwoFactorEnabled() ? $user : null;
    }

    /**
     * Match a recovery code and, on success, burn it so it can't be reused.
     */
    private function consumeRecoveryCode(User $user, string $candidate): bool
    {
        $codes = $user->two_factor_recovery_codes ?? [];
        $remaining = array_values(array_filter(
            $codes,
            static fn (string $code): bool => ! hash_equals($code, $candidate),
        ));

        if (count($remaining) === count($codes)) {
            return false;
        }

        $user->forceFill(['two_factor_recovery_codes' => $remaining])->save();

        return true;
    }

    /**
     * A validation-style 429: Inertia gets a redirect back with the message under
     * the field; a JSON caller still receives HTTP 429 with the errors payload.
     *
     * @throws ValidationException
     */
    private function assertNotLockedOut(User $user, string $field): void
    {
        if (RateLimiter::tooManyAttempts($this->throttleKey($user), self::MAX_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($this->throttleKey($user));

            throw ValidationException::withMessages([
                $field => ["Trop de tentatives de vérification. Réessayez dans {$seconds} secondes."],
            ])->status(429);
        }
    }

    private function throttleKey(User $user): string
    {
        return 'two-factor|'.$user->getKey().'|'.request()->ip();
    }
}
