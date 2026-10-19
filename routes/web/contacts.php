<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Contacts Routes (Inertia pages)
|--------------------------------------------------------------------------
| docs/43-brevo-parity-plan.md §43.4.6 (WP-51) — Contacts table v2.
| Required from routes/web/app.php, itself inside the `auth` middleware
| group (routes/web.php).
*/

Route::get('contacts', fn () => Inertia::render('Contacts/Index'))
    ->middleware('can:recipients.view')
    ->name('contacts.index');
