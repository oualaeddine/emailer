<?php

use App\Modules\Suppression\Preferences\Http\Controllers\PreferencesController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Preference pages (WP-74)
|--------------------------------------------------------------------------
| docs/45 §45.6.6 — public signed `preferences/{token}` (same scheme as the
| unsubscribe link) and the two Inertia settings pages. Required from the
| bottom of routes/web.php (top level): each group applies its own middleware.
*/

Route::middleware('signed')->group(function (): void {
    Route::get('preferences/{token}/confirm', [PreferencesController::class, 'confirm'])->name('preferences.confirm');
    Route::get('preferences/{token}', [PreferencesController::class, 'show'])->name('preferences.show');
    Route::post('preferences/{token}', [PreferencesController::class, 'update'])
        ->middleware('throttle:10,1')
        ->name('preferences.update');
});

Route::middleware(['auth', 'two-factor.enrol'])->group(function (): void {
    Route::get('settings/unsubscribe-pages', fn () => Inertia::render('Settings/UnsubscribePages'))
        ->middleware('can:settings.manage')
        ->name('settings.unsubscribe-pages');

    Route::get('settings/update-profile-forms', fn () => Inertia::render('Settings/UpdateProfileForms'))
        ->middleware('can:settings.manage')
        ->name('settings.update-profile-forms');
});
