<?php

use App\Modules\Notifications\Http\Controllers\NotificationController;
use App\Modules\Notifications\Http\Controllers\NotificationPreferencesController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Notifications API Routes
|--------------------------------------------------------------------------
| Required from routes/api.php inside the `v1` + `auth:sanctum` group
| (docs/42-parallel-execution-plan.md §42.5/§42.11).
| docs/41-notification-center.md §41.5 — bell panel + §41.4 personal
| preferences, both scoped entirely to `$request->user()`; no dedicated
| permission exists or is needed beyond the group's `auth:sanctum` guard.
*/

Route::get('notifications', [NotificationController::class, 'index']);
Route::get('notifications/unread-count', [NotificationController::class, 'unreadCount']);
Route::post('notifications/mark-all-read', [NotificationController::class, 'markAllAsRead']);
Route::patch('notifications/{id}/read', [NotificationController::class, 'markAsRead']);

Route::get('notification-preferences', [NotificationPreferencesController::class, 'index']);
Route::patch('notification-preferences', [NotificationPreferencesController::class, 'update']);
