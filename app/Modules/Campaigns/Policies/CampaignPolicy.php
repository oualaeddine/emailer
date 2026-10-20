<?php

namespace App\Modules\Campaigns\Policies;

use App\Domain\Enums\PermissionName;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Identity\Models\User;

/**
 * docs/15-campaign-management.md §15.9 — Permissions, extended by
 * docs/45-brevo-api-contracts.md §45.2/§45.4.2. Discovered by Laravel's
 * policy-guessing convention.
 *
 * `campaigns.send` (own campaigns only) vs. `campaigns.send_any`;
 * edits are `campaigns.create` (own) | `campaigns.send_any`.
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

    /**
     * docs/45 §45.4.2 PATCH / design / DELETE: `campaigns.create` (own) | `campaigns.send_any`.
     */
    public function update(User $user, Campaign $campaign): bool
    {
        if ($user->hasPermission(PermissionName::CampaignsSendAny->value)) {
            return true;
        }

        return $user->hasPermission(PermissionName::CampaignsCreate->value) && $campaign->created_by === $user->id;
    }

    public function delete(User $user, Campaign $campaign): bool
    {
        return $this->update($user, $campaign);
    }

    /**
     * §15.9 — also gates schedule/unschedule and send-to-new-contacts.
     */
    public function send(User $user, Campaign $campaign): bool
    {
        if ($user->hasPermission(PermissionName::CampaignsSendAny->value)) {
            return true;
        }

        return $user->hasPermission(PermissionName::CampaignsSend->value) && $campaign->created_by === $user->id;
    }

    public function cancelPause(User $user, Campaign $campaign): bool
    {
        return $user->hasPermission(PermissionName::CampaignsCancelPause->value);
    }

    public function approve(User $user, Campaign $campaign): bool
    {
        return $user->hasPermission(PermissionName::CampaignsApprove->value);
    }

    /**
     * §15.7 duplicate / clone produce a new draft — gated like `create`.
     */
    public function clonable(User $user, Campaign $campaign): bool
    {
        return $user->hasPermission(PermissionName::CampaignsCreate->value);
    }

    /**
     * docs/45 §45.4.2 audience estimate / test send / move: `campaigns.create`.
     */
    public function prepare(User $user, Campaign $campaign): bool
    {
        return $user->hasPermission(PermissionName::CampaignsCreate->value);
    }

    public function archive(User $user, Campaign $campaign): bool
    {
        return $user->hasPermission(PermissionName::CampaignsArchive->value);
    }
}
