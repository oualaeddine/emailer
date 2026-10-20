<?php

namespace App\Modules\Suppression\Preferences\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/** docs/45 §45.9 — fired when a contact saves the public preferences page. */
class ContactPreferencesUpdated implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    /**
     * @param  list<int>  $unsubscribedListIds
     * @param  list<int>  $subscribedListIds
     */
    public function __construct(
        public readonly int $recipientId,
        public readonly array $unsubscribedListIds,
        public readonly array $subscribedListIds,
        public readonly bool $unsubscribedAll,
    ) {}
}
