<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Segments web routes (WP-54U)
|--------------------------------------------------------------------------
| docs/43-brevo-parity-plan.md §43.4.9 — Contacts › Segments list and the
| segment builder. Required from routes/web/app.php (inside `auth`).
| Literal `new` is registered before the `{segment}` route.
*/

Route::get('contacts/segments', fn () => Inertia::render('Contacts/Segments'))
    ->middleware('can:recipients.view')
    ->name('contacts.segments');

Route::get('contacts/segments/new', fn () => Inertia::render('Contacts/SegmentEdit', ['uuid' => null]))
    ->middleware('can:segments.manage')
    ->name('contacts.segments.create');

Route::get('contacts/segments/{segment}', fn (string $segment) => Inertia::render('Contacts/SegmentEdit', ['uuid' => $segment]))
    ->whereUuid('segment')
    ->middleware('can:segments.manage')
    ->name('contacts.segments.edit');
