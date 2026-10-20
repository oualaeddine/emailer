<?php

use App\Modules\Reporting\Http\Controllers\ReportController;
use App\Modules\Reporting\Http\Controllers\ReportExportController;
use App\Modules\Reporting\Http\Controllers\ReportingController;
use App\Modules\Reporting\Http\Controllers\StatisticsController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Reporting API Routes
|--------------------------------------------------------------------------
| Required from routes/api.php inside the `v1` + `auth:sanctum` group
| (docs/42-parallel-execution-plan.md §42.5/§42.11).
| docs/25-reporting.md — Reporting module (legacy `/reporting/*`, kept until
| WP-73 retires the page);
| docs/45-brevo-api-contracts.md §45.6.5 — campaign report + statistics (WP-72).
*/

Route::get('reporting/summary', [ReportingController::class, 'summary']);
Route::get('reporting/campaigns', [ReportingController::class, 'campaigns']);
Route::get('reporting/smtp-accounts', [ReportingController::class, 'smtpAccounts']);
Route::get('reporting/export', [ReportingController::class, 'export']);

// Campaign report (`reporting.view` | `campaigns.view`).
Route::get('campaigns/{campaign}/report/overview', [ReportController::class, 'overview']);
Route::get('campaigns/{campaign}/report/deliverability', [ReportController::class, 'deliverability']);
Route::get('campaigns/{campaign}/report/opens', [ReportController::class, 'opens']);
Route::get('campaigns/{campaign}/report/clicks', [ReportController::class, 'clicks']);
Route::get('campaigns/{campaign}/report/unsubscriptions', [ReportController::class, 'unsubscriptions']);
Route::get('campaigns/{campaign}/report/unsubscribes', [ReportController::class, 'unsubscriptions']);
Route::get('campaigns/{campaign}/report/contacts', [ReportController::class, 'contacts']);
Route::post('campaigns/{campaign}/report/segment', [ReportController::class, 'segment']);
Route::get('campaigns/{campaign}/report/export', [ReportExportController::class, 'campaign']);

// `campaigns/compare` is a literal path that must win over `campaigns/{campaign}` (docs/45 §45.1), but
// routes/api/campaigns.php (WP-42, required first) registers `{campaign}` before this file runs: it is moved to the
// front of the route collection so route matching (first match wins) reaches it.
$compare = Route::get('campaigns/compare', [ReportController::class, 'compare']);
// `campaigns/compare` is kept out of `campaigns/{campaign}` by the global UUID
// pattern registered in AppServiceProvider::boot (Wave 3 gate).

// Global statistics (`reporting.view`).
Route::get('statistics/summary', [StatisticsController::class, 'summary']);
Route::get('statistics/overview', [StatisticsController::class, 'summary']);
Route::get('statistics/timeseries', [StatisticsController::class, 'timeseries']);
Route::get('statistics/campaigns', [StatisticsController::class, 'campaigns']);
Route::get('statistics/lists', [StatisticsController::class, 'lists']);
Route::get('statistics/averages', [StatisticsController::class, 'averages']);
Route::get('statistics/export', [ReportExportController::class, 'statistics']);
