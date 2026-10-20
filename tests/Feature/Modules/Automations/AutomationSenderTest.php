<?php

namespace Tests\Feature\Modules\Automations;

use App\Domain\Enums\RoleName;
use App\Modules\Automations\Models\Automation;
use App\Modules\Automations\Services\AutomationEmailSender;
use App\Modules\Automations\Services\TriggerDispatcher;
use App\Modules\DeliveryEngine\Jobs\SendEmailJob;
use App\Modules\DeliveryEngine\Models\SmtpAccount;
use App\Modules\DeliveryEngine\Services\SmtpManagerContract;
use App\Modules\Identity\Models\User;
use App\Modules\Senders\Models\Sender;
use App\Modules\Tracking\Models\Message;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Symfony\Component\Mime\Email;
use Tests\Feature\Modules\Automations\Concerns\BuildsAutomationFixtures;
use Tests\Support\FakeSmtpManager;
use Tests\TestCase;

/** next-sender — the `send_email` step's sender reaches the delivered e-mail. */
class AutomationSenderTest extends TestCase
{
    use BuildsAutomationFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        Bus::fake([SendEmailJob::class]);
        FakeSmtpManager::reset();
        $this->app->bind(SmtpManagerContract::class, FakeSmtpManager::class);
        SmtpAccount::query()->create([
            'name' => 'Compte Test', 'provider' => 'custom', 'host' => 'smtp.example.com', 'port' => 587,
            'encryption' => 'tls', 'username' => 'smtp-user', 'password_encrypted' => 'secret',
            'from_email' => 'relay@smtp-provider.dz', 'from_name' => 'Relais',
        ]);
    }

    protected function tearDown(): void
    {
        FakeSmtpManager::reset();
        parent::tearDown();
    }

    private function sendWithSender(?Sender $sender): Email
    {
        $template = $this->template();
        $automation = $this->activeAutomation('list_added', ['list_ids' => []], [
            $this->sendEmailStep($template, ['config' => ['template_id' => $template->uuid, 'subject' => null, 'sender_id' => $sender?->uuid, 'preview_text' => null]]),
        ]);
        app(TriggerDispatcher::class)->start($automation, $this->contact(['email' => 'amina@exemple.dz'])->id, ['trigger' => 'test']);

        $message = Message::query()->sole();
        $this->assertSame($sender?->isVerified() ? $sender->id : null, $message->sender_id);
        $this->app->call([new SendEmailJob($message->id), 'handle']);
        $this->assertCount(1, FakeSmtpManager::$sent);

        return FakeSmtpManager::$sent[0]['email'];
    }

    public function test_step_sender_is_the_from_through_the_transactional_contract(): void
    {
        $this->assertTrue(app()->bound(AutomationEmailSender::TRANSACTIONAL_SENDER));
        $sender = Sender::factory()->verified()->create(['name' => 'PageJaunes Accueil', 'email' => 'accueil@pagejaunes.dz', 'reply_to' => 'contact@pagejaunes.dz']);

        $email = $this->sendWithSender($sender);

        $this->assertSame('accueil@pagejaunes.dz', $email->getFrom()[0]->getAddress());
        $this->assertSame('PageJaunes Accueil', $email->getFrom()[0]->getName());
        $this->assertSame('relay@smtp-provider.dz', $email->getSender()?->getAddress());
        $this->assertSame('contact@pagejaunes.dz', $email->getReplyTo()[0]->getAddress());
    }

    public function test_step_sender_is_the_from_on_the_fallback_path(): void
    {
        $this->app->offsetUnset(AutomationEmailSender::TRANSACTIONAL_SENDER);
        $this->assertFalse(app()->bound(AutomationEmailSender::TRANSACTIONAL_SENDER));
        $sender = Sender::factory()->verified()->create(['email' => 'accueil@pagejaunes.dz']);

        $email = $this->sendWithSender($sender);

        $this->assertSame('accueil@pagejaunes.dz', $email->getFrom()[0]->getAddress());
        $this->assertSame('relay@smtp-provider.dz', $email->getSender()?->getAddress());
    }

    public function test_unverified_step_sender_keeps_the_smtp_account_from(): void
    {
        $email = $this->sendWithSender(Sender::factory()->create(['email' => 'pas-verifie@pagejaunes.dz']));

        $this->assertSame('relay@smtp-provider.dz', $email->getFrom()[0]->getAddress());
        $this->assertNull($email->getSender());
    }

    public function test_sender_must_exist_and_be_verified_at_activation_only(): void
    {
        $this->actingAs(User::factory()->withRole(RoleName::MarketingManager)->create());
        $template = $this->template();
        $unverified = Sender::factory()->create();
        $automation = Automation::factory()->trigger('list_added', ['list_ids' => [$this->list()->uuid]])->create();
        $step = fn (?string $senderId) => [$this->sendEmailStep($template, ['config' => ['template_id' => $template->uuid, 'sender_id' => $senderId]])];

        $this->putJson("/api/v1/automations/{$automation->uuid}/steps", ['steps' => $step('00000000-0000-4000-8000-000000000000')])
            ->assertUnprocessable()->assertJsonValidationErrors('steps.0.config.sender_id');

        // Drafts accept an unverified sender…
        $this->putJson("/api/v1/automations/{$automation->uuid}/steps", ['steps' => $step($unverified->uuid)])->assertOk();
        // …activation does not.
        $this->postJson("/api/v1/automations/{$automation->uuid}/activate")
            ->assertUnprocessable()->assertJsonValidationErrors('steps.0.config.sender_id');

        $unverified->update(['verified_at' => now()]);
        $this->postJson("/api/v1/automations/{$automation->uuid}/activate")->assertOk()->assertJsonPath('data.status', 'active');
    }
}
