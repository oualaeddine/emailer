<?php

namespace App\Domain\Events;

use App\Modules\Identity\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * docs/27-audit-logs.md §27.2 — Authentication: `auth.login_succeeded`.
 */
class UserLoggedIn
{
    use Dispatchable;

    public function __construct(public readonly User $user)
    {
    }
}
