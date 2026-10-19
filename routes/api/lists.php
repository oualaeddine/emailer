<?php

use App\Modules\Recipients\Lists\Http\Controllers\ListController;
use App\Modules\Recipients\Lists\Http\Controllers\ListExportController;
use App\Modules\Recipients\Lists\Http\Controllers\ListFolderController;
use App\Modules\Recipients\Lists\Http\Controllers\ListMembershipController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Lists & folders API (docs/45-brevo-api-contracts.md §45.5.6, WP-53)
|--------------------------------------------------------------------------
| Required from routes/api.php inside the `v1` + `auth:sanctum` group.
| Re-homes the legacy `recipient-lists` index/store (the orchestrator removes
| the legacy lines from routes/api/recipients.php at the gate).
*/

Route::get('recipient-lists', [ListController::class, 'index']);
Route::post('recipient-lists', [ListController::class, 'store']);
// Literal sub-paths before the {id} routes.
Route::get('recipient-lists/exports/{operation}/download', [ListExportController::class, 'download'])
    ->middleware('signed:relative')->name('lists.exports.download');
Route::get('recipient-lists/{id}', [ListController::class, 'show']);
Route::patch('recipient-lists/{id}', [ListController::class, 'update']);
Route::delete('recipient-lists/{id}', [ListController::class, 'destroy']);
Route::post('recipient-lists/{id}/contacts', [ListMembershipController::class, 'update']);
Route::post('recipient-lists/{id}/export', [ListExportController::class, 'store']);

Route::get('list-folders', [ListFolderController::class, 'index']);
Route::post('list-folders', [ListFolderController::class, 'store']);
Route::patch('list-folders/{id}', [ListFolderController::class, 'update']);
Route::delete('list-folders/{id}', [ListFolderController::class, 'destroy']);
