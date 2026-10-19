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

Route::get('campaigns', fn () => Inertia::render('Campaigns/Index'))
    ->middleware('can:campaigns.view')
    ->name('campaigns.index');

// docs/43-brevo-parity-plan.md §43.4.2 / docs/45 §45.4.3 (WP-43) — Brevo
// "campaign dashboard": the five setup cards, checklist and Confirmer l'envoi.
Route::get('campaigns/{campaign}/setup', fn (string $campaign) => Inertia::render('Campaigns/Setup', [
    'campaignId' => $campaign,
]))
    ->middleware('can:campaigns.view')
    ->name('campaigns.setup');
