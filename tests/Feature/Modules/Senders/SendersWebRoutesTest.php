<?php

namespace Tests\Feature\Modules\Senders;

use App\Domain\Enums\RoleName;
use App\Modules\Identity\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SendersWebRoutesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    /** @return array<string, array{string}> */
    public static function urls(): array
    {
        return [
            'senders' => ['/settings/senders'],
            'domains' => ['/settings/domains'],
        ];
    }

    #[DataProvider('urls')]
    public function test_administrator_can_access_page(string $url): void
    {
        $admin = User::factory()->withRole(RoleName::Administrator)->withTwoFactor()->create();

        $this->actingAs($admin)->get($url)->assertOk();
    }

    #[DataProvider('urls')]
    public function test_viewer_is_blocked_from_page(string $url): void
    {
        $viewer = User::factory()->withRole(RoleName::Viewer)->withTwoFactor()->create();

        $this->actingAs($viewer)->get($url)->assertStatus(403);
    }
}
