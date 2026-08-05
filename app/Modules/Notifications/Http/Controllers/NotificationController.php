<?php

namespace App\Modules\Notifications\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Notifications\Http\Resources\NotificationResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * docs/41-notification-center.md §41.5 — in-app Notification Center (bell
 * panel). Every endpoint is scoped entirely through
 * `$request->user()->notifications()` / `->unreadNotifications()` — the
 * `Notifiable` relation already declared on `App\Modules\Identity\Models\User`
 * keys on `notifiable_type`/`notifiable_id`, so a user can only ever see or
 * mutate their own rows. No cross-user access is possible, and no
 * dedicated `notifications.*` permission exists or is needed
 * (docs/26-rbac.md, docs/42-parallel-execution-plan.md §42.11 frozen
 * enum) — the `auth:sanctum` guard on the route group is the only gate.
 */
class NotificationController extends Controller
{
    /**
     * docs/41-notification-center.md §41.5 — reverse-chronological list
     * (the `notifications()` relation from `Illuminate\Notifications\HasDatabaseNotifications`
     * already orders by `created_at desc`).
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        return NotificationResource::collection(
            $request->user()->notifications()->paginate(20),
        );
    }

    /**
     * Feeds the bell icon's `CounterBadge`.
     */
    public function unreadCount(Request $request): JsonResponse
    {
        return response()->json([
            'count' => $request->user()->unreadNotifications()->count(),
        ]);
    }

    public function markAsRead(Request $request, string $id): NotificationResource
    {
        $notification = $request->user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        return new NotificationResource($notification);
    }

    public function markAllAsRead(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return response()->json([
            'count' => $request->user()->unreadNotifications()->count(),
        ]);
    }
}
