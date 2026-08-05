<?php

use App\Modules\Composer\Http\Controllers\DraftController;
use App\Modules\Composer\Http\Controllers\SignatureController;
use App\Modules\DeliveryEngine\Http\Controllers\SmtpAccountController;
use App\Modules\DeliveryEngine\Http\Controllers\WarmupScheduleController;
use App\Modules\Identity\Http\Controllers\MeController;
use App\Modules\Identity\Http\Controllers\RoleController;
use App\Modules\Identity\Http\Controllers\UserController;
use App\Modules\Importing\Http\Controllers\ImportJobController;
use App\Modules\PageJaunesIntegration\Http\Controllers\PageJaunesSearchController;
use App\Modules\Recipients\Http\Controllers\RecipientController;
use App\Modules\Recipients\Http\Controllers\RecipientListController;
use App\Modules\Recipients\Http\Controllers\RecipientNoteController;
use App\Modules\Recipients\Http\Controllers\SegmentController;
use App\Modules\Recipients\Http\Controllers\TagController;
use App\Modules\Settings\Http\Controllers\SettingsController;
use App\Modules\Templates\Http\Controllers\TemplateController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
| docs/29-api-specification.md §29.1 — base path /api/v1, Sanctum session
| auth via the `auth:sanctum` guard (stateful SPA requests).
*/

Route::prefix('v1')->middleware('auth:sanctum')->group(function (): void {
    Route::get('me', MeController::class);

    Route::get('users', [UserController::class, 'index']);
    Route::post('users', [UserController::class, 'store']);
    Route::patch('users/{user}', [UserController::class, 'update']);

    Route::get('roles', [RoleController::class, 'index']);

    Route::get('settings', [SettingsController::class, 'index']);
    Route::patch('settings', [SettingsController::class, 'update']);

    Route::get('pagejaunes/search', PageJaunesSearchController::class);

    Route::get('recipients', [RecipientController::class, 'index']);
    Route::post('recipients', [RecipientController::class, 'store']);
    Route::get('recipients/{uuid}', [RecipientController::class, 'show']);
    Route::patch('recipients/{recipient}', [RecipientController::class, 'update']);
    Route::get('recipients/{uuid}/notes', [RecipientNoteController::class, 'index']);
    Route::post('recipients/{uuid}/notes', [RecipientNoteController::class, 'store']);

    Route::get('tags', [TagController::class, 'index']);
    Route::post('tags', [TagController::class, 'store']);

    Route::get('recipient-lists', [RecipientListController::class, 'index']);
    Route::post('recipient-lists', [RecipientListController::class, 'store']);

    Route::post('segments/preview', [SegmentController::class, 'preview']);

    Route::get('import-jobs', [ImportJobController::class, 'index']);
    Route::post('import-jobs', [ImportJobController::class, 'store']);
    Route::put('import-jobs/{uuid}/mapping', [ImportJobController::class, 'updateMapping']);
    Route::get('import-jobs/{uuid}/rows', [ImportJobController::class, 'rows']);
    Route::post('import-jobs/{uuid}/commit', [ImportJobController::class, 'commit']);

    Route::get('drafts', [DraftController::class, 'index']);
    Route::post('drafts', [DraftController::class, 'store']);
    Route::patch('drafts/{draft}', [DraftController::class, 'update']);
    Route::post('drafts/{draft}/versions', [DraftController::class, 'saveVersion']);
    Route::get('drafts/{draft}/versions', [DraftController::class, 'versions']);
    Route::post('drafts/{draft}/versions/{versionId}/restore', [DraftController::class, 'restoreVersion']);
    Route::post('drafts/{draft}/send', [DraftController::class, 'send']);

    Route::get('signatures', [SignatureController::class, 'index']);
    Route::post('signatures', [SignatureController::class, 'store']);
    Route::patch('signatures/{signature}', [SignatureController::class, 'update']);

    Route::get('templates', [TemplateController::class, 'index']);
    Route::post('templates', [TemplateController::class, 'store']);
    Route::patch('templates/{template}', [TemplateController::class, 'update']);
    Route::post('templates/{template}/archive', [TemplateController::class, 'archive']);
    Route::get('templates/{template}/versions', [TemplateController::class, 'versions']);
    Route::delete('templates/{template}', [TemplateController::class, 'destroy']);

    Route::get('smtp-accounts', [SmtpAccountController::class, 'index']);
    Route::post('smtp-accounts', [SmtpAccountController::class, 'store']);
    Route::patch('smtp-accounts/{account}', [SmtpAccountController::class, 'update']);
    Route::delete('smtp-accounts/{account}', [SmtpAccountController::class, 'destroy']);
    Route::post('smtp-accounts/{account}/test', [SmtpAccountController::class, 'test']);

    Route::get('warmup-schedules', [WarmupScheduleController::class, 'index']);
    Route::post('warmup-schedules', [WarmupScheduleController::class, 'store']);
});
