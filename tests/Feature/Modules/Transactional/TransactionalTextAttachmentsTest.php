<?php

namespace Tests\Feature\Modules\Transactional;

use App\Domain\Enums\MessageStatus;
use App\Domain\Enums\RoleName;
use App\Modules\DeliveryEngine\Jobs\SendEmailJob;
use App\Modules\DeliveryEngine\Models\SmtpAccount;
use App\Modules\DeliveryEngine\Services\SmtpManagerContract;
use App\Modules\Identity\Models\User;
use App\Modules\Tracking\Models\Message;
use App\Modules\Transactional\Services\ApiKeyService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Mime\Email;
use Tests\Support\FakeSmtpManager;
use Tests\TestCase;

/** gap-textatt — explicit text_content delivery and attachment pruning. */
class TransactionalTextAttachmentsTest extends TestCase
{
    use RefreshDatabase;

    private string $plain;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        Storage::fake('local');
        Queue::fake();
        FakeSmtpManager::reset();
        $this->app->bind(SmtpManagerContract::class, FakeSmtpManager::class);
        SmtpAccount::query()->create([
            'name' => 'Compte Test', 'provider' => 'custom', 'host' => 'smtp.example.com', 'port' => 587,
            'encryption' => 'tls', 'username' => 'smtp-user', 'password_encrypted' => 'secret',
            'from_email' => 'relay@smtp-provider.dz', 'from_name' => 'Relais',
        ]);
        [, $this->plain] = app(ApiKeyService::class)->create(
            User::factory()->withRole(RoleName::Administrator)->create(),
            ['name' => 'ERP', 'scopes' => ['transactional:send']],
        );
    }

    protected function tearDown(): void
    {
        FakeSmtpManager::reset();
        parent::tearDown();
    }

    /** @param array<string, mixed> $body */
    private function sendApi(array $body): Message
    {
        $this->app['auth']->forgetGuards();
        $res = $this->withHeader('Authorization', 'Bearer '.$this->plain)->postJson('/api/v1/transactional/send', $body + [
            'to' => [['email' => 'amina@exemple.dz', 'name' => 'Amina']],
            'subject' => 'Facture 1042',
            'html_content' => '<p>Bonjour {{ params.NAME }}</p>',
            'params' => ['NAME' => 'Amina & Co'],
        ])->assertCreated();

        return Message::query()->where('uuid', $res->json('data.message_ids.0'))->firstOrFail();
    }

    private function deliver(Message $message): Email
    {
        Bus::fake();
        FakeSmtpManager::reset();
        $this->app->call([new SendEmailJob($message->id), 'handle']);

        return FakeSmtpManager::$sent[0]['email'];
    }

    public function test_text_content_is_the_text_part_with_params_merged_unescaped(): void
    {
        $message = $this->sendApi(['text_content' => 'Bonjour {{ params.NAME }}, votre facture.']);

        $this->assertSame('Bonjour Amina & Co, votre facture.', $message->delivery_options['text']);

        $email = $this->deliver($message);
        $this->assertSame('Bonjour Amina & Co, votre facture.', $email->getTextBody());
        $this->assertStringContainsString('Amina &amp; Co', (string) $email->getHtmlBody());
    }

    public function test_generated_text_is_used_without_text_content(): void
    {
        $message = $this->sendApi([]);

        $this->assertArrayNotHasKey('text', (array) $message->delivery_options);
        $this->assertStringContainsString('Bonjour Amina & Co', (string) $this->deliver($message)->getTextBody());
    }

    private function withAttachment(string $status, int $ageDays): Message
    {
        $message = $this->sendApi(['attachments' => [['name' => 'a.pdf', 'content' => base64_encode('%PDF-1.4 x')]]]);
        $message->forceFill(['status' => $status])->save();
        DB::table('messages')->where('id', $message->id)->update(['created_at' => now()->subDays($ageDays)]);

        return $message->refresh();
    }

    public function test_prune_deletes_old_final_state_folders_and_clears_delivery_options(): void
    {
        $old = $this->withAttachment(MessageStatus::Delivered->value, 40);
        $path = $old->delivery_options['attachments'][0]['path'];
        Storage::disk('local')->assertExists($path);

        $this->artisan('transactional:prune-attachments')->assertSuccessful();

        Storage::disk('local')->assertMissing($path);
        $this->assertArrayNotHasKey('attachments', (array) $old->refresh()->delivery_options);
    }

    public function test_prune_keeps_recent_and_still_queued_messages(): void
    {
        $recent = $this->withAttachment(MessageStatus::Delivered->value, 5);
        $queued = $this->withAttachment(MessageStatus::Queued->value, 90);
        $sending = $this->withAttachment(MessageStatus::Sending->value, 90);

        $this->artisan('transactional:prune-attachments')->assertSuccessful();

        foreach ([$recent, $queued, $sending] as $message) {
            $path = $message->delivery_options['attachments'][0]['path'];
            Storage::disk('local')->assertExists($path);
            $this->assertNotEmpty($message->refresh()->delivery_options['attachments']);
        }
    }

    public function test_prune_keeps_a_shared_folder_while_one_message_is_still_queued(): void
    {
        $this->app['auth']->forgetGuards();
        $res = $this->withHeader('Authorization', 'Bearer '.$this->plain)->postJson('/api/v1/transactional/send', [
            'to' => [['email' => 'a@exemple.dz'], ['email' => 'b@exemple.dz']],
            'subject' => 'S', 'html_content' => '<p>x</p>',
            'attachments' => [['name' => 'a.pdf', 'content' => base64_encode('%PDF-1.4 x')]],
        ])->assertCreated();
        $messages = Message::query()->whereIn('uuid', $res->json('data.message_ids'))->get();
        $this->assertCount(2, $messages);
        DB::table('messages')->whereIn('id', $messages->pluck('id'))->update(['created_at' => now()->subDays(60)]);
        $messages[0]->forceFill(['status' => MessageStatus::Delivered->value])->save();

        $this->artisan('transactional:prune-attachments')->assertSuccessful();

        Storage::disk('local')->assertExists($messages[0]->delivery_options['attachments'][0]['path']);
    }
}
