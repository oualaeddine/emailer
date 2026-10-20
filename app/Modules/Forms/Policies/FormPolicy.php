<?php

namespace App\Modules\Forms\Policies;

use App\Domain\Enums\PermissionName;
use App\Modules\Identity\Models\User;

/** docs/45 §45.2 — write/manage `forms.manage`, read also `recipients.view`. */
class FormPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(PermissionName::FormsManage->value) || $user->hasPermission(PermissionName::RecipientsView->value);
    }

    public function view(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function manage(User $user): bool
    {
        return $user->hasPermission(PermissionName::FormsManage->value);
    }
}
