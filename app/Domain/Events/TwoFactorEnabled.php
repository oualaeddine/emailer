<?php

namespace App\Domain\Events;

use App\Modules\Identity\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * docs/27-audit-logs.md §27.2, docs/28-security.md §28.1 —
 * `auth.two_factor_enabled`: a user confirmed TOTP enrolment.
 */
class TwoFactorEnabled
{
    use Dispatchable;

    public function __construct(public readonly User $user) {}
}
