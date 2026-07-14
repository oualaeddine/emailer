<?php

namespace App\Modules\Settings\Listeners;

use App\Domain\Events\SettingsUpdated;
use App\Modules\Settings\Services\SettingsService;
use Illuminate\Support\Facades\Cache;

/**
 * docs/31-events.md §31.4 — synchronous listener: must complete before the
 * request/job returns, so the very next read of this key is never stale.
 */
class ConfigCacheInvalidationListener
{
    public function handle(SettingsUpdated $event): void
    {
        Cache::forget(SettingsService::CACHE_PREFIX.$event->key);
    }
}
