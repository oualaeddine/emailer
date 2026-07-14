<?php

namespace App\Providers;

use App\Domain\Events\SettingsUpdated;
use App\Domain\Events\UserCreated;
use App\Domain\Events\UserDeactivated;
use App\Domain\Events\UserLoggedIn;
use App\Domain\Events\UserLoginFailed;
use App\Domain\Events\UserRoleChanged;
use App\Modules\Identity\Listeners\AuditListener as IdentityAuditListener;
use App\Modules\Identity\Listeners\NotifyUserOfRoleChange;
use App\Modules\Settings\Listeners\AuditListener as SettingsAuditListener;
use App\Modules\Settings\Listeners\ConfigCacheInvalidationListener;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

/**
 * docs/31-events.md §31.2 — Event Catalog & Listener Map.
 * This is the published-language contract between modules (docs/35-domain-driven-design.md
 * §35.4): every cross-module side effect is wired here, not called inline
 * from Application Services.
 */
class EventServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, list<array{class-string, string}|class-string>>
     */
    protected $listen = [
        UserLoggedIn::class => [
            [IdentityAuditListener::class, 'handleUserLoggedIn'],
        ],
        UserLoginFailed::class => [
            [IdentityAuditListener::class, 'handleUserLoginFailed'],
        ],
        UserCreated::class => [
            [IdentityAuditListener::class, 'handleUserCreated'],
        ],
        UserRoleChanged::class => [
            [IdentityAuditListener::class, 'handleUserRoleChanged'],
            NotifyUserOfRoleChange::class,
        ],
        UserDeactivated::class => [
            [IdentityAuditListener::class, 'handleUserDeactivated'],
        ],
        SettingsUpdated::class => [
            ConfigCacheInvalidationListener::class,
            SettingsAuditListener::class,
        ],
    ];

    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
