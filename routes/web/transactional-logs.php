<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
| docs/43 §43.4.17 — WP-101 transactional logs page. Required from routes/web/app.php,
| itself inside the `auth` group. The data comes from `GET /api/v1/transactional/logs` (WP-100).
*/

Route::get('transactional/logs', fn () => Inertia::render('Transactional/Logs'))
    ->middleware('can:transactional.view')
    ->name('transactional.logs');
