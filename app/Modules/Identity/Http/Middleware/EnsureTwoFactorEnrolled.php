<?php

namespace App\Modules\Identity\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * docs/28-security.md §28.1 — 2FA is mandatory for every account.
 *
 * An authenticated user who has not yet confirmed TOTP enrolment is confined
 * to the enrolment endpoints (and logout) until they do. Web requests are
 * redirected to the setup page; API/XHR requests get a 403 carrying a machine
 * -readable reason so the SPA can route the user to enrolment.
 */
class EnsureTwoFactorEnrolled
{
    /**
     * Route names reachable while enrolment is still outstanding.
     *
     * @var list<string>
     */
    private const EXEMPT_ROUTES = [
        'two-factor.setup',
        'two-factor.confirm',
        'logout',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && ! $user->hasTwoFactorEnabled()
            && ! in_array($request->route()?->getName(), self::EXEMPT_ROUTES, true)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Enrôlement de la double authentification requis.',
                    'code' => 'two_factor_enrollment_required',
                ], 403);
            }

            return redirect()->route('two-factor.setup');
        }

        return $next($request);
    }
}
