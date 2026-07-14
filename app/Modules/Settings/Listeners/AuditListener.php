<?php

namespace App\Modules\Settings\Listeners;

use App\Domain\Events\SettingsUpdated;
use App\Modules\Audit\Services\AuditLogger;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * docs/27-audit-logs.md §27.2 — Settings changes: `settings.updated`.
 */
class AuditListener implements ShouldQueue
{
    public function __construct(private readonly AuditLogger $auditLogger)
    {
    }

    public function handle(SettingsUpdated $event): void
    {
        $this->auditLogger->record(
            'settings.updated',
            null,
            $event->updatedBy,
            oldValues: ['key' => $event->key, 'value' => $event->oldValue],
            newValues: ['key' => $event->key, 'value' => $event->newValue],
        );
    }
}
