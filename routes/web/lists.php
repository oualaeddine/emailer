<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Lists Routes (Inertia pages) — docs/43 §43.4.8 (WP-53)
|--------------------------------------------------------------------------
| Required from routes/web/app.php, itself inside the `auth` middleware group.
*/

Route::get('contacts/lists', fn () => Inertia::render('Contacts/Lists'))
    ->middleware('can:recipients.view')
    ->name('contacts.lists');

Route::get('contacts/lists/{list}', fn (string $list) => Inertia::render('Contacts/ListShow', ['uuid' => $list]))
    ->middleware('can:recipients.view')
    ->name('contacts.lists.show');
