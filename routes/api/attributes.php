<?php

use App\Modules\Recipients\Attributes\Http\Controllers\ContactAttributeController;
use Illuminate\Support\Facades\Route;

/*
| docs/45 §45.5.1 — WP-50 Contact attributes. Required from routes/api.php inside the
| `v1` + `auth:sanctum` group. The literal `merge-tags` route precedes `{id}`.
*/

Route::get('contact-attributes', [ContactAttributeController::class, 'index']);
Route::post('contact-attributes', [ContactAttributeController::class, 'store']);
Route::get('contact-attributes/merge-tags', [ContactAttributeController::class, 'mergeTags']);
Route::get('contact-attributes/{id}', [ContactAttributeController::class, 'show']);
Route::patch('contact-attributes/{id}', [ContactAttributeController::class, 'update']);
Route::delete('contact-attributes/{id}', [ContactAttributeController::class, 'destroy']);
