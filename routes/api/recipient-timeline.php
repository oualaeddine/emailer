<?php

use App\Modules\Recipients\Http\Controllers\RecipientTimelineController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Recipient timeline API Route (WP-52)
|--------------------------------------------------------------------------
| docs/45-brevo-api-contracts.md §45.5.5. Required from routes/api.php inside
| the `v1` + `auth:sanctum` group.
*/

Route::get('recipients/{uuid}/timeline', [RecipientTimelineController::class, 'index']);
