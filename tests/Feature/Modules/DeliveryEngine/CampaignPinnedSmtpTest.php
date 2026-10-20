<?php

namespace Tests\Feature\Modules\DeliveryEngine;

use App\Domain\Enums\RoleName;
use App\Domain\Enums\SmtpHealthStatus;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\DeliveryEngine\Jobs\SendEmailJob;
use App\Modules\DeliveryEngine\Models\SmtpAccount;
use App\Modules\DeliveryEngine\Services\SmtpManagerContract;
use App\Modules\Identity\Models\User;
use App\Modules\Recipients\Models\Recipient;
use App\Modules\Tracking\Models\Message;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\Feature\Modules\Campaigns\Concerns\BuildsCampaignFixtures;
use Tests\Support\FakeSmtpManager;
use Tests\TestCase;

/**
 * gap-approval — campaign SMTP strategy « Compte unique »: the rotation is
 * restricted to the campaign's `single_smtp_account_id`.
 */
class CampaignPinnedSmtpTest extends TestCase
{
    use BuildsCampaignFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        FakeSmtpManager::reset();
        $this->app->bind(SmtpManagerContract::class, FakeSmtpManager::class);
    }

    protected function tearDown(): void
    {
        FakeSmtpManager::reset();
        parent::tearDown();
    }

    private function account(string $name, int $priority, array $overrides = []): SmtpAccount
    {
        return SmtpAccount::query()->create(array_merge([
            'name' => $name, 'provider' => 'custom', 'host' => 'smtp.example.com', 'port' => 587,
            'encryption' => 'tls', 'username' => 'u', 'password_encrypted' => 'secret',
            'from_email' => strtolower($name).'@example.com', 'priority' => $priority,
        ], $overrides));
    }

    private function message(array $campaign): Message
    {
        $campaign = Campaign::factory()->create($campaign);

        return Message::factory()->create([
            'campaign_id' => $campaign->id,
            'recipient_id' => Recipient::factory()->create(['email' => 'amina@exemple.dz'])->id,
        ]);
    }

    private function sendMessage(Message $message): void
    {
        $this->app->call([new SendEmailJob($message->id), 'handle']);
    }

    public function test_rotation_strategy_uses_the_best_priority_account(): void
    {
        $best = $this->account('Principal', 1);
        $this->account('Secours', 5);

        Bus::fake();
        $this->sendMessage($this->message(['smtp_strategy' => 'auto_rotate']));

        $this->assertSame($best->id, FakeSmtpManager::$sent[0]['account_id']);
    }

    public function test_single_strategy_sends_only_through_the_chosen_account(): void
    {
        $this->account('Principal', 1);
        $chosen = $this->account('Secours', 5);

        Bus::fake();
        $message = $this->message(['smtp_strategy' => 'pinned', 'single_smtp_account_id' => $chosen->id]);
        $this->sendMessage($message);

        $this->assertCount(1, FakeSmtpManager::$sent);
        $this->assertSame($chosen->id, FakeSmtpManager::$sent[0]['account_id']);
        $this->assertSame($chosen->id, $message->fresh()->smtp_account_id);
    }

    public function test_single_strategy_waits_instead_of_falling_back_when_the_account_is_unavailable(): void
    {
        $this->account('Principal', 1);
        $chosen = $this->account('Secours', 5);
        $chosen->forceFill(['health_status' => SmtpHealthStatus::Unhealthy->value])->save();

        Bus::fake();
        $this->sendMessage($this->message(['smtp_strategy' => 'pinned', 'single_smtp_account_id' => $chosen->id]));

        $this->assertCount(0, FakeSmtpManager::$sent);
        Bus::assertDispatched(SendEmailJob::class, fn (SendEmailJob $job) => $job->delay !== null);
    }

    public function test_single_strategy_requires_an_account_to_schedule(): void
    {
        Bus::fake();
        $user = User::factory()->withRole(RoleName::MarketingManager)->create();
        $campaign = $this->campaignWithAudience($user, ['lists' => [$this->listWith($this->recipients(1))->id]], ['smtp_strategy' => 'pinned']);

        $this->actingAs($user)->postJson("/api/v1/campaigns/{$campaign->uuid}/schedule", ['mode' => 'now'])
            ->assertStatus(422)
            ->assertJsonFragment(['message' => 'Choisissez le compte SMTP unique à utiliser, ou repassez en rotation automatique.']);

        $account = $this->account('Secours', 5);
        $campaign->update(['single_smtp_account_id' => $account->id]);
        $this->actingAs($user)->postJson("/api/v1/campaigns/{$campaign->uuid}/schedule", ['mode' => 'now'])->assertOk();
    }
}
