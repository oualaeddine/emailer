<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Campaigns Web Routes (Inertia pages)
|--------------------------------------------------------------------------
| docs/15-campaign-management.md, docs/42-parallel-execution-plan.md §42.7
| (WP-23). Required from routes/web/app.php inside the `auth` middleware
| group.
*/

Route::get('campaigns', fn () => Inertia::render('Campaigns/Index'))->name('campaigns.index');
