<?php

namespace Database\Seeders;

use App\Domain\Enums\PermissionName;
use App\Domain\Enums\RoleName;
use App\Modules\Identity\Models\Permission;
use App\Modules\Identity\Models\Role;
use Illuminate\Database\Seeder;

/**
 * docs/26-rbac.md §26.3 — Full Permission Matrix, §26.6 — Seed Data.
 *
 * Seeds the four fixed roles and every permission, then grants each role
 * exactly the permissions marked "✔" in the documented matrix. This is the
 * single source of truth for RBAC seed data — the matrix below must be
 * kept byte-for-byte in sync with docs/26-rbac.md §26.3.
 */
class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        foreach (PermissionName::cases() as $permission) {
            Permission::query()->updateOrCreate(
                ['name' => $permission->value],
                ['module' => $permission->module(), 'description' => $permission->label()],
            );
        }

        foreach (RoleName::cases() as $roleName) {
            $role = Role::query()->updateOrCreate(
                ['name' => $roleName->value],
                ['description' => $roleName->description()],
            );

            $permissionIds = Permission::query()
                ->whereIn('name', array_map(fn (PermissionName $p) => $p->value, $this->matrix()[$roleName->value]))
                ->pluck('id');

            $role->permissions()->sync($permissionIds);
        }
    }

    /**
     * @return array<string, list<PermissionName>>
     */
    private function matrix(): array
    {
        $administrator = PermissionName::cases();

        $marketingManager = [
            PermissionName::DashboardView,
            PermissionName::MailboxViewOwn,
            PermissionName::MailboxViewAll,
            PermissionName::ComposerCompose,
            PermissionName::ComposerSend,
            PermissionName::TemplatesView,
            PermissionName::TemplatesManage,
            PermissionName::RecipientsView,
            PermissionName::RecipientsManage,
            PermissionName::RecipientsImport,
            PermissionName::RecipientsVerify,
            PermissionName::RecipientsAnnotate,
            PermissionName::SegmentsManage,
            PermissionName::CampaignsView,
            PermissionName::CampaignsCreate,
            PermissionName::CampaignsSend,
            PermissionName::CampaignsSendAny,
            PermissionName::CampaignsApprove,
            PermissionName::CampaignsCancelPause,
            PermissionName::SmtpView,
            PermissionName::QueuesView,
            PermissionName::ReportingView,
            PermissionName::ReportingExport,
            PermissionName::SuppressionView,
            PermissionName::SuppressionManage,
            PermissionName::SettingsBrandingOnly,
        ];

        $marketingOperator = [
            PermissionName::DashboardView,
            PermissionName::MailboxViewOwn,
            PermissionName::ComposerCompose,
            PermissionName::ComposerSend,
            PermissionName::TemplatesView,
            PermissionName::RecipientsView,
            PermissionName::RecipientsManage,
            PermissionName::RecipientsImport,
            PermissionName::RecipientsVerify,
            PermissionName::RecipientsAnnotate,
            PermissionName::SegmentsManage,
            PermissionName::CampaignsView,
            PermissionName::CampaignsCreate,
            PermissionName::CampaignsSend,
            PermissionName::CampaignsCancelPause,
            PermissionName::SmtpView,
            PermissionName::ReportingView,
            PermissionName::ReportingExport,
            PermissionName::SuppressionView,
        ];

        $viewer = [
            PermissionName::DashboardView,
            PermissionName::TemplatesView,
            PermissionName::RecipientsView,
            PermissionName::CampaignsView,
            PermissionName::ReportingView,
        ];

        return [
            RoleName::Administrator->value => $administrator,
            RoleName::MarketingManager->value => $marketingManager,
            RoleName::MarketingOperator->value => $marketingOperator,
            RoleName::Viewer->value => $viewer,
        ];
    }
}
