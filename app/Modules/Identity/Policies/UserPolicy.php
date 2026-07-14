<?php

namespace App\Modules\Identity\Policies;

use App\Domain\Enums\PermissionName;
use App\Modules\Identity\Models\User;

/**
 * docs/26-rbac.md §26.3 — `users.manage` gates all user administration.
 * No additional object-level rule is documented for user management
 * itself (unlike, e.g., campaigns.send vs campaigns.send_any), so every
 * action here delegates to the single flat permission.
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(PermissionName::UsersManage->value);
    }

    public function view(User $user, User $target): bool
    {
        return $user->hasPermission(PermissionName::UsersManage->value);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(PermissionName::UsersManage->value);
    }

    public function update(User $user, User $target): bool
    {
        return $user->hasPermission(PermissionName::UsersManage->value);
    }

    public function delete(User $user, User $target): bool
    {
        return $user->hasPermission(PermissionName::UsersManage->value)
            && $user->id !== $target->id;
    }
}
