<?php

use App\Modules\Audit\Http\Controllers\AuditLogController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Audit API Routes
|--------------------------------------------------------------------------
| docs/29-api-specification.md §29.2 (lines 177-178).
| Required from routes/api.php inside the `v1` + `auth:sanctum` group
| (docs/42-parallel-execution-plan.md §42.5/§42.11).
*/

Route::get('audit-logs', [AuditLogController::class, 'index']);
Route::post('audit-logs/export', [AuditLogController::class, 'export']);
