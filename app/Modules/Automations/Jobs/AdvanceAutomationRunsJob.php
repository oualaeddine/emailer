<?php

namespace App\Modules\Automations\Jobs;

use App\Modules\Automations\Listeners\DateAnniversaryScheduler;
use App\Modules\Automations\Services\AutomationRunner;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * docs/45 §45.9 — the automation tick (scheduler every minute,
 * `withoutOverlapping`): starts the day's date-anniversary runs once their
 * configured time has come, then advances every due run (waits that are
 * over, runs left due by a previous tick).
 */
class AdvanceAutomationRunsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    public function handle(AutomationRunner $runner, DateAnniversaryScheduler $anniversaries): void
    {
        $anniversaries->scan(now());
        $runner->tick(now());
    }
}
