<?php

namespace App\Modules\Templates\Sections\Policies;

use App\Domain\Enums\PermissionName;
use App\Modules\Identity\Models\User;
use App\Modules\Templates\Sections\Models\SavedSection;

/**
 * docs/45-brevo-api-contracts.md §45.7.1 — saved sections are shared by
 * the whole account: listed with `templates.view` | `campaigns.create`,
 * created with `templates.manage` | `campaigns.create`, renamed / deleted
 * by their author or by `templates.manage`. Discovered by Laravel's
 * policy-guessing convention (`…\Sections\Models` → `…\Sections\Policies`).
 */
class SavedSectionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission(PermissionName::TemplatesView->value, PermissionName::CampaignsCreate->value);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyPermission(PermissionName::TemplatesManage->value, PermissionName::CampaignsCreate->value);
    }

    public function update(User $user, SavedSection $section): bool
    {
        if ($user->hasPermission(PermissionName::TemplatesManage->value)) {
            return true;
        }

        return $section->created_by !== null
            && $section->created_by === $user->id
            && $this->create($user);
    }

    public function delete(User $user, SavedSection $section): bool
    {
        return $this->update($user, $section);
    }
}
