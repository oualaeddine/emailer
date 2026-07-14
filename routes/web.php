<?php

use App\Modules\Identity\Http\Controllers\AuthenticatedSessionController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Web Routes (Inertia pages)
|--------------------------------------------------------------------------
| docs/08-navigation.md §8.3 — Route Map. Only routes needed by the
| Identity module are wired so far; further modules add their own route
| groups here as they are implemented (docs/34-roadmap.md build order).
*/

Route::middleware('guest')->group(function (): void {
    Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store']);
});

Route::middleware('auth')->group(function (): void {
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
});
