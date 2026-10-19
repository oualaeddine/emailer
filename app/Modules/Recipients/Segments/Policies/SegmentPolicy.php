<?php

namespace App\Modules\Recipients\Segments\Policies;

use App\Domain\Enums\PermissionName;
use App\Modules\Identity\Models\User;
use App\Modules\Recipients\Models\Segment;

/**
 * docs/45-brevo-api-contracts.md §45.2 — segments: read `recipients.view`,
 * write `segments.manage`. Registered on the model via #[UsePolicy].
 */
class SegmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(PermissionName::RecipientsView->value);
    }

    public function view(User $user, Segment $segment): bool
    {
        return $user->hasPermission(PermissionName::RecipientsView->value);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(PermissionName::SegmentsManage->value);
    }

    public function update(User $user, Segment $segment): bool
    {
        return $user->hasPermission(PermissionName::SegmentsManage->value);
    }

    public function delete(User $user, Segment $segment): bool
    {
        return $user->hasPermission(PermissionName::SegmentsManage->value);
    }
}
