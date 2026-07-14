<?php

namespace Tests\Unit\Domain\Enums;

use App\Domain\Enums\PermissionName;
use PHPUnit\Framework\TestCase;

/**
 * docs/26-rbac.md §26.3 — Full Permission Matrix has exactly 33 rows.
 * This is a pure unit test (no DB) guarding against silent drift between
 * the enum and the documented matrix.
 */
class PermissionNameTest extends TestCase
{
    public function test_it_defines_exactly_the_documented_permission_count(): void
    {
        $this->assertCount(33, PermissionName::cases());
    }

    public function test_every_permission_name_is_unique(): void
    {
        $values = array_map(fn (PermissionName $p) => $p->value, PermissionName::cases());

        $this->assertCount(count($values), array_unique($values));
    }

    public function test_module_is_derived_from_the_dot_prefix(): void
    {
        $this->assertSame('campaigns', PermissionName::CampaignsSendAny->module());
        $this->assertSame('smtp', PermissionName::SmtpManageCredentials->module());
        $this->assertSame('users', PermissionName::UsersManage->module());
    }

    public function test_every_permission_has_a_non_empty_french_label(): void
    {
        foreach (PermissionName::cases() as $permission) {
            $this->assertNotSame('', trim($permission->label()));
        }
    }
}
