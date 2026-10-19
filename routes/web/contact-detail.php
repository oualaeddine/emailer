<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Contact detail page (Inertia)
|--------------------------------------------------------------------------
| docs/43-brevo-parity-plan.md §43.4.7 (WP-52). Required from
| routes/web/app.php, inside the `auth` group. UUID-constrained so it never
| shadows /contacts/lists, /contacts/segments, ...
*/

Route::get('contacts/{recipient}', fn (string $recipient) => Inertia::render('Contacts/Show', ['uuid' => $recipient]))
    ->whereUuid('recipient')
    ->middleware('can:recipients.view')
    ->name('contacts.show');
