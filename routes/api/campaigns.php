<?php

use App\Modules\Campaigns\Http\Controllers\CampaignController;
use App\Modules\Campaigns\Http\Controllers\CampaignFolderController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Campaigns API Routes
|--------------------------------------------------------------------------
| Required from routes/api.php inside the `v1` + `auth:sanctum` group
| (docs/42-parallel-execution-plan.md §42.5/§42.11).
| docs/15-campaign-management.md — Campaign Management module;
| docs/45-brevo-api-contracts.md §45.4.2 — Campaigns v2 (WP-42).
| Literal sub-paths are registered before the `{campaign}` routes (§45.1).
*/

Route::get('campaigns', [CampaignController::class, 'index']);
Route::post('campaigns', [CampaignController::class, 'store']);
Route::post('campaigns/bulk', [CampaignController::class, 'bulk']);

Route::get('campaign-folders', [CampaignFolderController::class, 'index']);
Route::post('campaign-folders', [CampaignFolderController::class, 'store']);
Route::patch('campaign-folders/{folder}', [CampaignFolderController::class, 'update']);
Route::delete('campaign-folders/{folder}', [CampaignFolderController::class, 'destroy']);

Route::get('campaigns/{campaign}', [CampaignController::class, 'show']);
Route::patch('campaigns/{campaign}', [CampaignController::class, 'update']);
Route::delete('campaigns/{campaign}', [CampaignController::class, 'destroy']);
Route::put('campaigns/{campaign}/design', [CampaignController::class, 'saveDesign']);
Route::post('campaigns/{campaign}/design/copy', [CampaignController::class, 'copyDesign']);
Route::get('campaigns/{campaign}/preview', [CampaignController::class, 'preview']);
Route::post('campaigns/{campaign}/audience/estimate', [CampaignController::class, 'estimateAudience']);
Route::post('campaigns/{campaign}/test-send', [CampaignController::class, 'testSend']);
Route::post('campaigns/{campaign}/schedule', [CampaignController::class, 'schedule']);
Route::post('campaigns/{campaign}/unschedule', [CampaignController::class, 'unschedule']);
Route::post('campaigns/{campaign}/send', [CampaignController::class, 'send']);
Route::post('campaigns/{campaign}/pause', [CampaignController::class, 'pause']);
Route::post('campaigns/{campaign}/resume', [CampaignController::class, 'resume']);
Route::post('campaigns/{campaign}/cancel', [CampaignController::class, 'cancel']);
Route::post('campaigns/{campaign}/approve', [CampaignController::class, 'approve']);
Route::post('campaigns/{campaign}/duplicate', [CampaignController::class, 'duplicate']);
Route::post('campaigns/{campaign}/clone', [CampaignController::class, 'clone']);
Route::post('campaigns/{campaign}/send-to-new-contacts', [CampaignController::class, 'sendToNewContacts']);
Route::post('campaigns/{campaign}/archive', [CampaignController::class, 'archive']);
Route::post('campaigns/{campaign}/unarchive', [CampaignController::class, 'unarchive']);
Route::get('campaigns/{campaign}/recipients', [CampaignController::class, 'recipients']);
Route::get('campaigns/{campaign}/analytics', [CampaignController::class, 'analytics']);
