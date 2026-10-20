<?php

namespace App\Modules\Recipients\Attributes\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/** docs/45 §45.9. `changes`: NAME => {old, new}. */
class ContactAttributesUpdated implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    /** @param array<string, array{old: mixed, new: mixed}> $changes */
    public function __construct(
        public readonly int $recipientId,
        public readonly array $changes,
        public readonly string $source,
    ) {}
}
