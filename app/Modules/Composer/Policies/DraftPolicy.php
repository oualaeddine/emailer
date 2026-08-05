<?php

namespace App\Modules\Composer\Policies;

use App\Domain\Enums\PermissionName;
use App\Modules\Composer\Models\Draft;
use App\Modules\Identity\Models\User;

/**
 * docs/26-rbac.md §26.4 — Operators see only their own drafts by default;
 * `mailbox.view_all` (Manager/Admin) sees organization-wide.
 */
class DraftPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(PermissionName::MailboxViewOwn->value)
            || $user->hasPermission(PermissionName::MailboxViewAll->value);
    }

    public function view(User $user, Draft $draft): bool
    {
        if ($user->hasPermission(PermissionName::MailboxViewAll->value)) {
            return true;
        }

        return $user->hasPermission(PermissionName::MailboxViewOwn->value) && $draft->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(PermissionName::ComposerCompose->value);
    }

    public function update(User $user, Draft $draft): bool
    {
        if ($user->hasPermission(PermissionName::MailboxViewAll->value)) {
            return $user->hasPermission(PermissionName::ComposerCompose->value);
        }

        return $draft->user_id === $user->id && $user->hasPermission(PermissionName::ComposerCompose->value);
    }

    public function send(User $user, Draft $draft): bool
    {
        if ($user->hasPermission(PermissionName::MailboxViewAll->value)) {
            return $user->hasPermission(PermissionName::ComposerSend->value);
        }

        return $draft->user_id === $user->id && $user->hasPermission(PermissionName::ComposerSend->value);
    }
}
