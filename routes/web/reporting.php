<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Reporting Web Routes (Inertia pages)
|--------------------------------------------------------------------------
| docs/25-reporting.md, docs/42-parallel-execution-plan.md §42.8 (WP-31).
| Required from routes/web/app.php inside the `auth` middleware group.
*/

// docs/47 — `/statistics` is served by routes/web/statistics.php since Wave 3.
// (Route::get('statistics', …) → Reporting/Index removed; Reporting/Index.tsx is deleted.)

// docs/43 §43.3 — legacy URL, permanent redirect for one release.
Route::redirect('reporting', '/statistics', 301);
