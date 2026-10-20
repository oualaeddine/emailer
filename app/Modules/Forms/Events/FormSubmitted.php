<?php

namespace App\Modules\Forms\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/** docs/45 §45.9 — a public form submission was stored (internal ids). */
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
