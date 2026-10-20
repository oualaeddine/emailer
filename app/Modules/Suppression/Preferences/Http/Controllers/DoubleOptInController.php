<?php

namespace App\Modules\Suppression\Preferences\Http\Controllers;

use App\Domain\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Modules\Recipients\Models\Recipient;
use App\Modules\Suppression\Preferences\Services\DoubleOptInService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

/** POST /api/v1/recipients/{uuid}/double-opt-in — sends the confirmation mail. */
class DoubleOptInController extends Controller
{
    public function send(Request $request, string $uuid, DoubleOptInService $service): JsonResponse
    {
        abort_unless($request->user()?->hasPermission(PermissionName::RecipientsManage->value), 403);

        $recipient = Recipient::query()->where('uuid', $uuid)->firstOrFail();

        // 5 sends per minute per recipient.
        $key = 'double-opt-in:'.$recipient->uuid;
        if (! RateLimiter::attempt($key, 5, static fn () => true, 60)) {
            return response()->json(['message' => 'Trop de tentatives, réessayez plus tard.'], 429)
                ->header('Retry-After', (string) RateLimiter::availableIn($key));
        }

        $service->send($recipient);

        return response()->json(['data' => ['id' => $recipient->uuid, 'email' => $recipient->email, 'queued' => true]], 202);
    }
}
