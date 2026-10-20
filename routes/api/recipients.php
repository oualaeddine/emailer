<?php

use App\Modules\Recipients\Http\Controllers\RecipientController;
use App\Modules\Recipients\Http\Controllers\RecipientNoteController;
use App\Modules\Recipients\Http\Controllers\TagController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Recipients API Routes (recipients, tags, recipient-lists, segments)
|--------------------------------------------------------------------------
| Required from routes/api.php inside the `v1` + `auth:sanctum` group
| (docs/42-parallel-execution-plan.md §42.5/§42.11).
*/

Route::get('recipients', [RecipientController::class, 'index'])
    ->middleware(\App\Modules\Transactional\Http\Middleware\RequireApiScope::class.':contacts:read');
Route::post('recipients', [RecipientController::class, 'store'])
    ->middleware(\App\Modules\Transactional\Http\Middleware\RequireApiScope::class.':contacts:write');
Route::get('recipients/{uuid}', [RecipientController::class, 'show'])
    ->middleware(\App\Modules\Transactional\Http\Middleware\RequireApiScope::class.':contacts:read');
Route::patch('recipients/{recipient}', [RecipientController::class, 'update'])
    ->middleware(\App\Modules\Transactional\Http\Middleware\RequireApiScope::class.':contacts:write');
Route::get('recipients/{uuid}/notes', [RecipientNoteController::class, 'index']);
Route::post('recipients/{uuid}/notes', [RecipientNoteController::class, 'store']);

Route::get('tags', [TagController::class, 'index']);
Route::post('tags', [TagController::class, 'store']);


