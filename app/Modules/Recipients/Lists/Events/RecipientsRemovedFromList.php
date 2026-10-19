<?php

namespace App\Modules\Recipients\Lists\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/** docs/45 §45.9. */
class RecipientsRemovedFromList implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    /** @param  list<int>  $recipientIds */
    public function __construct(
        public readonly int $listId,
        public readonly array $recipientIds,
        public readonly string $source,
    ) {}
}
