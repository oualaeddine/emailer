<?php

namespace App\Modules\Recipients\Views\Policies;

use App\Domain\Enums\PermissionName;
use App\Modules\Identity\Models\User;
use App\Modules\Recipients\Views\Models\ContactView;

/**
 * docs/45 §45.5.4 — owner, or `recipients.manage` for shared views.
 */
class ContactViewPolicy
{
    public function manage(User $user, ContactView $view): bool
    {
        return $view->user_id === $user->id
            || ($view->is_shared && $user->hasPermission(PermissionName::RecipientsManage->value));
    }
}
