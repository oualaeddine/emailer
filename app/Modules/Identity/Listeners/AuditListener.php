<?php

namespace App\Modules\Identity\Listeners;

use App\Domain\Events\UserCreated;
use App\Domain\Events\UserDeactivated;
use App\Domain\Events\UserLoggedIn;
use App\Domain\Events\UserLoginFailed;
use App\Domain\Events\UserRoleChanged;
use App\Modules\Audit\Services\AuditLogger;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * docs/27-audit-logs.md §27.5 — Implementation Pattern.
 * The single listener class documenting exactly which Identity events are
 * audited (docs/31-events.md §31.2) — a code-review checklist item on its
 * own: any new Identity event must be added here to be audited.
 *
 * Queued (§31.4): a failed/slow audit write must never block login,
 * account creation, or role changes.
 */
class AuditListener implements ShouldQueue
{
    public function __construct(private readonly AuditLogger $auditLogger)
    {
    }

    public function handleUserLoggedIn(UserLoggedIn $event): void
    {
        $this->auditLogger->record('auth.login_succeeded', $event->user, $event->user);
    }

    public function handleUserLoginFailed(UserLoginFailed $event): void
    {
        $this->auditLogger->record('auth.login_failed', null, null, newValues: ['email' => $event->email]);
    }

    public function handleUserCreated(UserCreated $event): void
    {
        $this->auditLogger->record(
            'user.created',
            $event->user,
            $event->createdBy,
            newValues: [
                'name' => $event->user->name,
                'email' => $event->user->email,
                'role_id' => $event->user->role_id,
            ],
        );
    }

    public function handleUserRoleChanged(UserRoleChanged $event): void
    {
        $this->auditLogger->record(
            'user.role_changed',
            $event->user,
            $event->changedBy,
            oldValues: ['role_id' => $event->oldRole->id, 'role_name' => $event->oldRole->name],
            newValues: ['role_id' => $event->newRole->id, 'role_name' => $event->newRole->name],
        );
    }

    public function handleUserDeactivated(UserDeactivated $event): void
    {
        $this->auditLogger->record(
            'user.deactivated',
            $event->user,
            $event->deactivatedBy,
            oldValues: ['is_active' => true],
            newValues: ['is_active' => false],
        );
    }
}
