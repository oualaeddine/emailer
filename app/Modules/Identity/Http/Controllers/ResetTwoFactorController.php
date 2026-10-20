<?php

namespace App\Modules\Identity\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * POST /api/v1/users/{uuid}/two-factor/reset — an administrator (`users.manage`)
 * wipes another user's TOTP enrolment (lost phone, no recovery codes). The user
 * is sent through the mandatory setup again at their next login. Session-only:
 * the route carries no API scope tag, so API keys are rejected.
 */
class ResetTwoFactorController extends Controller
{
    public function __construct(private readonly UserService $users) {}

    public function __invoke(Request $request, User $user): JsonResponse
    {
        $this->authorize('resetTwoFactor', $user);

        if ($user->is($request->user())) {
            throw ValidationException::withMessages([
                'user' => ['Vous ne pouvez pas réinitialiser votre propre double authentification ; demandez à un autre administrateur.'],
            ]);
        }

        $this->users->resetTwoFactor($user, $request->user());

        return response()->json(['message' => 'Double authentification réinitialisée.']);
    }
}
