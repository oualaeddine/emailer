<?php

namespace App\Providers;

use App\Domain\Enums\PermissionName;
use App\Domain\Enums\RoleName;
use App\Modules\Identity\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\HorizonApplicationServiceProvider;

/**
 * docs/24-queue-management.md §24.5 — the raw Horizon dashboard (`/horizon`)
 * is restricted to Administrator only, distinct from the in-app Queue
 * Monitoring page which is also readable by Marketing Manager (§24.7).
 *
 * The gate below mirrors the `queues.view` check used by
 * `QueueStatsController` but additionally requires the Administrator role,
 * matching the dashboard's narrower audience. Without this provider Horizon
 * falls back to its own gate, which authorises everyone in the `local`
 * environment — unacceptable for a deployed instance.
 */
class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    protected function gate(): void
    {
        Gate::define('viewHorizon', function (?User $user): bool {
            return $user !== null
                && $user->is_active
                && $user->role?->name === RoleName::Administrator->value
                && $user->hasPermission(PermissionName::QueuesView->value);
        });
    }
}
