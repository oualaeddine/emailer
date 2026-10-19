<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Senders & Domains Routes (Inertia pages)
|--------------------------------------------------------------------------
| docs/43-brevo-parity-plan.md §43.3 / §43.4.14 (WP-41U).
| Required from routes/web/app.php, itself inside the `auth` middleware
| group (routes/web.php).
*/

Route::get('settings/senders', fn () => Inertia::render('Settings/Senders'))
    ->middleware('can:senders.manage')
    ->name('settings.senders');

Route::get('settings/domains', fn () => Inertia::render('Settings/Domains'))
    ->middleware('can:senders.manage')
    ->name('settings.domains');
