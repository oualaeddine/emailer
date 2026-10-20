<?php

use App\Modules\DeliveryEngine\Scheduling\Http\Controllers\BestTimePreviewController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Send at best time API Routes
|--------------------------------------------------------------------------
| Required from routes/api.php inside the `v1` + `auth:sanctum` group.
| docs/45-brevo-api-contracts.md §45.7.3 — WP-81 read-only helper.
*/

Route::get('campaigns/{campaign}/best-time-preview', [BestTimePreviewController::class, 'show']);
