<?php

use App\Modules\Templates\Sections\Http\Controllers\PremadeSectionController;
use App\Modules\Templates\Sections\Http\Controllers\SavedSectionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| EmailBuilder sections API Routes (WP-61)
|--------------------------------------------------------------------------
| docs/45-brevo-api-contracts.md §45.7.1 — saved sections (*Enregistrés*)
| and the static pre-made layouts (*Sections*). Required from routes/api.php
| inside the `v1` + `auth:sanctum` group. Permissions are checked by
| SavedSectionPolicy (templates.view | campaigns.create to list,
| templates.manage | campaigns.create to save, owner | templates.manage to
| rename / delete).
*/

Route::get('editor/sections', PremadeSectionController::class)->name('editor.sections');

Route::get('saved-sections', [SavedSectionController::class, 'index'])->name('saved-sections.index');
Route::post('saved-sections', [SavedSectionController::class, 'store'])->name('saved-sections.store');
Route::patch('saved-sections/{section}', [SavedSectionController::class, 'update'])->name('saved-sections.update');
Route::delete('saved-sections/{section}', [SavedSectionController::class, 'destroy'])->name('saved-sections.destroy');
