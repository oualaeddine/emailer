<?php

namespace App\Modules\Identity\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * docs/28-security.md §28.1, docs/28-security.md §28.7 — login rate limiting.
 * docs/29-api-specification.md §29.1 — 429 for rate-limited endpoints.
 */
class TooManyLoginAttemptsException extends Exception
{
    public function __construct(public readonly int $availableInSeconds)
    {
        parent::__construct('Too many login attempts.');
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => "Trop de tentatives de connexion. Réessayez dans {$this->availableInSeconds} secondes.",
        ], 429);
    }
}
