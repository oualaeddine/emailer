<?php

namespace App\Domain\Events;

use App\Modules\Identity\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * docs/27-audit-logs.md §27.2 — `user.password_changed` (self-service change)
 * and `user.password_reset` (administrator reset). Never carries the password.
 */
class UserPasswordChanged
{
    use Dispatchable;

    public function __construct(
        public readonly User $user,
        public readonly User $changedBy,
        public readonly bool $isReset,
    ) {}
}
