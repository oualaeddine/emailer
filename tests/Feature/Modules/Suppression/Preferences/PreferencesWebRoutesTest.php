<?php

namespace Tests\Feature\Modules\Suppression\Preferences;

use App\Domain\Enums\RoleName;
use App\Modules\Identity\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PreferencesWebRoutesTest extends TestCase
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
            'unsubscribe pages' => ['/settings/unsubscribe-pages'],
            'update profile forms' => ['/settings/update-profile-forms'],
        ];
    }

    #[DataProvider('urls')]
    public function test_administrator_can_access_page(string $url): void
    {
        $admin = User::factory()->withRole(RoleName::Administrator)->withTwoFactor()->create();

        $this->actingAs($admin)->get($url)->assertOk();
    }

    #[DataProvider('urls')]
    public function test_viewer_is_blocked(string $url): void
    {
        $viewer = User::factory()->withRole(RoleName::Viewer)->withTwoFactor()->create();

        $this->actingAs($viewer)->get($url)->assertStatus(403);
    }

    #[DataProvider('urls')]
    public function test_guest_is_redirected_to_login(string $url): void
    {
        $this->get($url)->assertRedirect();
    }
}
