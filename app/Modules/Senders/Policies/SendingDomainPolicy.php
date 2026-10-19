<?php

namespace App\Modules\Senders\Policies;

use App\Domain\Enums\PermissionName;
use App\Modules\Identity\Models\User;
use App\Modules\Senders\Models\SendingDomain;

class SendingDomainPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->manage($user);
    }

    public function view(User $user, SendingDomain $domain): bool
    {
        return $this->manage($user);
    }

    public function create(User $user): bool
    {
        return $this->manage($user);
    }

    public function update(User $user, SendingDomain $domain): bool
    {
        return $this->manage($user);
    }

    public function delete(User $user, SendingDomain $domain): bool
    {
        return $this->manage($user);
    }

    private function manage(User $user): bool
    {
        return $user->hasPermission(PermissionName::SendersManage->value);
    }
}
