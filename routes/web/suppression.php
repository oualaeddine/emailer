<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Suppression List Routes (Inertia pages)
|--------------------------------------------------------------------------
| docs/23-suppression-list.md §23.5 — Suppression List Management UI.
| docs/42-parallel-execution-plan.md §42.6 (WP-14).
| Required from routes/web/app.php, itself inside the `auth` middleware
| group (routes/web.php).
*/

Route::get('suppression', fn () => Inertia::render('Suppression/Index'))->name('suppression.index');
