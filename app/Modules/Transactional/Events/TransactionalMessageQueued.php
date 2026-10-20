<?php

namespace App\Modules\Transactional\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/** docs/45 §45.9 — a transactional message was created and queued. */
class TransactionalMessageQueued implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public readonly int $messageId, public readonly ?int $apiKeyId = null) {}
}
