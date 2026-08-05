<?php

use App\Modules\Tracking\Http\Controllers\MessageController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Tracking API Routes (messages / delivery tracking / email history)
|--------------------------------------------------------------------------
| Required from routes/api.php inside the `v1` + `auth:sanctum` group
| (docs/42-parallel-execution-plan.md §42.6/§42.11).
*/

Route::get('messages', [MessageController::class, 'index']);
Route::get('messages/{uuid}', [MessageController::class, 'show']);
