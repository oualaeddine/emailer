<?php

use App\Modules\PageJaunesIntegration\Http\Controllers\PageJaunesSearchController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| PageJaunes Integration API Routes
|--------------------------------------------------------------------------
| Required from routes/api.php inside the `v1` + `auth:sanctum` group
| (docs/42-parallel-execution-plan.md §42.5/§42.11).
*/

Route::get('pagejaunes/search', PageJaunesSearchController::class);
