<?php

namespace App\Providers;

use App\Domain\Enums\PermissionName;
use App\Modules\Composer\Models\Draft;
use App\Modules\Composer\Policies\DraftPolicy;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Policies\UserPolicy;
use App\Modules\Recipients\Models\Recipient;
use App\Modules\Recipients\Policies\RecipientPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

/**
 * docs/26-rbac.md §26.5 — Enforcement Layers.
 *
 * Every permission name in {@see PermissionName} is registered as its own
 * Gate ability, resolved through {@see User::hasPermission()}. This makes
 * every documented permission usable via route middleware
 * (`->middleware('can:campaigns.send')`), the `@can` Blade/Inertia-shared
 * props helper, and `Gate::authorize()` inside Application Services —
 * a single mechanism for the "route middleware" enforcement layer.
 *
 * Object-level nuance beyond the flat matrix (docs/26-rbac.md §26.4) is
 * layered on top via dedicated Policies, registered in $policies below.
 */
class AuthServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, class-string>
     */
    protected $policies = [
        User::class => UserPolicy::class,
        Recipient::class => RecipientPolicy::class,
        Draft::class => DraftPolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();

        foreach (PermissionName::cases() as $permission) {
            Gate::define($permission->value, fn (User $user): bool => $user->hasPermission($permission->value));
        }
    }
}
