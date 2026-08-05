<?php

namespace App\Modules\Notifications\Models;

use App\Modules\Identity\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * docs/41-notification-center.md §41.4 — `notification_preferences`.
 * These rows are explicit overrides only — the absence of a row for a
 * given (user, category, channel) means "default: enabled" (see
 * {@see \App\Modules\Notifications\Services\NotificationPreferenceService::effectiveForUser()}).
 * A row is written only when a user actually changes something away from
 * the default, so a brand-new user needs no seeding on creation.
 *
 * @property int $id
 * @property int $user_id
 * @property string $category
 * @property string $channel
 * @property bool $enabled
 */
class NotificationPreference extends Model
{
    protected $fillable = ['user_id', 'category', 'channel', 'enabled'];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
