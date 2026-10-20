<?php

namespace App\Modules\Automations\Policies;

use App\Domain\Enums\PermissionName;
use App\Modules\Automations\Models\Automation;
use App\Modules\Identity\Models\User;

/** docs/45 §45.2 — every automations endpoint requires `automations.manage`. */
class AutomationPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->manage($user);
    }

    public function view(User $user, Automation $automation): bool
    {
        return $this->manage($user);
    }

    public function create(User $user): bool
    {
        return $this->manage($user);
    }

    public function update(User $user, Automation $automation): bool
    {
        return $this->manage($user);
    }

    public function delete(User $user, Automation $automation): bool
    {
        return $this->manage($user);
    }

    private function manage(User $user): bool
    {
        return $user->hasPermission(PermissionName::AutomationsManage->value);
    }
}
