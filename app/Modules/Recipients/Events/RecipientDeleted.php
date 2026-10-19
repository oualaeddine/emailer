<?php

namespace App\Modules\Recipients\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/**
 * docs/45 §45.9 — fired for every deleted contact (bulk delete). Listeners: WP-101 `contactDeleted`.
 */
class RecipientDeleted implements ShouldDispatchAfterCommit
{
    public function __construct(
        public readonly int $recipientId,
        public readonly string $email,
    ) {}
}
