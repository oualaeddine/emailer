<?php

namespace App\Modules\Senders\Policies;

use App\Domain\Enums\PermissionName;
use App\Modules\Identity\Models\User;
use App\Modules\Senders\Models\Sender;

class SenderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission(
            PermissionName::SendersManage->value,
            PermissionName::CampaignsCreate->value,
            PermissionName::ComposerCompose->value,
        );
    }

    public function view(User $user, Sender $sender): bool
    {
        return $this->manage($user);
    }

    public function create(User $user): bool
    {
        return $this->manage($user);
    }

    public function update(User $user, Sender $sender): bool
    {
        return $this->manage($user);
    }

    public function delete(User $user, Sender $sender): bool
    {
        return $this->manage($user);
    }

    private function manage(User $user): bool
    {
        return $user->hasPermission(PermissionName::SendersManage->value);
    }
}
