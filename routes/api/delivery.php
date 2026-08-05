<?php

use App\Modules\DeliveryEngine\Http\Controllers\SmtpAccountController;
use App\Modules\DeliveryEngine\Http\Controllers\WarmupScheduleController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Delivery Engine API Routes (smtp-accounts, warmup-schedules)
|--------------------------------------------------------------------------
| Required from routes/api.php inside the `v1` + `auth:sanctum` group
| (docs/42-parallel-execution-plan.md §42.5/§42.11).
*/

Route::get('smtp-accounts', [SmtpAccountController::class, 'index']);
Route::post('smtp-accounts', [SmtpAccountController::class, 'store']);
Route::patch('smtp-accounts/{account}', [SmtpAccountController::class, 'update']);
Route::delete('smtp-accounts/{account}', [SmtpAccountController::class, 'destroy']);
Route::post('smtp-accounts/{account}/test', [SmtpAccountController::class, 'test']);

Route::get('warmup-schedules', [WarmupScheduleController::class, 'index']);
Route::post('warmup-schedules', [WarmupScheduleController::class, 'store']);
