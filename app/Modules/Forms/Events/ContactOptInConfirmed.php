<?php

namespace App\Modules\Forms\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * docs/45 §45.9 — a contact confirmed the double opt-in of a form submission
 * (fired once per form whose pending submissions were confirmed). Starts the
 * `form_submitted` automations that waited for the confirmation.
 */
class ContactOptInConfirmed implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly int $recipientId,
        public readonly int $formId,
    ) {}
}
