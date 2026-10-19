<?php

use App\Modules\Senders\Http\Controllers\SenderController;
use App\Modules\Senders\Http\Controllers\SendingDomainController;
use Illuminate\Support\Facades\Route;

/*
| docs/45 §45.4.1 — WP-41 Senders & domains. Required from routes/api.php inside
| the `v1` + `auth:sanctum` group.
*/

// Public signed link (48 h) — no session required.
Route::get('senders/{id}/verify', [SenderController::class, 'verify'])
    ->middleware('signed')
    ->withoutMiddleware('auth:sanctum')
    ->name('senders.verify');

Route::get('senders', [SenderController::class, 'index']);
Route::post('senders', [SenderController::class, 'store']);
Route::get('senders/{id}', [SenderController::class, 'show']);
Route::patch('senders/{id}', [SenderController::class, 'update']);
Route::delete('senders/{id}', [SenderController::class, 'destroy']);
Route::post('senders/{id}/default', [SenderController::class, 'makeDefault']);
Route::post('senders/{id}/resend-verification', [SenderController::class, 'resendVerification']);

Route::get('sending-domains', [SendingDomainController::class, 'index']);
Route::post('sending-domains', [SendingDomainController::class, 'store']);
Route::get('sending-domains/{id}', [SendingDomainController::class, 'show']);
Route::post('sending-domains/{id}/verify', [SendingDomainController::class, 'verify']);
Route::delete('sending-domains/{id}', [SendingDomainController::class, 'destroy']);
