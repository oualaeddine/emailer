<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Blocklist Routes (Inertia page)
|--------------------------------------------------------------------------
| docs/43 §43.4.13 — Contacts/Blocklist (WP-56). Required from
| routes/web/app.php (auth group).
*/

Route::get('contacts/blocklist', fn () => Inertia::render('Contacts/Blocklist'))
    ->middleware('can:suppression.view')
    ->name('contacts.blocklist');
