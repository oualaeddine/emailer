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

Route::get('reporting', fn () => Inertia::render('Reporting/Index'))->name('reporting.index');
