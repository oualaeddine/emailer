<?php

namespace App\Domain\Notifications;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * docs/41-notification-center.md — routing layer every event listener calls
 * instead of dispatching Laravel Notifications directly, so per-user channel
 * preferences are respected in one place (§41.3, §41.6).
 *
 * The full `notification_preferences` table (§41.4) and multi-channel
 * routing (mail/Slack) are introduced by the Notification Center module
 * itself; until then this service already implements the one rule that
 * does not depend on that table — `account_security` in-app notifications
 * are always delivered and are not user-disableable (§41.4) — and falls
 * back to "enabled" for any other category/channel when the preferences
 * table does not exist yet, so call sites never need to change once the
 * full preference model lands.
 *
 * Recipients are typed as {@see Model} rather than a "Notifiable" contract
 * because Laravel does not define one — `Illuminate\Notifications\Notifiable`
 * is a trait with no companion interface; every recipient in this
 * application is an Eloquent model using that trait.
 */
class NotificationCenterService
{
    /**
     * @param  iterable<Model>  $recipients
     */
    public function notify(NotificationCategory $category, iterable $recipients, Notification $notification): void
    {
        foreach ($recipients as $recipient) {
            if ($this->channelEnabled($recipient, $category, 'in_app')) {
                $recipient->notify($notification);
            }
        }
    }

    private function channelEnabled(Model $recipient, NotificationCategory $category, string $channel): bool
    {
        if ($category === NotificationCategory::AccountSecurity && $channel === 'in_app') {
            return true;
        }

        if (! Schema::hasTable('notification_preferences')) {
            return true;
        }

        $preference = DB::table('notification_preferences')
            ->where('user_id', $recipient->getKey())
            ->where('category', $category->value)
            ->where('channel', $channel)
            ->first();

        return $preference === null || (bool) $preference->enabled;
    }
}
