<?php

use App\Modules\Composer\Http\Controllers\DraftController;
use App\Modules\Composer\Http\Controllers\SignatureController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Composer API Routes (drafts, signatures)
|--------------------------------------------------------------------------
| Required from routes/api.php inside the `v1` + `auth:sanctum` group
| (docs/42-parallel-execution-plan.md §42.5/§42.11).
*/

Route::get('drafts', [DraftController::class, 'index']);
Route::post('drafts', [DraftController::class, 'store']);
Route::patch('drafts/{draft}', [DraftController::class, 'update']);
Route::post('drafts/{draft}/versions', [DraftController::class, 'saveVersion']);
Route::get('drafts/{draft}/versions', [DraftController::class, 'versions']);
Route::post('drafts/{draft}/versions/{versionId}/restore', [DraftController::class, 'restoreVersion']);
Route::post('drafts/{draft}/send', [DraftController::class, 'send']);

Route::get('signatures', [SignatureController::class, 'index']);
Route::post('signatures', [SignatureController::class, 'store']);
Route::patch('signatures/{signature}', [SignatureController::class, 'update']);
