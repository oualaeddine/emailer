<?php

namespace App\Domain\Events;

use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * docs/27-audit-logs.md §27.2 — Permission changes: `user.role_changed`.
 * docs/41-notification-center.md §41.2 — notifies the affected user.
 */
class UserRoleChanged
{
    use Dispatchable;

    public function __construct(
        public readonly User $user,
        public readonly Role $oldRole,
        public readonly Role $newRole,
        public readonly User $changedBy,
    ) {
    }
}
