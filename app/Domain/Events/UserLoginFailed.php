<?php

namespace App\Domain\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * docs/27-audit-logs.md §27.2 — Authentication: `auth.login_failed`.
 */
class UserLoginFailed
{
    use Dispatchable;

    public function __construct(public readonly string $email)
    {
    }
}
