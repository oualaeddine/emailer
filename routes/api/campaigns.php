<?php

use App\Modules\Campaigns\Http\Controllers\CampaignController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Campaigns API Routes
|--------------------------------------------------------------------------
| Required from routes/api.php inside the `v1` + `auth:sanctum` group
| (docs/42-parallel-execution-plan.md §42.5/§42.11).
| docs/15-campaign-management.md — Campaign Management module.
*/

Route::get('campaigns', [CampaignController::class, 'index']);
Route::post('campaigns', [CampaignController::class, 'store']);
Route::get('campaigns/{campaign}', [CampaignController::class, 'show']);
Route::patch('campaigns/{campaign}', [CampaignController::class, 'update']);
Route::post('campaigns/{campaign}/schedule', [CampaignController::class, 'schedule']);
Route::post('campaigns/{campaign}/send', [CampaignController::class, 'send']);
Route::post('campaigns/{campaign}/pause', [CampaignController::class, 'pause']);
Route::post('campaigns/{campaign}/resume', [CampaignController::class, 'resume']);
Route::post('campaigns/{campaign}/cancel', [CampaignController::class, 'cancel']);
Route::post('campaigns/{campaign}/approve', [CampaignController::class, 'approve']);
Route::post('campaigns/{campaign}/clone', [CampaignController::class, 'clone']);
Route::get('campaigns/{campaign}/recipients', [CampaignController::class, 'recipients']);
Route::get('campaigns/{campaign}/analytics', [CampaignController::class, 'analytics']);
