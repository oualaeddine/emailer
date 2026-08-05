<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Audit Log Routes (Inertia page)
|--------------------------------------------------------------------------
| docs/27-audit-logs.md §27.7 — Audit Log Viewer UI.
| Required from routes/web/app.php, itself inside the `auth` middleware
| group (routes/web.php).
*/

Route::get('admin/audit-log', fn () => Inertia::render('Admin/AuditLog'))->name('admin.audit-log');
