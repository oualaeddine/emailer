<?php

namespace Tests\Feature\Modules\Transactional;

use App\Domain\Enums\RoleName;
use App\Modules\Identity\DataTransferObjects\UpdateUserData;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\UserService;
use App\Modules\Templates\Services\HostResolver;
use App\Modules\Transactional\Services\ApiKeyService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

/**
 * docs/45 §45.7.6 — API keys only reach the routes tagged `RequireApiScope`
 * (RestrictApiKeysToTaggedRoutes), with the matching scope; session users are
 * unaffected; keys of deactivated / deleted owners stop authenticating.
 */
class ApiKeyRouteScopeTest extends TestCase
{
    use RefreshDatabase;

    private const WEBHOOK = [
        'url' => 'https://hooks.exemple.dz/mail',
        'description' => 'ERP',
        'type' => 'transactional',
        'events' => ['sent', 'delivered'],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        Queue::fake();
        $this->app->instance(HostResolver::class, new class extends HostResolver
        {
            public function resolve(string $host): array
            {
                return str_starts_with($host, 'hooks.') ? ['93.184.216.34'] : [];
            }
        });
    }

    private function admin(): User
    {
        return User::factory()->withRole(RoleName::Administrator)->create();
    }

    /** @param list<string> $scopes */
    private function key(array $scopes, ?User $owner = null): string
    {
        [, $plain] = app(ApiKeyService::class)->create($owner ?? $this->admin(), ['name' => 'ERP', 'scopes' => $scopes]);

        return $plain;
    }

    /** @param array<string, mixed> $body */
    private function asKey(string $plain, string $method, string $uri, array $body = []): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->withHeaders(['api-key' => $plain])->json($method, '/api/v1/'.$uri, $body);
    }

    public function test_transactional_key_of_an_administrator_is_rejected_on_untagged_routes(): void
    {
        $plain = $this->key(['transactional:read', 'transactional:send']);

        $this->asKey($plain, 'GET', 'webhooks')->assertForbidden();
        $this->asKey($plain, 'GET', 'campaigns')->assertForbidden();
        $this->asKey($plain, 'POST', 'webhooks', self::WEBHOOK)->assertForbidden();
        $this->asKey($plain, 'GET', 'automations')->assertForbidden();
        $this->asKey($plain, 'GET', 'recipients')->assertForbidden();
        $this->asKey($plain, 'GET', 'api-keys')->assertForbidden();
        $this->asKey($plain, 'GET', 'me')->assertForbidden();
        // Rejected before route-model binding: 403, not 404.
        $this->asKey($plain, 'GET', 'campaigns/'.Str::uuid())->assertForbidden();
        $this->assertDatabaseCount('api_webhooks', 0);

        // Its own tagged route still works.
        $this->asKey($plain, 'GET', 'transactional/logs')->assertOk();
    }

    public function test_tagged_routes_require_the_matching_scope(): void
    {
        $owner = $this->admin();
        $campaigns = $this->key(['campaigns:read'], $owner);
        $contacts = $this->key(['contacts:read'], $owner);
        $writer = $this->key(['contacts:write'], $owner);
        $stats = $this->key(['statistics:read'], $owner);

        $this->asKey($campaigns, 'GET', 'campaigns')->assertOk();
        $this->asKey($contacts, 'GET', 'campaigns')->assertForbidden();

        $this->asKey($contacts, 'GET', 'recipients')->assertOk();
        $this->asKey($campaigns, 'GET', 'recipients')->assertForbidden();

        $this->asKey($contacts, 'POST', 'recipients', ['email' => 'a@exemple.dz', 'source' => 'api'])->assertForbidden();
        $this->asKey($writer, 'POST', 'recipients', ['email' => 'a@exemple.dz', 'source' => 'api'])->assertCreated();

        $this->asKey($stats, 'GET', 'statistics/summary')->assertOk();
        $this->asKey($campaigns, 'GET', 'statistics/summary')->assertForbidden();

        // Tagged route, but not one of the key's scopes nor a contract route: webhooks stay closed.
        $this->asKey($writer, 'POST', 'webhooks', self::WEBHOOK)->assertForbidden();
    }

    public function test_session_users_are_unaffected(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->getJson('/api/v1/webhooks')->assertOk();
        $this->actingAs($admin)->getJson('/api/v1/campaigns')->assertOk();
        $this->actingAs($admin)->getJson('/api/v1/recipients')->assertOk();
        $this->actingAs($admin)->getJson('/api/v1/automations')->assertOk();
        $this->actingAs($admin)->getJson('/api/v1/statistics/summary')->assertOk();
        $this->actingAs($admin)->postJson('/api/v1/webhooks', self::WEBHOOK)->assertCreated();
    }

    public function test_keys_of_deactivated_or_deleted_owners_stop_working(): void
    {
        $owner = $this->admin();
        $plain = $this->key(['campaigns:read'], $owner);
        $this->asKey($plain, 'GET', 'campaigns')->assertOk();

        // Flag flipped directly: the Sanctum callback rejects the token.
        $owner->forceFill(['is_active' => false])->save();
        $this->asKey($plain, 'GET', 'campaigns')->assertUnauthorized();
        $owner->forceFill(['is_active' => true])->save();
        $this->asKey($plain, 'GET', 'campaigns')->assertOk();

        // Soft-deleted owner.
        $owner->delete();
        $this->asKey($plain, 'GET', 'campaigns')->assertUnauthorized();
        $owner->restore();

        // Deactivation through UserService revokes the tokens.
        app(UserService::class)->update($owner, new UpdateUserData(isActive: false), $this->admin());
        $this->assertSame(0, PersonalAccessToken::query()->where('tokenable_id', $owner->id)->count());
        $this->asKey($plain, 'GET', 'campaigns')->assertUnauthorized();
    }
}
