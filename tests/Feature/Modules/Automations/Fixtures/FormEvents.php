<?php

/*
 * Stand-ins for WP-90's form events (docs/45 §45.9), declared only when the
 * Forms module is absent from this checkout: same FQCN and constructor
 * properties, so the tests dispatch the real class once WP-90 is merged.
 */

namespace App\Modules\Forms\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

if (! class_exists(FormSubmitted::class)) {
    class FormSubmitted implements ShouldDispatchAfterCommit
    {
        use Dispatchable;

        public function __construct(
            public readonly int $formId,
            public readonly int $submissionId,
            public readonly ?int $recipientId,
            public readonly string $status,
        ) {}
    }
}

if (! class_exists(ContactOptInConfirmed::class)) {
    class ContactOptInConfirmed implements ShouldDispatchAfterCommit
    {
        use Dispatchable;

        public function __construct(
            public readonly int $recipientId,
            public readonly int $formId,
        ) {}
    }
}
