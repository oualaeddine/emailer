<?php

namespace App\Modules\Recipients\Policies;

use App\Domain\Enums\PermissionName;
use App\Modules\Identity\Models\User;
use App\Modules\Recipients\Models\Recipient;

/**
 * docs/26-rbac.md §26.3 — `recipients.view` / `recipients.manage`.
 */
class RecipientPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(PermissionName::RecipientsView->value);
    }

    public function view(User $user, Recipient $recipient): bool
    {
        return $user->hasPermission(PermissionName::RecipientsView->value);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(PermissionName::RecipientsManage->value);
    }

    public function update(User $user, Recipient $recipient): bool
    {
        return $user->hasPermission(PermissionName::RecipientsManage->value);
    }
}
