<?php

namespace App\Modules\Automations\Jobs;

use App\Modules\Automations\Models\Automation;
use App\Modules\Automations\Services\TriggerDispatcher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * WP-91 — starts runs of one automation for a chunk (≤ 500) of contacts
 * that hit its trigger; the trigger filter and re-entry rules are applied
 * per contact by {@see TriggerDispatcher::start()}.
 */
class StartAutomationRunJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    /**
     * @param  list<int>  $recipientIds
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public readonly int $automationId,
        public readonly array $recipientIds,
        public readonly array $context = [],
    ) {}

    public function handle(TriggerDispatcher $dispatcher): void
    {
        $automation = Automation::query()->find($this->automationId);

        if ($automation === null || ! $automation->isActive()) {
            return;
        }

        foreach ($this->recipientIds as $recipientId) {
            $dispatcher->start($automation, (int) $recipientId, $this->context);
        }
    }
}
