<?php

namespace App\Modules\Identity\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Http\Requests\LoginRequest;
use App\Modules\Identity\Services\AuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * docs/29-api-specification.md §29.2 — POST /login, POST /logout.
 * docs/28-security.md §28.1 — first factor; the TOTP second factor, when the
 * account has it enabled, is handled by {@see TwoFactorChallengeController}.
 */
class AuthenticatedSessionController extends Controller
{
    public function __construct(private readonly AuthService $authService) {}

    public function create(): Response
    {
        return Inertia::render('Auth/Login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $result = $this->authService->attempt(
            $request->string('email')->toString(),
            $request->string('password')->toString(),
            $request->boolean('remember'),
        );

        if (! $result->succeeded) {
            throw ValidationException::withMessages([
                'email' => ['Identifiants incorrects ou compte désactivé.'],
            ]);
        }

        if ($result->requiresTwoFactor) {
            // Credentials are correct but the session is not started yet: park
            // the pending identity and hand off to the TOTP challenge.
            $request->session()->put('two_factor.login.id', $result->user->getKey());
            $request->session()->put('two_factor.login.remember', $request->boolean('remember'));

            return redirect()->route('two-factor.challenge');
        }

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(): RedirectResponse
    {
        $this->authService->logout();

        return redirect()->route('login');
    }
}
