<?php

use App\Modules\DeliveryEngine\Rendering\Http\MirrorController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public Mirror Routes (WP-63)
|--------------------------------------------------------------------------
| docs/45 §45.6.3 — "voir dans le navigateur" (`{{ mirror }}`) and the
| anonymous campaign share link: public, unauthenticated, gated by the
| `signed` URL middleware (expiry only when the campaign sets an e-mail
| expiration). Same top-level group pattern as the tracking routes.
*/
Route::middleware('signed')->group(function (): void {
    Route::get('m/c/{campaign}', [MirrorController::class, 'campaign'])
        ->whereUuid('campaign')
        ->name('mirror.campaign');

    Route::get('m/{message}', [MirrorController::class, 'show'])
        ->whereUuid('message')
        ->name('mirror.show');
});
