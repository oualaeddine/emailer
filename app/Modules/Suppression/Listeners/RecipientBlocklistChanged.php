<?php

namespace App\Modules\Suppression\Listeners;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * docs/45 §45.9 — fired by {@see SyncRecipientBlocklist} when a recipient's
 * `blocklisted_at` mirror flips (WP-52 timeline, WP-101 `contactUpdated`).
 */
class RecipientBlocklistChanged
{
    use Dispatchable;

    public function __construct(
        public readonly int $recipientId,
        public readonly bool $blocklisted,
        public readonly ?string $reason,
    ) {
    }
}
