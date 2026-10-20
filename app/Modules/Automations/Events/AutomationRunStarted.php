<?php

namespace App\Modules\Automations\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/** WP-91 — a contact entered an automation (internal ids, docs/31 §31.3). */
class AutomationRunStarted implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly int $runId,
        public readonly int $automationId,
        public readonly int $recipientId,
    ) {}
}
