<?php

namespace Tests\Feature\Modules\Identity;

use App\Domain\Enums\RoleName;
use App\Modules\Identity\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

/**
 * docs/26-rbac.md §26.3 — Full Permission Matrix.
 *
 * This grid is transcribed independently from the documentation table
 * (not derived from RolePermissionSeeder's own matrix()) so this test
 * actually catches drift between the seeder and the documented source of
 * truth, rather than checking the seeder against itself.
 */
class RbacMatrixTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{0: string, 1: bool, 2: bool, 3: bool, 4: bool}>
     *              permission => [administrator, marketing_manager, marketing_operator, viewer]
     */
    public static function matrix(): array
    {
        return [
            'dashboard.view' => [true, true, true, true],
            'mailbox.view_own' => [true, true, true, false],
            'mailbox.view_all' => [true, true, false, false],
            'composer.compose' => [true, true, true, false],
            'composer.send' => [true, true, true, false],
            'templates.view' => [true, true, true, true],
            'templates.manage' => [true, true, false, false],
            'recipients.view' => [true, true, true, true],
            'recipients.manage' => [true, true, true, false],
            'recipients.import' => [true, true, true, false],
            'recipients.verify' => [true, true, true, false],
            'recipients.annotate' => [true, true, true, false],
            'segments.manage' => [true, true, true, false],
            'campaigns.view' => [true, true, true, true],
            'campaigns.create' => [true, true, true, false],
            'campaigns.send' => [true, true, true, false],
            'campaigns.send_any' => [true, true, false, false],
            'campaigns.approve' => [true, true, false, false],
            'campaigns.cancel_pause' => [true, true, true, false],
            'smtp.view' => [true, true, true, false],
            'smtp.manage_credentials' => [true, false, false, false],
            'smtp.manage_quotas_rotation' => [true, false, false, false],
            'smtp.test' => [true, false, false, false],
            'queues.view' => [true, true, false, false],
            'reporting.view' => [true, true, true, true],
            'reporting.export' => [true, true, true, false],
            'suppression.view' => [true, true, true, false],
            'suppression.manage' => [true, true, false, false],
            'audit.view' => [true, false, false, false],
            'audit.export' => [true, false, false, false],
            'users.manage' => [true, false, false, false],
            'settings.manage' => [true, false, false, false],
            'settings.branding_only' => [true, true, false, false],
        ];
    }

    public function test_every_role_matches_the_documented_permission_matrix(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $roleIndex = [
            RoleName::Administrator->value => 0,
            RoleName::MarketingManager->value => 1,
            RoleName::MarketingOperator->value => 2,
            RoleName::Viewer->value => 3,
        ];

        $users = [];
        foreach (array_keys($roleIndex) as $roleName) {
            $users[$roleName] = User::factory()->withRole(RoleName::from($roleName))->create();
        }

        foreach (self::matrix() as $permission => $expectations) {
            foreach ($roleIndex as $roleName => $index) {
                $expected = $expectations[$index];
                $actual = Gate::forUser($users[$roleName])->allows($permission);

                $this->assertSame(
                    $expected,
                    $actual,
                    sprintf(
                        'Expected role "%s" to %s permission "%s" (docs/26-rbac.md §26.3).',
                        $roleName,
                        $expected ? 'HAVE' : 'NOT HAVE',
                        $permission,
                    ),
                );
            }
        }
    }
}
