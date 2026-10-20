<?php

use App\Modules\Identity\Http\Controllers\ChangePasswordController;
use App\Modules\Identity\Http\Controllers\MeController;
use App\Modules\Identity\Http\Controllers\ResetTwoFactorController;
use App\Modules\Identity\Http\Controllers\RoleController;
use App\Modules\Identity\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Identity API Routes
|--------------------------------------------------------------------------
| Required from routes/api.php inside the `v1` + `auth:sanctum` group
| (docs/42-parallel-execution-plan.md §42.5/§42.11).
*/

Route::get('me', MeController::class);
// Self-service password change (session-authenticated; API keys are rejected
// by RestrictApiKeysToTaggedRoutes because the route carries no scope tag).
Route::put('me/password', ChangePasswordController::class)->middleware('throttle:5,1');

Route::get('users', [UserController::class, 'index']);
Route::post('users', [UserController::class, 'store']);
Route::patch('users/{user}', [UserController::class, 'update']);
// Administrator reset of another user's two-factor enrolment (session-only, like the
// password endpoints: no RequireApiScope tag, so API keys get 403).
Route::post('users/{user}/two-factor/reset', ResetTwoFactorController::class)->middleware('throttle:20,1');

Route::get('roles', [RoleController::class, 'index']);
