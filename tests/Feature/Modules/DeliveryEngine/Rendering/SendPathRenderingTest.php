<?php

namespace Tests\Feature\Modules\DeliveryEngine\Rendering;

use App\Domain\Enums\MessageStatus;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Campaigns\Services\CampaignService;
use App\Modules\Composer\Models\Draft;
use App\Modules\Composer\Models\Signature;
use App\Modules\DeliveryEngine\Jobs\SendEmailJob;
use App\Modules\DeliveryEngine\Models\SmtpAccount;
use App\Modules\DeliveryEngine\Rendering\RenderingPipeline;
use App\Modules\DeliveryEngine\Services\ComposerSendService;
use App\Modules\DeliveryEngine\Services\SmtpManagerContract;
use App\Modules\Identity\Models\User;
use App\Modules\Recipients\Models\Recipient;
use App\Modules\Suppression\Support\UnsubscribeUrlGenerator;
use App\Modules\Tracking\Models\Message;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

/**
 * WP-63 call sites: Message creation (campaign dispatch, campaign test send,
 * composer send) stores the personalised HTML; SendEmailJob sends the
 * delivery-phase rendering (tracking, CSS, text part, To name, cid images).
 */
class SendPathRenderingTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<Email> */
    public static array $sent = [];

    protected function setUp(): void
    {
        parent::setUp();
        self::$sent = [];
        $this->app->bind(SmtpManagerContract::class, fn () => new class implements SmtpManagerContract
        {
            public function send(SmtpAccount $account, Email $email): void
            {
                SendPathRenderingTest::$sent[] = $email;
            }
        });

        SmtpAccount::query()->create([
            'name' => 'Compte Test', 'provider' => 'custom', 'host' => 'smtp.example.com', 'port' => 587,
            'encryption' => 'tls', 'username' => 'u', 'password_encrypted' => 'secret', 'from_email' => 'no-reply@example.com',
        ]);
    }

    public function test_send_email_job_sends_the_rendered_multipart_message(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('img/logo.png', base64_decode(RenderingGoldenTest::PNG));

        $campaign = Campaign::factory()->create([
            'subject' => 'Bonjour {{ contact.PRENOM }}',
            'html_body' => '<html><body><p>Salut {{ contact.PRENOM }}</p><a href="https://shop.example.com/">Boutique</a>'
                .'<img src="'.url('storage/img/logo.png').'" alt="logo"><a href="{{ unsubscribe }}">Désinscription</a></body></html>',
            'to_field' => '{{ contact.PRENOM }} {{ contact.NOM }}',
            'inline_images' => true,
        ]);
        $recipient = Recipient::factory()->create(['email' => 'sara@example.com', 'first_name' => 'Sara', 'last_name' => 'Kaci', 'custom_fields' => []]);

        $message = app(RenderingPipeline::class)->createMessage([
            'campaign_id' => $campaign->id, 'recipient_id' => $recipient->id,
            'status' => MessageStatus::Queued->value, 'queued_at' => now(),
        ], (string) $campaign->subject, (string) $campaign->html_body, $recipient, $campaign);

        // Stored = personalised web version: merged, link ids, no tracking.
        $this->assertSame('Bonjour Sara', $message->subject);
        $this->assertStringContainsString('data-link-id="'.substr(sha1('https://shop.example.com/'), 0, 16).'"', $message->html_body);
        $this->assertStringNotContainsString('/t/', $message->html_body);

        $this->app->call([new SendEmailJob($message->id), 'handle']);

        $this->assertCount(1, self::$sent);
        $email = self::$sent[0];
        $this->assertSame('Bonjour Sara', $email->getSubject());
        $this->assertSame('sara@example.com', $email->getTo()[0]->getAddress());
        $this->assertSame('Sara Kaci', $email->getTo()[0]->getName());
        $this->assertStringContainsString('/t/o/'.$message->uuid.'.gif', (string) $email->getHtmlBody());
        $this->assertStringContainsString('/t/c/'.$message->uuid, (string) $email->getHtmlBody());
        $this->assertStringContainsString('src="cid:', (string) $email->getHtmlBody());
        $this->assertCount(1, $email->getAttachments());
        $this->assertStringContainsString('Salut Sara', (string) $email->getTextBody());
        $this->assertStringContainsString(url('unsubscribe/'.UnsubscribeUrlGenerator::encode('sara@example.com')), (string) $email->getTextBody());
        $this->assertSame(MessageStatus::Accepted->value, $message->fresh()->status);

        // Retry path re-renders the same stored HTML: one pixel, nothing double-wrapped.
        $again = app(RenderingPipeline::class)->render($message->fresh());
        $this->assertSame(1, substr_count($again->html, '/t/o/'));
        $this->assertSame(1, substr_count($again->html, '/t/c/'));
    }

    public function test_campaign_test_send_renders_real_contact_data_and_keeps_test_prefix(): void
    {
        Bus::fake();
        $campaign = Campaign::factory()->create([
            'subject' => '[TEST] Offre pour {{ contact.PRENOM }}',
            'html_body' => '<p>{{ contact.PRENOM }}</p><a href="{{ unsubscribe }}">u</a><a href="{{ mirror }}">m</a>',
        ]);
        $contact = Recipient::factory()->create(['first_name' => 'Amina', 'custom_fields' => []]);

        app(CampaignService::class)->testSend($campaign, ['qa@example.com'], $contact, null);

        $message = Message::query()->where('is_test', true)->firstOrFail();
        $this->assertSame('[TEST] Offre pour Amina', $message->subject);
        $this->assertStringContainsString('<p>Amina</p>', $message->html_body);
        // Unsubscribe link targets the test address, never the contact.
        $this->assertStringContainsString(url('unsubscribe/'.UnsubscribeUrlGenerator::encode('qa@example.com')), $message->html_body);
        $this->assertStringContainsString(url('m/'.$message->uuid), $message->html_body);

        Bus::assertDispatched(SendEmailJob::class);
        $this->app->call([new SendEmailJob($message->id), 'handle']);
        $this->assertSame('[TEST] Offre pour Amina', self::$sent[0]->getSubject());
    }

    public function test_test_send_to_name_uses_the_merge_contact_and_is_stored(): void
    {
        Bus::fake();
        $campaign = Campaign::factory()->create([
            'subject' => 'Offre', 'html_body' => '<p>{{ contact.PRENOM }}</p>', 'to_field' => '{{ contact.PRENOM }} {{ contact.NOM }}',
        ]);
        $contact = Recipient::factory()->create(['first_name' => 'Amina', 'last_name' => 'Benali', 'custom_fields' => []]);

        app(CampaignService::class)->testSend($campaign, ['qa@example.com'], $contact, null);

        $message = Message::query()->where('is_test', true)->firstOrFail();
        $this->assertSame('Amina Benali', $message->to_name);

        $this->app->call([new SendEmailJob($message->id), 'handle']);
        $this->assertSame('qa@example.com', self::$sent[0]->getTo()[0]->getAddress());
        $this->assertSame('Amina Benali', self::$sent[0]->getTo()[0]->getName());
    }

    public function test_campaign_dispatch_stores_personalised_html(): void
    {
        Bus::fake();
        $campaign = Campaign::factory()->create([
            'subject' => 'Hi {{ contact.PRENOM }}',
            'html_body' => '<p>{{ contact.PRENOM }}</p><a href="https://x.example.com/">x</a><a href="{{ unsubscribe }}">u</a>',
            'utm_enabled' => true, 'utm_source' => 'nl',
        ]);
        $recipient = Recipient::factory()->create(['first_name' => 'Omar', 'custom_fields' => []]);

        $method = new \ReflectionMethod(CampaignService::class, 'dispatchToRecipient');
        $message = $method->invoke(app(CampaignService::class), $campaign, $recipient);

        $this->assertSame('Hi Omar', $message->subject);
        $this->assertStringContainsString('href="https://x.example.com/?utm_source=nl', $message->html_body);
        $this->assertStringContainsString(url('unsubscribe/'.UnsubscribeUrlGenerator::encode($recipient->email)), $message->html_body);
        Bus::assertDispatched(SendEmailJob::class);
    }

    public function test_composer_send_appends_the_draft_signature(): void
    {
        Bus::fake();
        $user = User::factory()->create();
        $signature = Signature::query()->create(['user_id' => $user->id, 'name' => 'S', 'html_content' => '<p>Cordialement, Karim</p>', 'is_default' => true]);
        $draft = Draft::query()->create(['user_id' => $user->id, 'subject' => 'Re: {{ contact.PRENOM }}', 'html_body' => '<p>Bonjour</p>', 'signature_id' => $signature->id]);
        $recipient = Recipient::factory()->create(['first_name' => 'Lyes', 'custom_fields' => []]);

        $message = app(ComposerSendService::class)->send($draft, $recipient);

        $this->assertSame('Re: Lyes', $message->subject);
        $this->assertSame('<p>Bonjour</p><div class="email-signature" data-signature="1"><p>Cordialement, Karim</p></div>', $message->html_body);
        $this->assertSame($draft->id, $message->draft_id);
    }
}
