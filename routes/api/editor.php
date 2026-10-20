<?php

use App\Modules\Templates\Http\Controllers\MjmlValidationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Email editor API Routes (WP-60)
|--------------------------------------------------------------------------
| docs/45-brevo-api-contracts.md §45.6.1 — server-side MJML validation used
| by the EmailBuilder on every save. Required from routes/api.php inside the
| `v1` + `auth:sanctum` group. Permission (`templates.manage` |
| `campaigns.create`) is checked by ValidateMjmlRequest. Each call spawns a
| Node process, hence the throttle.
*/

Route::post('templates/mjml/validate', MjmlValidationController::class)
    ->middleware('throttle:60,1')
    ->name('templates.mjml.validate');
