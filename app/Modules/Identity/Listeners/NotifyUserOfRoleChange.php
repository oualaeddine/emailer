<?php

namespace App\Modules\Identity\Listeners;

use App\Domain\Events\UserRoleChanged;
use App\Domain\Notifications\NotificationCategory;
use App\Domain\Notifications\NotificationCenterService;
use App\Modules\Identity\Notifications\RoleChangedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * docs/41-notification-center.md §41.2 — `UserRoleChanged` → Account/security.
 */
class NotifyUserOfRoleChange implements ShouldQueue
{
    public function __construct(private readonly NotificationCenterService $notificationCenter)
    {
    }

    public function handle(UserRoleChanged $event): void
    {
        $this->notificationCenter->notify(
            NotificationCategory::AccountSecurity,
            [$event->user],
            new RoleChangedNotification($event->oldRole, $event->newRole),
        );
    }
}
