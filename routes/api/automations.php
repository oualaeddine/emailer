<?php

use App\Modules\Automations\Http\Controllers\AutomationController;
use App\Modules\Automations\Http\Controllers\AutomationRunController;
use Illuminate\Support\Facades\Route;

/*
| docs/45 §45.7.5 — WP-91 Automations. Required from routes/api.php inside
| the `v1` + `auth:sanctum` group. Permission `automations.manage` (§45.2).
*/

Route::get('automations', [AutomationController::class, 'index']);
Route::post('automations', [AutomationController::class, 'store']);
Route::get('automations/{id}', [AutomationController::class, 'show']);
Route::patch('automations/{id}', [AutomationController::class, 'update']);
Route::delete('automations/{id}', [AutomationController::class, 'destroy']);
Route::put('automations/{id}/steps', [AutomationController::class, 'replaceSteps']);
Route::post('automations/{id}/activate', [AutomationController::class, 'activate']);
Route::post('automations/{id}/pause', [AutomationController::class, 'pause']);
Route::post('automations/{id}/duplicate', [AutomationController::class, 'duplicate']);
Route::get('automations/{id}/activity', [AutomationController::class, 'activity']);
Route::get('automations/{id}/runs', [AutomationRunController::class, 'index']);
Route::post('automations/{id}/runs/{run}/exit', [AutomationRunController::class, 'exit']);
