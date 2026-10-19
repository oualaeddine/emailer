<?php

namespace App\Modules\Recipients\Attributes\Policies;

use App\Domain\Enums\PermissionName;
use App\Modules\Identity\Models\User;

/** docs/45 §45.2 — write: `attributes.manage`; list / merge tags: `recipients.view` | `campaigns.create` | `templates.manage`. */
class ContactAttributePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission(
            PermissionName::RecipientsView->value,
            PermissionName::CampaignsCreate->value,
            PermissionName::TemplatesManage->value,
        );
    }

    public function view(User $user): bool
    {
        return $user->hasPermission(PermissionName::RecipientsView->value);
    }

    public function manage(User $user): bool
    {
        return $user->hasPermission(PermissionName::AttributesManage->value);
    }
}
