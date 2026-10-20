<?php

use App\Modules\Forms\Http\Controllers\CaptchaSettingsController;
use App\Modules\Forms\Http\Controllers\FormController;
use Illuminate\Support\Facades\Route;

/*
| docs/45 §45.7.4 — WP-90 subscription forms. Required from routes/api.php
| inside the `v1` + `auth:sanctum` group.
*/

// Before `forms/{id}`: the literal path must not be taken as a form id.
Route::get('forms/captcha-settings', [CaptchaSettingsController::class, 'show']);
Route::put('forms/captcha-settings', [CaptchaSettingsController::class, 'update']);

Route::get('forms', [FormController::class, 'index']);
Route::post('forms', [FormController::class, 'store']);
Route::get('forms/{id}', [FormController::class, 'show']);
Route::patch('forms/{id}', [FormController::class, 'update']);
Route::delete('forms/{id}', [FormController::class, 'destroy']);
Route::post('forms/{id}/duplicate', [FormController::class, 'duplicate']);
Route::post('forms/{id}/publish', [FormController::class, 'publish']);
Route::post('forms/{id}/unpublish', [FormController::class, 'unpublish']);
Route::get('forms/{id}/submissions', [FormController::class, 'submissions']);
