<?php

namespace App\Domain\Events;

use App\Modules\Identity\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * docs/27-audit-logs.md §27.2 — `user.two_factor_reset`: an administrator (or,
 * with a null `$resetBy`, the `users:reset-two-factor` CLI recovery) wiped a
 * user's TOTP enrolment. Never carries the secret or the recovery codes.
 */
class UserTwoFactorReset
{
    use Dispatchable;

    public function __construct(
        public readonly User $user,
        public readonly ?User $resetBy,
    ) {}
}
