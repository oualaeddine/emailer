<?php

use App\Modules\Identity\Http\Controllers\TwoFactorChallengeController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| TOTP Challenge Routes (Identity module)
|--------------------------------------------------------------------------
| docs/28-security.md §28.1 — second factor at login. Required from
| routes/web.php inside the `two-factor.pending` middleware group.
*/

Route::get('two-factor/challenge', [TwoFactorChallengeController::class, 'create'])
    ->name('two-factor.challenge');
Route::post('two-factor/challenge', [TwoFactorChallengeController::class, 'store']);
