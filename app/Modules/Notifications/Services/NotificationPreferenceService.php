<?php

namespace App\Modules\Notifications\Services;

use App\Modules\Identity\Models\User;
use App\Modules\Notifications\Enums\NotificationCategory;
use App\Modules\Notifications\Enums\NotificationChannel;
use App\Modules\Notifications\Models\NotificationPreference;
use Illuminate\Validation\ValidationException;

/**
 * docs/41-notification-center.md §41.4 — per-user, per-category,
 * per-channel notification preferences ("Settings → Notifications,
 * personal section"). Rows in `notification_preferences` are overrides
 * only: a missing row means "default: enabled", so {@see effectiveForUser()}
 * materializes the full (category × channel) matrix on every read instead
 * of relying on rows being seeded when a user is created.
 */
class NotificationPreferenceService
{
    /**
     * "in_app always enabled and not user-disableable for
     * `account_security`" (§41.4) — a user cannot silence their own
     * role-change notice.
     *
     * @var list<array{0: NotificationCategory, 1: NotificationChannel}>
     */
    private const LOCKED = [
        [NotificationCategory::AccountSecurity, NotificationChannel::InApp],
    ];

    /**
     * @return list<array{category: string, channel: string, enabled: bool, locked: bool}>
     */
    public function effectiveForUser(User $user): array
    {
        $overrides = NotificationPreference::query()
            ->where('user_id', $user->id)
            ->get()
            ->keyBy(fn (NotificationPreference $preference): string => $preference->category.'.'.$preference->channel);

        $result = [];

        foreach (NotificationCategory::cases() as $category) {
            foreach (NotificationChannel::cases() as $channel) {
                $locked = $this->isLocked($category, $channel);
                $override = $overrides->get($category->value.'.'.$channel->value);

                $result[] = [
                    'category' => $category->value,
                    'channel' => $channel->value,
                    'enabled' => $locked ? true : ($override?->enabled ?? true),
                    'locked' => $locked,
                ];
            }
        }

        return $result;
    }

    /**
     * @throws ValidationException when attempting to disable a locked combination
     */
    public function update(User $user, NotificationCategory $category, NotificationChannel $channel, bool $enabled): void
    {
        if (! $enabled && $this->isLocked($category, $channel)) {
            throw ValidationException::withMessages([
                'enabled' => ["Le canal « {$channel->value} » de la catégorie « {$category->value} » ne peut pas être désactivé."],
            ]);
        }

        NotificationPreference::query()->updateOrCreate(
            ['user_id' => $user->id, 'category' => $category->value, 'channel' => $channel->value],
            ['enabled' => $enabled],
        );
    }

    private function isLocked(NotificationCategory $category, NotificationChannel $channel): bool
    {
        foreach (self::LOCKED as [$lockedCategory, $lockedChannel]) {
            if ($lockedCategory === $category && $lockedChannel === $channel) {
                return true;
            }
        }

        return false;
    }
}
