<?php

namespace App\Modules\Suppression\Preferences\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * docs/45 §45.6.6 — a contact clicked the double opt-in confirmation link.
 * Fired on every confirmation (idempotent consumers), synchronously so the
 * confirmation page reflects what listeners applied (e.g. Forms confirms the
 * contact's pending submissions). `$firstConfirmation` is false when
 * `recipients.opt_in_at` was already set.
 */
class DoubleOptInConfirmed
{
    use Dispatchable;

    public function __construct(
        public readonly int $recipientId,
        public readonly bool $firstConfirmation,
    ) {}
}
