<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Forms builder web routes (WP-90U)
|--------------------------------------------------------------------------
| docs/43-brevo-parity-plan.md §43.4.11 / §43.3 — Marketing › Formulaires
| list and the subscription form builder. Required from routes/web/app.php
| (inside `auth`). The public hosted routes `/f/{form}` live in
| routes/web/forms.php (WP-90).
*/

Route::get('marketing/forms', fn () => Inertia::render('Marketing/Forms'))
    ->middleware('can:recipients.view') // data and actions require forms.manage (API)
    ->name('marketing.forms');

Route::get('marketing/forms/{form}', fn (string $form) => Inertia::render('Marketing/FormEdit', ['uuid' => $form]))
    ->whereUuid('form')
    ->middleware('can:forms.manage')
    ->name('marketing.forms.edit');
