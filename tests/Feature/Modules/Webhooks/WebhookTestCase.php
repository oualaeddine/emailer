<?php

namespace Tests\Feature\Modules\Webhooks;

use App\Domain\Enums\RoleName;
use App\Modules\Identity\Models\User;
use App\Modules\Templates\Services\HostResolver;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

abstract class WebhookTestCase extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        // Deterministic DNS: hooks.* resolve to a public IP, intranet.* to a private one.
        $this->app->instance(HostResolver::class, new class extends HostResolver
        {
            public function resolve(string $host): array
            {
                if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
                    return [$host];
                }

                return match (true) {
                    str_starts_with($host, 'hooks.') => ['93.184.216.34'],
                    str_starts_with($host, 'intranet.') => ['10.0.0.5'],
                    default => [],
                };
            }
        });
    }

    protected function admin(): User
    {
        return User::factory()->withRole(RoleName::Administrator)->withTwoFactor()->create();
    }

    protected function manager(): User
    {
        return User::factory()->withRole(RoleName::MarketingManager)->withTwoFactor()->create();
    }
}
