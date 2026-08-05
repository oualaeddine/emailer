<?php

namespace App\Modules\Notifications\Http\Requests;

use App\Modules\Notifications\Enums\NotificationCategory;
use App\Modules\Notifications\Enums\NotificationChannel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

/**
 * docs/41-notification-center.md §41.4 — PATCH own notification
 * preferences. Every user manages only their own preference rows, so
 * authorization is "is there an authenticated user" — no permission check,
 * matching the rest of this module's endpoints.
 */
class UpdateNotificationPreferencesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'preferences' => ['required', 'array', 'min:1'],
            'preferences.*.category' => ['required', new Enum(NotificationCategory::class)],
            'preferences.*.channel' => ['required', new Enum(NotificationChannel::class)],
            'preferences.*.enabled' => ['required', 'boolean'],
        ];
    }
}
