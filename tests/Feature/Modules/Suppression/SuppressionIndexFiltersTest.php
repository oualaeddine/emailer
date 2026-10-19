<?php

namespace Tests\Feature\Modules\Suppression;

use App\Domain\Enums\RoleName;
use App\Modules\Identity\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class SuppressionIndexFiltersTest extends TestCase
{
    use RefreshDatabase;

    private function seedEntries(): User
    {
        $this->seed(RolePermissionSeeder::class);
        foreach ([['b@x.com', 'hard_bounce', '2026-01-01'], ['a@x.com', 'spam_complaint', '2026-01-02'], ['c@x.com', 'manual_block', '2026-01-03']] as [$e, $r, $d]) {
            DB::table('suppression_entries')->insert(['uuid' => (string) Str::uuid(), 'email' => $e, 'reason' => $r, 'created_at' => $d]);
        }

        return User::factory()->withRole(RoleName::MarketingManager)->create();
    }

    public function test_reason_array_sort_and_per_page(): void
    {
        $u = $this->seedEntries();

        $res = $this->actingAs($u)->getJson('/api/v1/suppression-entries?reason[]=hard_bounce&reason[]=spam_complaint&sort=email')->assertOk();
        $this->assertSame(['a@x.com', 'b@x.com'], array_column($res->json('data'), 'email'));

        $res = $this->actingAs($u)->getJson('/api/v1/suppression-entries?per_page=2')->assertOk();
        $this->assertCount(2, $res->json('data'));
        $this->assertSame(3, $res->json('meta.total'));
        $this->assertSame('c@x.com', $res->json('data.0.email'));
    }

    public function test_scalar_reason_still_works_and_limits_validated(): void
    {
        $u = $this->seedEntries();

        $res = $this->actingAs($u)->getJson('/api/v1/suppression-entries?reason=manual_block')->assertOk();
        $this->assertSame(['c@x.com'], array_column($res->json('data'), 'email'));

        $this->actingAs($u)->getJson('/api/v1/suppression-entries?per_page=101')->assertStatus(422);
        $this->actingAs($u)->getJson('/api/v1/suppression-entries?reason[]=nope')->assertStatus(422);
    }
}
