<?php

namespace App\Domain\Events;

use App\Modules\Identity\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * docs/27-audit-logs.md §27.2, docs/28-security.md §28.1 —
 * `auth.two_factor_failed`: an invalid second-factor code was submitted for
 * an account whose password had already been accepted.
 */
class TwoFactorChallengeFailed
{
    use Dispatchable;

    public function __construct(public readonly User $user) {}
}
