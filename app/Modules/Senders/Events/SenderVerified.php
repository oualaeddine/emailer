<?php

namespace App\Modules\Senders\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

class SenderVerified implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public readonly int $senderId) {}
}
