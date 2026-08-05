<?php

use App\Modules\Queues\Http\Controllers\QueueStatsController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Queue Management API Routes
|--------------------------------------------------------------------------
| Required from routes/api.php inside the `v1` + `auth:sanctum` group
| (docs/42-parallel-execution-plan.md §42.5/§42.11).
| docs/29-api-specification.md — GET /api/v1/queues/metrics (`queues.view`).
*/

Route::get('queues/metrics', [QueueStatsController::class, 'index']);
