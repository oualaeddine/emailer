<?php

namespace Tests\Feature\Modules\DeliveryEngine;

use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Campaigns\Services\CampaignService;
use App\Modules\DeliveryEngine\Jobs\SendEmailJob;
use App\Modules\DeliveryEngine\Models\SmtpAccount;
use App\Modules\DeliveryEngine\Services\SmtpManagerContract;
use App\Modules\Recipients\Models\Recipient;
use App\Modules\Senders\Models\Sender;
use App\Modules\Tracking\Models\Message;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Symfony\Component\Mime\Email;
use Tests\Support\FakeSmtpManager;
use Tests\TestCase;

/**
 * Visual-pass fixes: campaign e-mails carry the campaign sender, its reply-to
 * and RFC 8058 one-click unsubscribe headers; classic campaigns include their
 * preview text as the hidden preheader.
 */
class CampaignEnvelopeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
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

    private function send(Message $message): Email
    {
        Bus::fake();
        $this->app->call([new SendEmailJob($message->id), 'handle']);
        $this->assertCount(1, FakeSmtpManager::$sent);

        return FakeSmtpManager::$sent[0]['email'];
    }

    private function campaignMessage(array $campaign = [], array $message = []): Message
    {
        $campaign = Campaign::factory()->create($campaign);
        $recipient = Recipient::factory()->create(['email' => 'amina@exemple.dz']);

        return Message::factory()->create(array_merge(['recipient_id' => $recipient->id, 'campaign_id' => $campaign->id], $message));
    }

    public function test_verified_campaign_sender_is_the_from_and_the_account_is_the_envelope_sender(): void
    {
        $sender = Sender::factory()->create(['name' => 'PageJaunes Ventes', 'email' => 'ventes@pagejaunes.dz', 'verified_at' => now()]);
        $email = $this->send($this->campaignMessage(['sender_id' => $sender->id, 'reply_to' => 'service@pagejaunes.dz']));

        $this->assertSame('ventes@pagejaunes.dz', $email->getFrom()[0]->getAddress());
        $this->assertSame('PageJaunes Ventes', $email->getFrom()[0]->getName());
        $this->assertSame('relay@smtp-provider.dz', $email->getSender()?->getAddress());
        $this->assertSame('service@pagejaunes.dz', $email->getReplyTo()[0]->getAddress());
    }

    public function test_sender_reply_to_is_used_when_the_campaign_has_none(): void
    {
        $sender = Sender::factory()->create(['email' => 'ventes@pagejaunes.dz', 'reply_to' => 'contact@pagejaunes.dz', 'verified_at' => now()]);
        $email = $this->send($this->campaignMessage(['sender_id' => $sender->id, 'reply_to' => null]));

        $this->assertSame('contact@pagejaunes.dz', $email->getReplyTo()[0]->getAddress());
    }

    public function test_unverified_sender_falls_back_to_the_smtp_account(): void
    {
        $sender = Sender::factory()->create(['email' => 'pas-verifie@pagejaunes.dz', 'verified_at' => null]);
        $email = $this->send($this->campaignMessage(['sender_id' => $sender->id]));

        $this->assertSame('relay@smtp-provider.dz', $email->getFrom()[0]->getAddress());
        $this->assertNull($email->getSender());
    }

    public function test_campaign_messages_carry_one_click_unsubscribe_headers(): void
    {
        $email = $this->send($this->campaignMessage());

        $header = $email->getHeaders()->get('List-Unsubscribe')?->getBodyAsString();
        $this->assertNotNull($header);
        $this->assertStringContainsString('/unsubscribe/', $header);
        $this->assertSame('List-Unsubscribe=One-Click', $email->getHeaders()->get('List-Unsubscribe-Post')?->getBodyAsString());
    }

    public function test_test_sends_and_non_campaign_messages_have_no_unsubscribe_headers(): void
    {
        $email = $this->send($this->campaignMessage([], ['is_test' => true]));
        $this->assertNull($email->getHeaders()->get('List-Unsubscribe'));

        FakeSmtpManager::reset();
        $recipient = Recipient::factory()->create(['email' => 'karim@exemple.dz']);
        $email = $this->send(Message::factory()->create(['recipient_id' => $recipient->id, 'campaign_id' => null]));
        $this->assertNull($email->getHeaders()->get('List-Unsubscribe'));
        $this->assertSame('relay@smtp-provider.dz', $email->getFrom()[0]->getAddress());
    }

    public function test_test_send_includes_the_preview_text_as_preheader(): void
    {
        Bus::fake();
        $campaign = Campaign::factory()->create([
            'html_body' => '<html><body><p>Bonjour</p></body></html>',
            'preview_text' => 'Jusqu’à −20 % cette semaine',
        ]);

        app(CampaignService::class)->testSend($campaign, ['test@exemple.dz'], null, null);

        $html = (string) Message::query()->where('campaign_id', $campaign->id)->value('html_body');
        $this->assertMatchesRegularExpression('/<body[^>]*><div style="display:none[^"]*">Jusqu’à −20 % cette semaine<\/div>/u', $html);
    }
}
