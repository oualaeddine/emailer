<?php

use App\Modules\Identity\Http\Controllers\AuthenticatedSessionController;
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

Route::get('/', fn () => redirect()->route('dashboard'));

Route::get('dashboard', fn () => Inertia::render('Dashboard'))->name('dashboard');

Route::get('admin/users', fn () => Inertia::render('Admin/Users'))->name('admin.users');

Route::get('settings/branding', fn () => Inertia::render('Settings/Branding'))->name('settings.branding');

Route::get('recipients/import/pagejaunes', fn () => Inertia::render('Recipients/PageJaunesSearch'))
    ->name('recipients.import.pagejaunes');

Route::get('recipients', fn () => Inertia::render('Recipients/Index'))->name('recipients.index');

Route::get('recipients/import', fn () => Inertia::render('Recipients/Import'))->name('recipients.import');

Route::get('compose', fn () => Inertia::render('Composer/Index'))->name('composer.index');

Route::get('templates', fn () => Inertia::render('Templates/Index'))->name('templates.index');

Route::get('smtp', fn () => Inertia::render('Smtp/Index'))->name('smtp.index');

require __DIR__.'/suppression.php';
require __DIR__.'/mailbox.php';
