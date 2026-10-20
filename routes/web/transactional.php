<?php

use App\Domain\Enums\PermissionName;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
| docs/43 §43.4.17 (WP-100) — SMTP & API settings page. Required from
| routes/web/app.php, itself inside the `auth` middleware group.
| Reachable with `api_keys.manage` OR `transactional.view`.
*/

Route::get('transactional/settings', function (Request $request) {
    abort_unless(
        Gate::forUser($request->user())->any([PermissionName::ApiKeysManage->value, PermissionName::TransactionalView->value]),
        403,
    );

    return Inertia::render('Transactional/Settings');
})->name('transactional.settings');
