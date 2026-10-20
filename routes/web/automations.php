<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Automations web routes (WP-91U)
|--------------------------------------------------------------------------
| docs/43-brevo-parity-plan.md §43.4.19 / §43.3 — Automatisations list and
| the linear workflow editor. Required from routes/web/app.php (inside
| `auth`). Every page needs `automations.manage` (docs/45 §45.2), the list
| included. Literal `new` is registered before the `{automation}` route.
*/

Route::get('automations', fn () => Inertia::render('Automations/Index'))
    ->middleware('can:automations.manage')
    ->name('automations.index');

Route::get('automations/new', fn () => Inertia::render('Automations/Edit', ['uuid' => null]))
    ->middleware('can:automations.manage')
    ->name('automations.create');

Route::get('automations/{automation}', fn (string $automation) => Inertia::render('Automations/Edit', ['uuid' => $automation]))
    ->whereUuid('automation')
    ->middleware('can:automations.manage')
    ->name('automations.edit');
