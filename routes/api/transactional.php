<?php

use App\Modules\Transactional\Http\Controllers\ApiKeyController;
use App\Modules\Transactional\Http\Controllers\TransactionalLogController;
use App\Modules\Transactional\Http\Controllers\TransactionalSendController;
use App\Modules\Transactional\Http\Middleware\RequireApiScope;
use Illuminate\Support\Facades\Route;

/*
| docs/45 §45.7.6 — WP-100 API keys, transactional send, logs. Required from
| routes/api.php inside the `v1` + `auth:sanctum` group. API-key callers send
| `Authorization: Bearer <key>` or `api-key: <key>`; the scope is a Sanctum ability.
*/

Route::get('api-keys', [ApiKeyController::class, 'index']);
Route::post('api-keys', [ApiKeyController::class, 'store']);
Route::delete('api-keys/{id}', [ApiKeyController::class, 'destroy']);

Route::post('transactional/send', [TransactionalSendController::class, 'store'])
    ->middleware(RequireApiScope::class.':transactional:send,transactional.send');

Route::middleware(RequireApiScope::class.':transactional:read,transactional.view')->group(function (): void {
    Route::get('transactional/logs', [TransactionalLogController::class, 'index']);
    Route::get('transactional/logs/{id}', [TransactionalLogController::class, 'show']);
});

Route::get('transactional/settings', [TransactionalLogController::class, 'settings'])
    ->middleware(RequireApiScope::class.':transactional:read,transactional.view');
