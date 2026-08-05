<?php

use App\Modules\Recipients\Http\Controllers\RecipientController;
use App\Modules\Recipients\Http\Controllers\RecipientListController;
use App\Modules\Recipients\Http\Controllers\RecipientNoteController;
use App\Modules\Recipients\Http\Controllers\SegmentController;
use App\Modules\Recipients\Http\Controllers\TagController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Recipients API Routes (recipients, tags, recipient-lists, segments)
|--------------------------------------------------------------------------
| Required from routes/api.php inside the `v1` + `auth:sanctum` group
| (docs/42-parallel-execution-plan.md §42.5/§42.11).
*/

Route::get('recipients', [RecipientController::class, 'index']);
Route::post('recipients', [RecipientController::class, 'store']);
Route::get('recipients/{uuid}', [RecipientController::class, 'show']);
Route::patch('recipients/{recipient}', [RecipientController::class, 'update']);
Route::get('recipients/{uuid}/notes', [RecipientNoteController::class, 'index']);
Route::post('recipients/{uuid}/notes', [RecipientNoteController::class, 'store']);

Route::get('tags', [TagController::class, 'index']);
Route::post('tags', [TagController::class, 'store']);

Route::get('recipient-lists', [RecipientListController::class, 'index']);
Route::post('recipient-lists', [RecipientListController::class, 'store']);

Route::post('segments/preview', [SegmentController::class, 'preview']);
