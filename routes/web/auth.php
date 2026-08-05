<?php

use App\Modules\Identity\Http\Controllers\AuthenticatedSessionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Guest Web Routes (Identity module)
|--------------------------------------------------------------------------
| Required from routes/web.php inside the `guest` middleware group
| (docs/42-parallel-execution-plan.md §42.5/§42.11).
*/

Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
Route::post('login', [AuthenticatedSessionController::class, 'store']);
