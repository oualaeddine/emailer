<?php

namespace App\Http\Middleware;

use App\Modules\Transactional\Http\Middleware\RequireApiScope;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * docs/45 §45.7.6 — API keys (Sanctum personal access tokens) may only call the
 * routes the contract lists as API-key capable. A route opts in by carrying
 * `RequireApiScope:{scope}[,{permission}]`, which then checks the key's scope.
 * Every other `/api/v1` route answers 403 to an API key, whatever the role of
 * the key's owner. Session (SPA) callers hold a TransientToken and are untouched.
 */
class RestrictApiKeysToTaggedRoutes
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->user('sanctum')?->currentAccessToken();

        if ($token instanceof PersonalAccessToken && ! self::acceptsApiKeys($request->route())) {
            return response()->json(['message' => 'Cette route n’est pas accessible avec une clé API.'], 403);
        }

        return $next($request);
    }

    private static function acceptsApiKeys(mixed $route): bool
    {
        if (! $route instanceof Route) {
            return false;
        }

        foreach ($route->gatherMiddleware() as $middleware) {
            if (is_string($middleware) && ($middleware === RequireApiScope::class || str_starts_with($middleware, RequireApiScope::class.':'))) {
                return true;
            }
        }

        return false;
    }
}
