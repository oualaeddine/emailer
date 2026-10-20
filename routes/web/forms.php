<?php

use App\Modules\Forms\Http\Controllers\PublicFormController;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public subscription forms (WP-90)
|--------------------------------------------------------------------------
| docs/45 §45.7.4 — top-level public group (no auth). The POST is hit by the
| cross-origin embed script, so it is exempt from CSRF and protected by the
| per-IP throttle, a honeypot and the optional captcha instead.
*/

Route::get('f/{form}', [PublicFormController::class, 'show'])->whereUuid('form')->name('forms.show');
Route::post('f/{form}', [PublicFormController::class, 'submit'])
    ->whereUuid('form')
    ->middleware('throttle:5,1')
    ->withoutMiddleware([ValidateCsrfToken::class])
    ->name('forms.submit');
Route::get('f/{form}/embed.js', [PublicFormController::class, 'embedScript'])->whereUuid('form')->name('forms.embed');
Route::get('f/{form}/merci', [PublicFormController::class, 'thanks'])->whereUuid('form')->name('forms.thanks');
