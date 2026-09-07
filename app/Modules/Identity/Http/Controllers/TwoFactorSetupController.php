<?php

namespace App\Modules\Identity\Http\Controllers;

use App\Domain\Events\TwoFactorEnabled;
use App\Http\Controllers\Controller;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\TwoFactorAuthenticator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * docs/28-security.md §28.1 — TOTP enrolment.
 *
 * 2FA is mandatory for every account, so the `two-factor.enrol` middleware
 * funnels any not-yet-enrolled user here. The user scans the QR (or types the
 * secret), proves possession with a code, and is shown single-use recovery
 * codes exactly once.
 */
class TwoFactorSetupController extends Controller
{
    public function __construct(private readonly TwoFactorAuthenticator $authenticator) {}

    public function show(Request $request): Response|RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $recoveryCodes = $request->session()->get('two_factor.recovery_codes');

        // Already enrolled and nothing fresh to reveal — nothing to do here.
        if ($user->hasTwoFactorEnabled() && $recoveryCodes === null) {
            return redirect()->intended(route('dashboard'));
        }

        if ($recoveryCodes !== null) {
            return Inertia::render('Auth/TwoFactorSetup', [
                'confirmed' => true,
                'recoveryCodes' => $recoveryCodes,
            ]);
        }

        // Reuse an in-progress secret so a page reload doesn't invalidate a
        // code the user already scanned; otherwise mint a new one.
        if ($user->two_factor_secret === null) {
            $user->forceFill([
                'two_factor_secret' => $this->authenticator->generateSecret(),
                'two_factor_confirmed_at' => null,
            ])->save();
        }

        return Inertia::render('Auth/TwoFactorSetup', [
            'confirmed' => false,
            'secret' => $user->two_factor_secret,
            'qrSvg' => $this->authenticator->qrCodeSvg(
                (string) config('app.name'),
                $user->email,
                $user->two_factor_secret,
            ),
        ]);
    }

    public function confirm(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->hasTwoFactorEnabled()) {
            return redirect()->intended(route('dashboard'));
        }

        $request->validate(['code' => ['required', 'string']]);

        if ($user->two_factor_secret === null
            || ! $this->authenticator->verify($user->two_factor_secret, (string) $request->input('code'))) {
            throw ValidationException::withMessages([
                'code' => ['Le code de vérification est invalide.'],
            ]);
        }

        $recoveryCodes = $this->authenticator->generateRecoveryCodes();

        $user->forceFill([
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => $recoveryCodes,
        ])->save();

        TwoFactorEnabled::dispatch($user);

        // Flash the codes for a single render on the setup page.
        return redirect()
            ->route('two-factor.setup')
            ->with('two_factor.recovery_codes', $recoveryCodes);
    }
}
