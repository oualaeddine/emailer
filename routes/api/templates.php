<?php

use App\Modules\Templates\Http\Controllers\TemplateController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Templates API Routes
|--------------------------------------------------------------------------
| Required from routes/api.php inside the `v1` + `auth:sanctum` group
| (docs/42-parallel-execution-plan.md §42.5/§42.11).
*/

Route::get('templates', [TemplateController::class, 'index']);
Route::post('templates', [TemplateController::class, 'store']);
Route::patch('templates/{template}', [TemplateController::class, 'update']);
Route::post('templates/{template}/archive', [TemplateController::class, 'archive']);
Route::get('templates/{template}/versions', [TemplateController::class, 'versions']);
Route::delete('templates/{template}', [TemplateController::class, 'destroy']);
