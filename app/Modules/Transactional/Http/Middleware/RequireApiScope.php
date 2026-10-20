<?php

namespace App\Modules\Transactional\Http\Middleware;

use App\Modules\Transactional\Services\ApiKeyService;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * docs/45 §45.1 / §45.7.6 — `require.api.scope:{scope},{permission}` after `auth:sanctum`.
 * API-key callers (bearer / `api-key` header → PersonalAccessToken) need the
 * Sanctum ability `{scope}`; SPA session callers need the permission.
 */
class RequireApiScope
{
    public function __construct(private readonly ApiKeyService $keys) {}

    public function handle(Request $request, Closure $next, string $scope, ?string $permission = null): Response
    {
        $user = $request->user();
        $token = $user?->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            if (! $token->can($scope)) {
                return response()->json(['message' => 'Cette clé API n’a pas l’autorisation requise ('.$scope.').'], 403);
            }
            $this->keys->touchIp($token, $request->ip());

            return $next($request);
        }

        if ($user === null || ($permission !== null && ! $user->hasPermission($permission))) {
            return response()->json(['message' => 'Action non autorisée.'], 403);
        }

        return $next($request);
    }
}
