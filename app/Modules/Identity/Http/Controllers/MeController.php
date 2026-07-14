<?php

namespace App\Modules\Identity\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Http\Resources\UserResource;
use Illuminate\Http\Request;

/**
 * docs/29-api-specification.md §29.2 — GET /api/v1/me.
 * "current user + permissions array (drives frontend gating, 26.5)".
 */
class MeController extends Controller
{
    public function __invoke(Request $request): array
    {
        $user = $request->user()->load('role.permissions');

        return [
            'data' => (new UserResource($user))->resolve($request) + [
                'permissions' => $user->role?->permissions->pluck('name')->values() ?? [],
            ],
        ];
    }
}
