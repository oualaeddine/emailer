<?php

namespace App\Modules\Suppression\Policies;

use App\Domain\Enums\PermissionName;
use App\Modules\Identity\Models\User;
use App\Modules\Suppression\Models\SuppressionEntry;

/**
 * docs/26-rbac.md §26.3 — `suppression.view` / `suppression.manage`.
 * Mirrors `App\Modules\Recipients\Policies\RecipientPolicy`.
 *
 * Not registered in `App\Providers\AuthServiceProvider::$policies` (that
 * file is outside this work package's file scope, docs/42 §42.6/§42.11) —
 * it relies on Laravel's default policy auto-discovery instead, which is
 * exactly why it's named `SuppressionEntryPolicy` (matching the model
 * class `SuppressionEntry` + `Policy`, in the sibling `Policies`
 * namespace) rather than `SuppressionPolicy`: Laravel's guesser derives
 * `App\Modules\Suppression\Policies\SuppressionEntryPolicy` from
 * `App\Modules\Suppression\Models\SuppressionEntry` by swapping the
 * `Models` segment for `Policies` and appending `Policy` — the same
 * convention that already resolves `RecipientPolicy` for `Recipient`.
 */
class SuppressionEntryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(PermissionName::SuppressionView->value);
    }

    public function view(User $user, SuppressionEntry $entry): bool
    {
        return $user->hasPermission(PermissionName::SuppressionView->value);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(PermissionName::SuppressionManage->value);
    }

    public function delete(User $user, SuppressionEntry $entry): bool
    {
        return $user->hasPermission(PermissionName::SuppressionManage->value);
    }
}
