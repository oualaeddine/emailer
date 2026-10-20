<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Statistics Web Routes (Inertia pages)
|--------------------------------------------------------------------------
| docs/43 §43.4.16 (WP-73). Required from routes/web/app.php inside the
| `auth` middleware group. Supersedes the legacy `reporting.index` line.
*/

Route::get('statistics', fn () => Inertia::render('Statistics/Index'))
    ->middleware('can:reporting.view')
    ->name('statistics.index');
