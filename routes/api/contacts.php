<?php

use App\Modules\Recipients\Http\Controllers\RecipientController;
use App\Modules\Recipients\Views\Http\Controllers\ColumnPreferenceController;
use App\Modules\Recipients\Views\Http\Controllers\ContactExportController;
use App\Modules\Recipients\Views\Http\Controllers\ContactViewController;
use App\Modules\Recipients\Views\Http\Controllers\RecipientBulkController;
use Illuminate\Support\Facades\Route;

/*
| docs/45 §45.5.4 — WP-57 contacts index search, views, bulk, export.
| Required from routes/api.php inside the `v1` + `auth:sanctum` group.
| Literal `recipients/*` paths are POST-only, so they never collide with the
| GET/PATCH `recipients/{uuid}` routes of recipients.php.
*/

Route::post('recipients/search', [RecipientController::class, 'search']);
Route::post('recipients/bulk', RecipientBulkController::class);
Route::post('recipients/export', [ContactExportController::class, 'store']);

// Signed 24 h link (no session). Lives under /api/v1 (web: route not in this WP's scope).
Route::get('exports/{operation}/download', [ContactExportController::class, 'download'])
    ->middleware('signed')
    ->withoutMiddleware('auth:sanctum')
    ->name('contacts.exports.download');

Route::get('operations/{id}', [ContactExportController::class, 'operation']);

Route::get('contact-views', [ContactViewController::class, 'index']);
Route::post('contact-views', [ContactViewController::class, 'store']);
Route::patch('contact-views/{id}', [ContactViewController::class, 'update']);
Route::delete('contact-views/{id}', [ContactViewController::class, 'destroy']);

Route::patch('me/column-preferences', ColumnPreferenceController::class);
