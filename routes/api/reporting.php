<?php

use App\Modules\Reporting\Http\Controllers\ReportingController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Reporting API Routes
|--------------------------------------------------------------------------
| Required from routes/api.php inside the `v1` + `auth:sanctum` group
| (docs/42-parallel-execution-plan.md §42.5/§42.11).
| docs/25-reporting.md — Reporting module.
*/

Route::get('reporting/summary', [ReportingController::class, 'summary']);
Route::get('reporting/campaigns', [ReportingController::class, 'campaigns']);
Route::get('reporting/smtp-accounts', [ReportingController::class, 'smtpAccounts']);
Route::get('reporting/export', [ReportingController::class, 'export']);
