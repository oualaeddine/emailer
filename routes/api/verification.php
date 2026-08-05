<?php

use App\Modules\Verification\Http\Controllers\VerificationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Verification API Routes
|--------------------------------------------------------------------------
| Required from routes/api.php inside the `v1` + `auth:sanctum` group
| (docs/42-parallel-execution-plan.md §42.5/§42.11).
|
| docs/29-api-specification.md §29.4 — the single-verify endpoint's
| documented contract is `/api/v1/recipients/{uuid}/verify`, which is
| declared here rather than in `routes/api/recipients.php` (out of this
| work package's file scope, §42.8) — a route's URI segment doesn't have to
| match the file it's declared in.
*/

Route::post('recipients/{uuid}/verify', [VerificationController::class, 'verifySingle']);
Route::post('recipients/verify-bulk', [VerificationController::class, 'verifyBulk']);
