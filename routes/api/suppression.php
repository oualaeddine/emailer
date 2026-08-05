<?php

use App\Modules\Suppression\Http\Controllers\SuppressionEntryController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Suppression API Routes
|--------------------------------------------------------------------------
| Required from routes/api.php inside the `v1` + `auth:sanctum` group
| (docs/42-parallel-execution-plan.md §42.5/§42.11).
| docs/29-api-specification.md §29.8.
*/

Route::get('suppression-entries', [SuppressionEntryController::class, 'index']);
Route::post('suppression-entries', [SuppressionEntryController::class, 'store']);
Route::delete('suppression-entries/{uuid}', [SuppressionEntryController::class, 'destroy']);
