<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Reporting Web Routes (Inertia pages)
|--------------------------------------------------------------------------
| docs/25-reporting.md, docs/42-parallel-execution-plan.md §42.8 (WP-31).
| Required from routes/web/app.php inside the `auth` middleware group.
*/

Route::get('statistics', fn () => Inertia::render('Reporting/Index'))
    ->middleware('can:reporting.view')
    ->name('reporting.index');

// docs/43 §43.3 — legacy URL, permanent redirect for one release.
Route::redirect('reporting', '/statistics', 301);
