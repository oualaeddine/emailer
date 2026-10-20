<?php

namespace App\Modules\Automations\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/** docs/45 §45.9 — a run reached completed / exited / failed. */
class AutomationRunFinished implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly int $runId,
        public readonly string $status,
    ) {}
}
