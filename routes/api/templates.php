<?php

use App\Modules\Templates\Http\Controllers\TemplateController;
use App\Modules\Templates\Http\Controllers\TemplateFolderController;
use App\Modules\Templates\Http\Controllers\TemplateGalleryController;
use App\Modules\Templates\Http\Controllers\TemplateImportController;
use App\Modules\Templates\Http\Controllers\TemplateThumbnailController;
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

// Literal sub-paths before the {template} routes (docs/45 §45.1).
Route::post('templates/import-from-url', [TemplateImportController::class, 'fromUrl']);
Route::post('templates/import-zip', [TemplateImportController::class, 'zip']);
Route::get('templates/gallery', [TemplateGalleryController::class, 'index']);

// Folders (docs/45 §45.6.2, WP-62).
Route::get('template-folders', [TemplateFolderController::class, 'index']);
Route::post('template-folders', [TemplateFolderController::class, 'store']);
Route::patch('template-folders/{id}', [TemplateFolderController::class, 'update']);
Route::delete('template-folders/{id}', [TemplateFolderController::class, 'destroy']);

Route::get('templates/{template}', [TemplateController::class, 'show']);
Route::patch('templates/{template}', [TemplateController::class, 'update']);
Route::post('templates/{template}/duplicate', [TemplateController::class, 'duplicate']);
Route::post('templates/{template}/test-send', [TemplateController::class, 'testSend']);
Route::post('templates/{template}/archive', [TemplateController::class, 'archive']);
Route::post('templates/{template}/unarchive', [TemplateController::class, 'unarchive']);
Route::get('templates/{template}/versions', [TemplateController::class, 'versions']);
Route::post('templates/{template}/versions/{version}/restore', [TemplateController::class, 'restoreVersion'])->whereNumber('version');
Route::post('templates/{template}/thumbnail', [TemplateThumbnailController::class, 'store']);
Route::delete('templates/{template}', [TemplateController::class, 'destroy']);
