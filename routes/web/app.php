<?php

use App\Modules\Identity\Http\Controllers\AuthenticatedSessionController;
use App\Modules\Identity\Http\Controllers\TwoFactorSetupController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Authenticated Web Routes (Inertia pages)
|--------------------------------------------------------------------------
| docs/08-navigation.md §8.3 — Route Map.
| Required from routes/web.php inside the `auth` middleware group
| (docs/42-parallel-execution-plan.md §42.5/§42.11).
*/

Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

// docs/28-security.md §28.1 — TOTP enrolment. Exempt from the mandatory-2FA
// gate (by route name) so a not-yet-enrolled user can actually reach it.
Route::get('two-factor/setup', [TwoFactorSetupController::class, 'show'])->name('two-factor.setup');
Route::post('two-factor/setup', [TwoFactorSetupController::class, 'confirm'])->name('two-factor.confirm');

Route::get('/', fn () => redirect()->route('dashboard'));

Route::get('dashboard', fn () => Inertia::render('Dashboard'))
    ->middleware('can:dashboard.view')
    ->name('dashboard');

Route::get('admin/users', fn () => Inertia::render('Admin/Users'))
    ->middleware('can:users.manage')
    ->name('admin.users');

Route::get('settings/branding', fn () => Inertia::render('Settings/Branding'))
    ->middleware('can:settings.branding.access')
    ->name('settings.branding');

Route::get('recipients/import/pagejaunes', fn () => Inertia::render('Recipients/PageJaunesSearch'))
    ->middleware('can:recipients.import')
    ->name('recipients.import.pagejaunes');

Route::get('recipients', fn () => Inertia::render('Recipients/Index'))
    ->middleware('can:recipients.view')
    ->name('recipients.index');

Route::get('recipients/import', fn () => Inertia::render('Recipients/Import'))
    ->middleware('can:recipients.import')
    ->name('recipients.import');

Route::get('compose', fn () => Inertia::render('Composer/Index'))
    ->middleware('can:composer.compose')
    ->name('composer.index');

Route::get('templates', fn () => Inertia::render('Templates/Index'))
    ->middleware('can:templates.view')
    ->name('templates.index');

Route::get('smtp', fn () => Inertia::render('Smtp/Index'))
    ->middleware('can:smtp.view')
    ->name('smtp.index');

require __DIR__.'/suppression.php';
require __DIR__.'/mailbox.php';
require __DIR__.'/campaigns.php';
require __DIR__.'/audit.php';
require __DIR__.'/reporting.php';
require __DIR__.'/help.php';
