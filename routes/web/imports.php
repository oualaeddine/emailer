<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Import Web Routes (Inertia pages) — WP-55
|--------------------------------------------------------------------------
| docs/43-brevo-parity-plan.md §43.4.12. Required from routes/web/app.php
| (inside the `auth` group).
*/

Route::get('contacts/imports', fn () => Inertia::render('Contacts/Imports'))
    ->middleware('can:recipients.import')
    ->name('contacts.imports');

Route::get('contacts/imports/new', fn () => Inertia::render('Contacts/ImportWizard', [
    'list_id' => request()->query('list_id'),
]))
    ->middleware('can:recipients.import')
    ->name('contacts.imports.new');
