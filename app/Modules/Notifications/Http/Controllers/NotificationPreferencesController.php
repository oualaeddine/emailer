<?php

namespace App\Modules\Notifications\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Notifications\Enums\NotificationCategory;
use App\Modules\Notifications\Enums\NotificationChannel;
use App\Modules\Notifications\Http\Requests\UpdateNotificationPreferencesRequest;
use App\Modules\Notifications\Services\NotificationPreferenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * docs/41-notification-center.md §41.4 — "Settings → Notifications
 * (personal section)". Mirrors `App\Modules\Settings\Http\Controllers\SettingsController`'s
 * simple read/update shape, but scoped per-user (via
 * {@see NotificationPreferenceService}) instead of global — gated purely
 * by authentication, since every user only ever manages their own
 * preferences and no `notifications.*` permission exists.
 */
class NotificationPreferencesController extends Controller
{
    public function __construct(private readonly NotificationPreferenceService $preferences)
    {
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->preferences->effectiveForUser($request->user())]);
    }

    public function update(UpdateNotificationPreferencesRequest $request): JsonResponse
    {
        foreach ($request->validated('preferences') as $preference) {
            $this->preferences->update(
                $request->user(),
                NotificationCategory::from($preference['category']),
                NotificationChannel::from($preference['channel']),
                (bool) $preference['enabled'],
            );
        }

        return response()->json(['data' => $this->preferences->effectiveForUser($request->user())]);
    }
}
