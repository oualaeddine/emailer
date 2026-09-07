<?php

namespace Tests\Feature\Modules\Identity;

use App\Domain\Enums\RoleName;
use App\Modules\Identity\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;
use Tests\TestCase;

class SanctumStatefulDomainTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_production_domain_is_recognized_as_stateful_frontend(): void
    {
        $request = Request::create(
            '/api/v1/notifications/unread-count',
            'GET',
            [],
            [],
            [],
            [
                'HTTP_HOST' => 'emailer.pagesjaunes-dz.com',
                'HTTP_REFERER' => 'https://emailer.pagesjaunes-dz.com/dashboard',
            ],
        );

        $this->assertTrue(
            EnsureFrontendRequestsAreStateful::fromFrontend($request),
            'emailer.pagesjaunes-dz.com must be recognized as a stateful frontend by Sanctum'
        );
    }

    public function test_authenticated_user_can_access_api_from_production_domain_without_401(): void
    {
        $user = User::factory()->withRole(RoleName::Administrator)->withTwoFactor()->create();

        $response = $this->actingAs($user, 'web')
            ->withHeaders([
                'Host' => 'emailer.pagesjaunes-dz.com',
                'Referer' => 'https://emailer.pagesjaunes-dz.com/dashboard',
                'Accept' => 'application/json',
            ])
            ->get('/api/v1/notifications/unread-count');

        $response->assertOk();
    }
}
