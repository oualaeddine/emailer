<?php

namespace Tests\Feature\Modules\Transactional;

use App\Domain\Enums\RoleName;
use App\Modules\DeliveryEngine\Jobs\SendEmailJob;
use App\Modules\DeliveryEngine\Models\SmtpAccount;
use App\Modules\DeliveryEngine\Services\SmtpManagerContract;
use App\Modules\Identity\Models\User;
use App\Modules\Recipients\Models\Recipient;
use App\Modules\Senders\Models\Sender;
use App\Modules\Tracking\Models\Message;
use App\Modules\Transactional\Services\ApiKeyService;
use App\Modules\Transactional\Services\TransactionalAttachmentStore;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Mime\Email;
use Tests\Support\FakeSmtpManager;
use Tests\TestCase;

/**
 * next-sender — transactional messages are delivered with the sender,
 * reply-to, cc, bcc, custom headers and attachments they were sent with.
 */
class TransactionalDeliveryOptionsTest extends TestCase
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
    private function sendApi(array $body)
    {
        $this->app['auth']->forgetGuards();

        return $this->withHeader('Authorization', 'Bearer '.$this->plain)->postJson('/api/v1/transactional/send', $body + [
            'to' => [['email' => 'amina@exemple.dz', 'name' => 'Amina']],
            'subject' => 'Facture 1042',
            'html_content' => '<p>Votre facture</p>',
        ]);
    }

    private function deliver(Message $message): Email
    {
        Bus::fake();
        FakeSmtpManager::reset();
        $this->app->call([new SendEmailJob($message->id), 'handle']);
        $this->assertCount(1, FakeSmtpManager::$sent);

        return FakeSmtpManager::$sent[0]['email'];
    }

    public function test_migration_adds_sender_and_delivery_options_columns(): void
    {
        $this->assertTrue(Schema::hasColumn('messages', 'sender_id'));
        $this->assertTrue(Schema::hasColumn('messages', 'delivery_options'));
    }

    public function test_sender_reply_to_cc_bcc_headers_and_attachment_are_delivered(): void
    {
        $sender = Sender::factory()->verified()->create(['name' => 'PageJaunes Factures', 'email' => 'factures@pagejaunes.dz', 'reply_to' => 'contact@pagejaunes.dz']);
        $pdf = '%PDF-1.4 facture';

        $res = $this->sendApi([
            'sender_id' => $sender->uuid,
            'reply_to' => ['email' => 'service@pagejaunes.dz', 'name' => 'Service client'],
            'cc' => [['email' => 'Compta@Exemple.dz', 'name' => 'Compta']],
            'bcc' => [['email' => 'archive@exemple.dz']],
            'headers' => ['X-Order-Id' => '1042'],
            'attachments' => [['name' => '../../facture <1042>.pdf', 'content' => base64_encode($pdf)]],
        ])->assertCreated();

        $message = Message::query()->where('uuid', $res->json('data.message_ids.0'))->firstOrFail();
        $this->assertSame($sender->id, $message->sender_id);
        $stored = $message->delivery_options['attachments'][0];
        $this->assertSame('facture _1042_.pdf', $stored['name']);
        $this->assertSame("transactional-attachments/{$message->uuid}/1-facture _1042_.pdf", $stored['path']);
        $this->assertSame(strlen($pdf), $stored['size']);
        Storage::disk('local')->assertExists($stored['path']);

        $email = $this->deliver($message);
        $this->assertSame('factures@pagejaunes.dz', $email->getFrom()[0]->getAddress());
        $this->assertSame('PageJaunes Factures', $email->getFrom()[0]->getName());
        $this->assertSame('relay@smtp-provider.dz', $email->getSender()?->getAddress());
        $this->assertSame('service@pagejaunes.dz', $email->getReplyTo()[0]->getAddress());
        $this->assertSame('Service client', $email->getReplyTo()[0]->getName());
        $this->assertSame(['compta@exemple.dz'], array_map(fn ($a) => $a->getAddress(), $email->getCc()));
        $this->assertSame(['archive@exemple.dz'], array_map(fn ($a) => $a->getAddress(), $email->getBcc()));
        $this->assertSame('1042', $email->getHeaders()->get('X-Order-Id')?->getBodyAsString());
        $this->assertCount(1, $email->getAttachments());
        $this->assertSame('facture _1042_.pdf', $email->getAttachments()[0]->getFilename());
        $this->assertSame($pdf, $email->getAttachments()[0]->getBody());
        $this->assertNull($email->getHeaders()->get('List-Unsubscribe'));
    }

    public function test_sender_reply_to_is_used_when_the_request_has_none(): void
    {
        $sender = Sender::factory()->verified()->create(['email' => 'factures@pagejaunes.dz', 'reply_to' => 'contact@pagejaunes.dz']);
        $res = $this->sendApi(['sender' => ['email' => 'factures@pagejaunes.dz']])->assertCreated();

        $email = $this->deliver(Message::query()->where('uuid', $res->json('data.message_ids.0'))->firstOrFail());
        $this->assertSame('factures@pagejaunes.dz', $email->getFrom()[0]->getAddress());
        $this->assertSame('contact@pagejaunes.dz', $email->getReplyTo()[0]->getAddress());
    }

    public function test_no_sender_or_a_sender_no_longer_verified_keeps_the_smtp_account_from(): void
    {
        $res = $this->sendApi([])->assertCreated();
        $email = $this->deliver(Message::query()->where('uuid', $res->json('data.message_ids.0'))->firstOrFail());
        $this->assertSame('relay@smtp-provider.dz', $email->getFrom()[0]->getAddress());
        $this->assertNull($email->getSender());

        $sender = Sender::factory()->verified()->create(['email' => 'factures@pagejaunes.dz']);
        $res = $this->sendApi(['sender_id' => $sender->uuid])->assertCreated();
        $sender->update(['verified_at' => null]);
        $email = $this->deliver(Message::query()->where('uuid', $res->json('data.message_ids.0'))->firstOrFail());
        $this->assertSame('relay@smtp-provider.dz', $email->getFrom()[0]->getAddress());
        $this->assertNull($email->getSender());
    }

    public function test_reserved_headers_are_refused_and_never_override_the_envelope(): void
    {
        foreach (['From', 'subject', 'Sender', 'List-Unsubscribe', 'X-Mailer-Event', 'Bad Name'] as $name) {
            $this->sendApi(['headers' => [$name => 'pirate@exemple.dz']])->assertStatus(422)->assertJsonValidationErrors('headers');
        }
        $this->assertSame(0, Message::query()->count());

        // A row written by other means still cannot override them at delivery.
        $recipient = Recipient::factory()->create(['email' => 'karim@exemple.dz']);
        $message = Message::factory()->create([
            'recipient_id' => $recipient->id,
            'campaign_id' => null,
            'subject' => 'Original',
            'delivery_options' => ['headers' => ['From' => 'pirate@exemple.dz', 'Subject' => 'Pirate', 'X-Mailer-Event' => 'x', 'X-Ok' => 'oui']],
        ]);
        $email = $this->deliver($message);
        $this->assertSame('relay@smtp-provider.dz', $email->getFrom()[0]->getAddress());
        $this->assertCount(1, iterator_to_array($email->getHeaders()->all('From'), false));
        $this->assertSame('Original', $email->getSubject());
        $this->assertNull($email->getHeaders()->get('X-Mailer-Event'));
        $this->assertSame('oui', $email->getHeaders()->get('X-Ok')?->getBodyAsString());
    }

    public function test_attachment_limits_and_url_attachments_are_rejected(): void
    {
        $tooBig = base64_encode(str_repeat('a', TransactionalAttachmentStore::MAX_TOTAL_BYTES / 2 + 1));
        $this->sendApi(['attachments' => [['name' => 'a.bin', 'content' => $tooBig], ['name' => 'b.bin', 'content' => $tooBig]]])
            ->assertStatus(422)->assertJsonValidationErrors('attachments');

        $this->sendApi(['attachments' => [['name' => 'a.pdf', 'url' => 'https://exemple.dz/a.pdf']]])
            ->assertStatus(422)->assertJsonValidationErrors('attachments.0.url');

        $this->sendApi(['attachments' => [['name' => 'a.pdf', 'content' => 'pas du base64 !']]])
            ->assertStatus(422)->assertJsonValidationErrors('attachments.0.content');

        $this->assertSame(0, Message::query()->count());
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_filename_sanitiser(): void
    {
        $this->assertSame('passwd', TransactionalAttachmentStore::sanitizeFilename('../../etc/passwd'));
        $this->assertSame('rapport_2026.pdf', TransactionalAttachmentStore::sanitizeFilename("C:\\temp\\rapport\n2026.pdf"));
        $this->assertSame('piece-jointe', TransactionalAttachmentStore::sanitizeFilename('...'));
        $long = TransactionalAttachmentStore::sanitizeFilename(str_repeat('é', 200).'.pdf');
        $this->assertSame(150, mb_strlen($long));
        $this->assertStringEndsWith('.pdf', $long);
    }
}
