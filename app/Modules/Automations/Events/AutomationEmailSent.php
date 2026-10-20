<?php

namespace App\Modules\Automations\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/** WP-91 — a send_email step queued a message (`messages.automation_step_id` = stepId). */
class AutomationEmailSent implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly int $runId,
        public readonly int $stepId,
        public readonly int $messageId,
    ) {}
}
