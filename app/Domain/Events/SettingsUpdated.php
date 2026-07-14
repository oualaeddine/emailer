<?php

namespace App\Domain\Events;

use App\Modules\Identity\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * docs/27-audit-logs.md §27.2 — Settings changes: `settings.updated` (per key).
 * docs/31-events.md — `SettingsUpdated` → AuditListener, ConfigCacheInvalidationListener.
 */
class SettingsUpdated
{
    use Dispatchable;

    public function __construct(
        public readonly string $key,
        public readonly mixed $oldValue,
        public readonly mixed $newValue,
        public readonly bool $isSecret,
        public readonly User $updatedBy,
    ) {
    }
}
