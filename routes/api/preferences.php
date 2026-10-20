<?php

use App\Modules\Suppression\Preferences\Http\Controllers\DoubleOptInController;
use App\Modules\Suppression\Preferences\Http\Controllers\UnsubscribePageController;
use App\Modules\Suppression\Preferences\Http\Controllers\UpdateProfileFormController;
use Illuminate\Support\Facades\Route;

/*
| docs/45 §45.6.6 — WP-74 unsubscribe pages & update-profile forms. Required
| from routes/api.php inside the `v1` + `auth:sanctum` group.
*/

Route::get('unsubscribe-pages', [UnsubscribePageController::class, 'index']);
Route::post('unsubscribe-pages', [UnsubscribePageController::class, 'store']);
Route::get('unsubscribe-pages/{id}/preview', [UnsubscribePageController::class, 'preview']);
Route::post('unsubscribe-pages/{id}/default', [UnsubscribePageController::class, 'makeDefault']);
Route::get('unsubscribe-pages/{id}', [UnsubscribePageController::class, 'show']);
Route::patch('unsubscribe-pages/{id}', [UnsubscribePageController::class, 'update']);
Route::delete('unsubscribe-pages/{id}', [UnsubscribePageController::class, 'destroy']);

Route::get('update-profile-forms', [UpdateProfileFormController::class, 'index']);
Route::post('update-profile-forms', [UpdateProfileFormController::class, 'store']);
Route::get('update-profile-forms/{id}/preview', [UpdateProfileFormController::class, 'preview']);
Route::post('update-profile-forms/{id}/default', [UpdateProfileFormController::class, 'makeDefault']);
Route::get('update-profile-forms/{id}', [UpdateProfileFormController::class, 'show']);
Route::patch('update-profile-forms/{id}', [UpdateProfileFormController::class, 'update']);
Route::delete('update-profile-forms/{id}', [UpdateProfileFormController::class, 'destroy']);

// Double opt-in send trigger (forms — Wave 4 — call DoubleOptInService directly).
Route::post('recipients/{uuid}/double-opt-in', [DoubleOptInController::class, 'send'])
    ->name('recipients.double-opt-in');
