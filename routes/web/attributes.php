<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Contact Attributes Routes (Inertia page)
|--------------------------------------------------------------------------
| docs/43-brevo-parity-plan.md §43.4.10 (WP-50U).
| Required from routes/web/app.php, itself inside the `auth` middleware
| group (routes/web.php).
*/

Route::get('contacts/attributes', fn () => Inertia::render('Contacts/Attributes'))
    ->middleware('can:recipients.view')
    ->name('contacts.attributes');
