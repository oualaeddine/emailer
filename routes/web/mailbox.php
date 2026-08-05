<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Mailbox Web Routes (Inertia page)
|--------------------------------------------------------------------------
| docs/10-mailbox.md — three-pane mailbox.
| Required from routes/web/app.php inside the `auth` middleware group
| (docs/42-parallel-execution-plan.md §42.6/§42.11).
*/

Route::get('mailbox', fn () => Inertia::render('Mailbox/Index'))->name('mailbox.index');
