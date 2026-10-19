<?php

use App\Modules\Campaigns\Http\Controllers\CampaignChecklistController;
use Illuminate\Support\Facades\Route;

/*
| docs/45-brevo-api-contracts.md §45.4.5 — WP-45 pre-send checklist.
*/

Route::get('campaigns/{campaign}/checklist', [CampaignChecklistController::class, 'show']);
Route::post('campaigns/{campaign}/checklist/links', [CampaignChecklistController::class, 'checkLinks']);
