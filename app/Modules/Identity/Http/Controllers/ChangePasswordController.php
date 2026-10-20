<?php

namespace App\Modules\Identity\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Http\Requests\ChangePasswordRequest;
use App\Modules\Identity\Services\UserService;
use Illuminate\Http\JsonResponse;

/**
 * PUT /api/v1/me/password — the signed-in user changes their own password.
 */
class ChangePasswordController extends Controller
{
    public function __construct(private readonly UserService $users) {}

    public function __invoke(ChangePasswordRequest $request): JsonResponse
    {
        $this->users->changePassword(
            $request->user(),
            $request->validated('password'),
            $request->hasSession() ? $request->session()->getId() : null,
        );

        return response()->json(['message' => 'Mot de passe modifié.']);
    }
}
