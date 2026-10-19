<?php

use App\Modules\Importing\Http\Controllers\ImportJobController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Importing API Routes
|--------------------------------------------------------------------------
| Required from routes/api.php inside the `v1` + `auth:sanctum` group
| (docs/42-parallel-execution-plan.md §42.5/§42.11).
*/

Route::get('import-jobs', [ImportJobController::class, 'index']);
Route::post('import-jobs', [ImportJobController::class, 'store']);
Route::get('import-jobs/{uuid}', [ImportJobController::class, 'show']);
Route::get('import-jobs/{uuid}/preview', [ImportJobController::class, 'preview']);
Route::put('import-jobs/{uuid}/settings', [ImportJobController::class, 'updateSettings']);
Route::get('import-jobs/{uuid}/errors.csv', [ImportJobController::class, 'errorsCsv'])->name('import-jobs.errors');
Route::put('import-jobs/{uuid}/mapping', [ImportJobController::class, 'updateMapping']);
Route::get('import-jobs/{uuid}/rows', [ImportJobController::class, 'rows']);
Route::post('import-jobs/{uuid}/commit', [ImportJobController::class, 'commit']);
