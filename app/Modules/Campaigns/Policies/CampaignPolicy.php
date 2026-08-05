<?php

namespace App\Modules\Campaigns\Policies;

use App\Domain\Enums\PermissionName;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Identity\Models\User;

/**
 * docs/15-campaign-management.md §15.9 — Permissions. Discovered by
 * Laravel's default policy-guessing convention (Campaign lives under
 * `App\Modules\Campaigns\Models`, this class under
 * `App\Modules\Campaigns\Policies`) — this work package must not touch
 * `AuthServiceProvider` (outside `app/Modules/Campaigns/**`), so it relies
 * on that convention instead of an explicit `$policies` entry, exactly the
 * same mechanism that already backs `RecipientPolicy`/`DraftPolicy` there.
 *
 * `campaigns.send` (own campaigns only) vs. `campaigns.send_any` mirrors
 * the `composer.send` + `mailbox.view_own`/`view_all` ownership pattern
 * seen in `DraftPolicy::send` — except here the RBAC matrix already
 * encodes "own" vs. "any" as two distinct permissions, so no secondary
 * scope permission is needed.
 */
class CampaignPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(PermissionName::CampaignsView->value);
    }

    public function view(User $user, Campaign $campaign): bool
    {
        return $user->hasPermission(PermissionName::CampaignsView->value);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(PermissionName::CampaignsCreate->value);
    }

    public function update(User $user, Campaign $campaign): bool
    {
        if (! $user->hasPermission(PermissionName::CampaignsCreate->value)) {
            return false;
        }

        if ($user->hasPermission(PermissionName::CampaignsSendAny->value)) {
            return true;
        }

        return $campaign->created_by === $user->id;
    }

    /**
     * §15.9 — "campaigns.send (own) vs. campaigns.send_any" distinction.
     * Also gates `schedule()` (§15.3), since scheduling starts the same
     * dispatch chain, just deferred.
     */
    public function send(User $user, Campaign $campaign): bool
    {
        if ($user->hasPermission(PermissionName::CampaignsSendAny->value)) {
            return true;
        }

        return $user->hasPermission(PermissionName::CampaignsSend->value) && $campaign->created_by === $user->id;
    }

    /**
     * §15.6 Pause/Resume/Cancel — a single `campaigns.cancel_pause`
     * permission with no "_own"/"_any" pair (unlike `send`), so it is
     * global for anyone holding it, per the RBAC matrix.
     */
    public function cancelPause(User $user, Campaign $campaign): bool
    {
        return $user->hasPermission(PermissionName::CampaignsCancelPause->value);
    }

    public function approve(User $user, Campaign $campaign): bool
    {
        return $user->hasPermission(PermissionName::CampaignsApprove->value);
    }

    /**
     * §15.7 Cloning produces a new draft campaign, so it is gated the same
     * as `create`.
     */
    public function clonable(User $user, Campaign $campaign): bool
    {
        return $user->hasPermission(PermissionName::CampaignsCreate->value);
    }
}
