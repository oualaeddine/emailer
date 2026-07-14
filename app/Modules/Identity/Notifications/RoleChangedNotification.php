<?php

namespace App\Modules\Identity\Notifications;

use App\Modules\Identity\Models\Role;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * docs/41-notification-center.md §41.2 — `UserRoleChanged` → Account/security category.
 * French-only per docs/07-ui-design.md §7.12.
 */
class RoleChangedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly Role $oldRole, private readonly Role $newRole)
    {
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Votre rôle a été modifié',
            'message' => sprintf(
                'Votre rôle est passé de « %s » à « %s ».',
                $this->oldRole->description ?? $this->oldRole->name,
                $this->newRole->description ?? $this->newRole->name,
            ),
            'category' => 'account_security',
        ];
    }
}
