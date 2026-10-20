<?php

use App\Modules\Webhooks\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

/*
| docs/45 §45.7.7 — WP-101 outgoing webhooks. Required from routes/api.php inside
| the `v1` + `auth:sanctum` group.
*/

Route::middleware('can:webhooks.manage')->group(function (): void {
    Route::get('webhooks', [WebhookController::class, 'index']);
    Route::post('webhooks', [WebhookController::class, 'store']);
    Route::get('webhooks/{id}', [WebhookController::class, 'show']);
    Route::patch('webhooks/{id}', [WebhookController::class, 'update']);
    Route::delete('webhooks/{id}', [WebhookController::class, 'destroy']);
    Route::post('webhooks/{id}/rotate-secret', [WebhookController::class, 'rotateSecret']);
    Route::post('webhooks/{id}/test', [WebhookController::class, 'test']);
    Route::get('webhooks/{id}/deliveries', [WebhookController::class, 'deliveries']);
    Route::post('webhook-deliveries/{id}/retry', [WebhookController::class, 'retry']);
});
