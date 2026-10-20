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
Route::get('campaigns/{campaign}/report/overview', [ReportController::class, 'overview'])
    ->middleware(\App\Modules\Transactional\Http\Middleware\RequireApiScope::class.':campaigns:read');
Route::get('campaigns/{campaign}/report/deliverability', [ReportController::class, 'deliverability'])
    ->middleware(\App\Modules\Transactional\Http\Middleware\RequireApiScope::class.':campaigns:read');
Route::get('campaigns/{campaign}/report/opens', [ReportController::class, 'opens'])
    ->middleware(\App\Modules\Transactional\Http\Middleware\RequireApiScope::class.':campaigns:read');
Route::get('campaigns/{campaign}/report/clicks', [ReportController::class, 'clicks'])
    ->middleware(\App\Modules\Transactional\Http\Middleware\RequireApiScope::class.':campaigns:read');
Route::get('campaigns/{campaign}/report/unsubscriptions', [ReportController::class, 'unsubscriptions'])
    ->middleware(\App\Modules\Transactional\Http\Middleware\RequireApiScope::class.':campaigns:read');
Route::get('campaigns/{campaign}/report/unsubscribes', [ReportController::class, 'unsubscriptions'])
    ->middleware(\App\Modules\Transactional\Http\Middleware\RequireApiScope::class.':campaigns:read');
Route::get('campaigns/{campaign}/report/contacts', [ReportController::class, 'contacts'])
    ->middleware(\App\Modules\Transactional\Http\Middleware\RequireApiScope::class.':campaigns:read');
Route::post('campaigns/{campaign}/report/segment', [ReportController::class, 'segment']);
Route::get('campaigns/{campaign}/report/export', [ReportExportController::class, 'campaign'])
    ->middleware(\App\Modules\Transactional\Http\Middleware\RequireApiScope::class.':campaigns:read');

// `campaigns/compare` is kept out of `campaigns/{campaign}` by the global UUID
// pattern registered in AppServiceProvider::boot (Wave 3 gate).
Route::get('campaigns/compare', [ReportController::class, 'compare']);

// Global statistics (`reporting.view`).
Route::get('statistics/summary', [StatisticsController::class, 'summary'])
    ->middleware(\App\Modules\Transactional\Http\Middleware\RequireApiScope::class.':statistics:read');
Route::get('statistics/overview', [StatisticsController::class, 'summary'])
    ->middleware(\App\Modules\Transactional\Http\Middleware\RequireApiScope::class.':statistics:read');
Route::get('statistics/timeseries', [StatisticsController::class, 'timeseries'])
    ->middleware(\App\Modules\Transactional\Http\Middleware\RequireApiScope::class.':statistics:read');
Route::get('statistics/campaigns', [StatisticsController::class, 'campaigns'])
    ->middleware(\App\Modules\Transactional\Http\Middleware\RequireApiScope::class.':statistics:read');
Route::get('statistics/lists', [StatisticsController::class, 'lists'])
    ->middleware(\App\Modules\Transactional\Http\Middleware\RequireApiScope::class.':statistics:read');
Route::get('statistics/averages', [StatisticsController::class, 'averages'])
    ->middleware(\App\Modules\Transactional\Http\Middleware\RequireApiScope::class.':statistics:read');
Route::get('statistics/export', [ReportExportController::class, 'statistics'])
    ->middleware(\App\Modules\Transactional\Http\Middleware\RequireApiScope::class.':statistics:read');
