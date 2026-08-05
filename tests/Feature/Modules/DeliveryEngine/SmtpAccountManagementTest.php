<?php

namespace Tests\Feature\Modules\DeliveryEngine;

use App\Domain\Enums\RoleName;
use App\Modules\DeliveryEngine\Models\SmtpAccount;
use App\Modules\DeliveryEngine\Services\SmtpConnectionTesterContract;
use App\Modules\Identity\Models\User;
use App\Modules\Tracking\Models\Message;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakeSmtpConnectionTester;
use Tests\TestCase;

/**
 * docs/29-api-specification.md §29.6, docs/18-smtp-management.md.
 */
class SmtpAccountManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->app->bind(SmtpConnectionTesterContract::class, FakeSmtpConnectionTester::class);
        FakeSmtpConnectionTester::$shouldSucceed = true;
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Compte principal',
            'provider' => 'custom',
            'host' => 'smtp.example.com',
            'port' => 587,
            'encryption' => 'tls',
            'username' => 'smtp-user',
            'password' => 'super-secret-password',
            'from_email' => 'no-reply@example.com',
        ], $overrides);
    }

    public function test_administrator_can_create_an_smtp_account_and_password_is_never_returned(): void
    {
        $admin = User::factory()->withRole(RoleName::Administrator)->create();

        $response = $this->actingAs($admin)->postJson('/api/v1/smtp-accounts', $this->payload());

        $response->assertCreated();
        $response->assertJsonMissingPath('data.password');
        $response->assertJsonMissingPath('data.password_encrypted');

        $account = SmtpAccount::query()->first();
        $this->assertSame('super-secret-password', $account->password_encrypted);
    }

    public function test_non_administrator_cannot_manage_smtp_credentials(): void
    {
        $manager = User::factory()->withRole(RoleName::MarketingManager)->create();

        $this->actingAs($manager)->postJson('/api/v1/smtp-accounts', $this->payload())->assertForbidden();
    }

    public function test_manager_and_operator_can_view_smtp_accounts_read_only(): void
    {
        $admin = User::factory()->withRole(RoleName::Administrator)->create();
        $manager = User::factory()->withRole(RoleName::MarketingManager)->create();
        $this->actingAs($admin)->postJson('/api/v1/smtp-accounts', $this->payload());

        $this->actingAs($manager)->getJson('/api/v1/smtp-accounts')->assertOk();
    }

    public function test_blank_password_on_update_keeps_the_existing_credential(): void
    {
        $admin = User::factory()->withRole(RoleName::Administrator)->create();
        $created = $this->actingAs($admin)->postJson('/api/v1/smtp-accounts', $this->payload())->json('data');

        $this->actingAs($admin)->patchJson("/api/v1/smtp-accounts/{$created['id']}", [
            'name' => 'Nom modifié',
            'password' => '',
        ])->assertOk();

        $account = SmtpAccount::query()->where('uuid', $created['id'])->first();
        $this->assertSame('Nom modifié', $account->name);
        $this->assertSame('super-secret-password', $account->password_encrypted);
    }

    public function test_testing_a_connection_returns_the_fake_transports_response(): void
    {
        $admin = User::factory()->withRole(RoleName::Administrator)->create();
        $created = $this->actingAs($admin)->postJson('/api/v1/smtp-accounts', $this->payload())->json('data');

        $response = $this->actingAs($admin)->postJson("/api/v1/smtp-accounts/{$created['id']}/test");

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('raw_response', '250 OK (fake)');
    }

    public function test_a_failed_test_connection_is_reported_without_erroring(): void
    {
        FakeSmtpConnectionTester::$shouldSucceed = false;
        $admin = User::factory()->withRole(RoleName::Administrator)->create();
        $created = $this->actingAs($admin)->postJson('/api/v1/smtp-accounts', $this->payload())->json('data');

        $response = $this->actingAs($admin)->postJson("/api/v1/smtp-accounts/{$created['id']}/test");

        $response->assertOk();
        $response->assertJsonPath('success', false);
    }

    public function test_an_account_referenced_by_send_attempts_cannot_be_hard_deleted(): void
    {
        $admin = User::factory()->withRole(RoleName::Administrator)->create();
        $account = SmtpAccount::query()->create([
            ...$this->payload(),
            'password_encrypted' => 'secret',
            'health_status' => 'healthy',
        ]);
        $message = Message::factory()->create();
        $account->sendAttempts()->create([
            'message_id' => $message->id,
            'attempt_number' => 1,
            'status' => 'success',
            'attempted_at' => now(),
        ]);

        $this->actingAs($admin)->deleteJson("/api/v1/smtp-accounts/{$account->uuid}")->assertStatus(409);
    }

    public function test_an_unused_account_can_be_deleted(): void
    {
        $admin = User::factory()->withRole(RoleName::Administrator)->create();
        $account = SmtpAccount::query()->create([
            ...$this->payload(),
            'password_encrypted' => 'secret',
            'health_status' => 'healthy',
        ]);

        $this->actingAs($admin)->deleteJson("/api/v1/smtp-accounts/{$account->uuid}")->assertNoContent();
    }
}
