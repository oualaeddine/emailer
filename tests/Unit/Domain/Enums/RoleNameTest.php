<?php

namespace Tests\Unit\Domain\Enums;

use App\Domain\Enums\RoleName;
use PHPUnit\Framework\TestCase;

/**
 * docs/26-rbac.md §26.2 — exactly four fixed roles for v1.
 */
class RoleNameTest extends TestCase
{
    public function test_it_defines_exactly_the_four_documented_roles(): void
    {
        $this->assertCount(4, RoleName::cases());
        $this->assertSame(
            ['administrator', 'marketing_manager', 'marketing_operator', 'viewer'],
            array_map(fn (RoleName $r) => $r->value, RoleName::cases()),
        );
    }

    public function test_every_role_has_a_non_empty_french_label_and_description(): void
    {
        foreach (RoleName::cases() as $role) {
            $this->assertNotSame('', trim($role->label()));
            $this->assertNotSame('', trim($role->description()));
        }
    }
}
