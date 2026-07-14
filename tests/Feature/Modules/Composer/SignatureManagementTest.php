<?php

namespace Tests\Feature\Modules\Composer;

use App\Domain\Enums\RoleName;
use App\Modules\Composer\Models\Signature;
use App\Modules\Identity\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * docs/04-database-design.md §4.3 — only one `is_default=true` signature per user.
 */
class SignatureManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_creating_a_second_default_signature_unsets_the_first(): void
    {
        $operator = User::factory()->withRole(RoleName::MarketingOperator)->create();

        $first = $this->actingAs($operator)->postJson('/api/v1/signatures', [
            'name' => 'Signature pro',
            'html_content' => '<p>Cordialement</p>',
            'is_default' => true,
        ])->json('data.id');

        $this->actingAs($operator)->postJson('/api/v1/signatures', [
            'name' => 'Signature perso',
            'html_content' => '<p>Bien à vous</p>',
            'is_default' => true,
        ])->assertCreated();

        $firstSignature = Signature::query()->where('uuid', $first)->first();
        $this->assertFalse($firstSignature->is_default);
        $this->assertSame(1, Signature::query()->where('user_id', $operator->id)->where('is_default', true)->count());
    }

    public function test_a_user_cannot_edit_another_users_signature(): void
    {
        $owner = User::factory()->withRole(RoleName::MarketingOperator)->create();
        $other = User::factory()->withRole(RoleName::MarketingOperator)->create();

        $signature = Signature::query()->create([
            'user_id' => $owner->id,
            'name' => 'Sig',
            'html_content' => '<p>Sig</p>',
            'is_default' => false,
        ]);

        $this->actingAs($other)->patchJson("/api/v1/signatures/{$signature->uuid}", [
            'name' => 'Piraté',
            'html_content' => '<p>x</p>',
        ])->assertForbidden();
    }
}
