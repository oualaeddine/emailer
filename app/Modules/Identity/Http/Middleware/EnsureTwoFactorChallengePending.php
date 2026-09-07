<?php

namespace App\Modules\Identity\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * docs/28-security.md §28.1 — guards the TOTP challenge endpoints.
 *
 * The challenge lives between the two factors: the password has been accepted
 * but no session started. Access requires the parked pending identity; an
 * already-authenticated user is bounced to the dashboard, everyone else to
 * login.
 */
class EnsureTwoFactorChallengePending
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() !== null) {
            return redirect()->route('dashboard');
        }

        if ($request->session()->get('two_factor.login.id') === null) {
            return redirect()->route('login');
        }

        return $next($request);
    }
}
