<?php

use App\Modules\Recipients\Segments\Http\Controllers\SegmentController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Segments v2 API Routes — WP-54 (docs/45-brevo-api-contracts.md §45.5.2)
|--------------------------------------------------------------------------
| Required from routes/api.php inside the `v1` + `auth:sanctum` group.
| Literal sub-paths are registered before the `{id}` routes (§45.1).
| `POST segments/preview` re-registers the legacy route of
| routes/api/recipients.php (same URI: this later registration wins), so the
| legacy line can be dropped there without behaviour change.
*/

Route::get('segments/fields', [SegmentController::class, 'fields']);
Route::post('segments/count', [SegmentController::class, 'countRules']);
Route::post('segments/preview', [SegmentController::class, 'preview']);

Route::get('segments', [SegmentController::class, 'index']);
Route::post('segments', [SegmentController::class, 'store']);
Route::get('segments/{id}', [SegmentController::class, 'show']);
Route::patch('segments/{id}', [SegmentController::class, 'update']);
Route::delete('segments/{id}', [SegmentController::class, 'destroy']);
Route::post('segments/{id}/count', [SegmentController::class, 'count']);
Route::get('segments/{id}/members', [SegmentController::class, 'members']);
Route::post('segments/{id}/duplicate', [SegmentController::class, 'duplicate']);
Route::post('segments/{id}/preview', [SegmentController::class, 'preview']);
