<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Modules\Identity\Http\Middleware\EnsureTwoFactorChallengePending;
use App\Modules\Identity\Http\Middleware\EnsureTwoFactorEnrolled;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            HandleInertiaRequests::class,
        ]);

        $middleware->api(prepend: [
            EnsureFrontendRequestsAreStateful::class,
        ]);

        // docs/28-security.md §28.1 — mandatory-2FA enforcement and the
        // between-factors challenge guard, referenced by name in the route
        // files.
        $middleware->alias([
            'two-factor.enrol' => EnsureTwoFactorEnrolled::class,
            'two-factor.pending' => EnsureTwoFactorChallengePending::class,
        ]);

        // Every authenticated API request is subject to the enrolment gate too,
        // so the second factor can't be sidestepped by calling the API directly.
        $middleware->api(append: [
            EnsureTwoFactorEnrolled::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->respond(function (\Symfony\Component\HttpFoundation\Response $response, \Throwable $e, \Illuminate\Http\Request $request) {
            if ($response->getStatusCode() === 403 && ! $request->is('api/*')) {
                return \Inertia\Inertia::render('Error', [
                    'status' => 403,
                    'message' => $e->getMessage() ?: "Vous n'avez pas l'autorisation d'accéder à cette page.",
                ])
                ->toResponse($request)
                ->setStatusCode(403);
            }

            return $response;
        });
    })->create();
