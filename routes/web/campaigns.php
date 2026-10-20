<?php

use Illuminate\Support\Facades\Gate;
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

// docs/43-brevo-parity-plan.md §43.4.15 / docs/45 §45.6.5 (WP-71) — Brevo
// campaign report: Vue d'ensemble, Délivrabilité, Ouvertures, Clics,
// Désinscriptions, export per tab and compare (data from WP-72 endpoints).
// Same access as the report API: `reporting.view` OR `campaigns.view`.
Route::get('campaigns/{campaign}/report', function (string $campaign) {
    abort_unless(Gate::any(['reporting.view', 'campaigns.view']), 403);

    return Inertia::render('Campaigns/Report', ['campaignId' => $campaign]);
})->name('campaigns.report');
