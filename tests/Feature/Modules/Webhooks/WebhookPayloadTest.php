<?php

namespace Tests\Feature\Modules\Webhooks;

use App\Domain\Enums\MessageStatus;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Recipients\Attributes\Events\ContactAttributesUpdated;
use App\Modules\Recipients\Events\RecipientDeleted;
use App\Modules\Recipients\Lists\Events\RecipientsAddedToList;
use App\Modules\Recipients\Models\Recipient;
use App\Modules\Recipients\Models\RecipientList;
use App\Modules\Suppression\Listeners\RecipientBlocklistChanged;
use App\Modules\Suppression\Preferences\Events\ContactPreferencesUpdated;
use App\Modules\Tracking\Events\MessageEventRecorded;
use App\Modules\Tracking\Models\Message;
use App\Modules\Tracking\Models\MessageEvent;
use App\Modules\Tracking\Services\TrackingEventRecorder;
use App\Modules\Webhooks\Jobs\DeliverWebhookJob;
use App\Modules\Webhooks\Models\ApiWebhook;
use App\Modules\Webhooks\Models\ApiWebhookDelivery;
use Illuminate\Support\Facades\Queue;

/** Domain events -> Brevo-shaped payloads (docs/45 §45.7.7). */
class WebhookPayloadTest extends WebhookTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    private function recorder(): TrackingEventRecorder
    {
        return app(TrackingEventRecorder::class);
    }

    private function transactionalMessage(string $email = null): Message
    {
        return Message::factory()->create([
            'campaign_id' => null,
            'subject' => 'Votre commande 1042',
            'provider_message_id' => '<abc@pagesjaunes-mailer>',
            'status' => MessageStatus::Queued->value,
            'recipient_id' => Recipient::factory()->create($email ? ['email' => $email] : []),
        ]);
    }

    /** @return list<ApiWebhookDelivery> */
    private function deliveries(): array
    {
        return ApiWebhookDelivery::query()->orderBy('id')->get()->all();
    }

    public function test_transactional_delivered_payload_has_brevo_keys(): void
    {
        $message = $this->transactionalMessage('amina@exemple.dz');
        $webhook = ApiWebhook::factory()->events(['delivered'])->create();

        $message->markStatus(MessageStatus::Delivered);

        $deliveries = $this->deliveries();
        $this->assertCount(1, $deliveries);
        Queue::assertPushed(DeliverWebhookJob::class, 1);
        $payload = $deliveries[0]->payload;
        $this->assertSame('delivered', $deliveries[0]->event);
        $this->assertSame('delivered', $payload['event']);
        $this->assertSame('amina@exemple.dz', $payload['email']);
        $this->assertSame($webhook->uuid, $payload['id']);
        $this->assertSame('<abc@pagesjaunes-mailer>', $payload['message-id']);
        $this->assertSame('Votre commande 1042', $payload['subject']);
        $this->assertSame($message->recipient->uuid, $payload['contact_id']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $payload['date']);
        $this->assertIsInt($payload['ts']);
        $this->assertSame($payload['ts'], $payload['ts_event']);
        $this->assertGreaterThanOrEqual($payload['ts'] * 1000, $payload['ts_epoch']);
        foreach (['link', 'reason', 'user_agent', 'tag', 'tags', 'URL'] as $absent) {
            $this->assertArrayNotHasKey($absent, $payload);
        }
    }

    public function test_click_open_and_unique_open_payloads(): void
    {
        $message = $this->transactionalMessage();
        ApiWebhook::factory()->events(['click', 'opened', 'uniqueOpened'])->create();

        $this->recorder()->recordOpen($message);
        $this->recorder()->recordOpen($message);
        $this->recorder()->recordClick($message, 'https://exemple.dz/suivi');

        $events = array_map(fn ($d) => $d->event, $this->deliveries());
        $this->assertSame(['opened', 'unique_opened', 'opened', 'click'], $events);
        $click = $this->deliveries()[3]->payload;
        $this->assertSame('https://exemple.dz/suivi', $click['link']);
        $this->assertSame('click', $click['event']);
    }

    public function test_proxy_open_only_reaches_proxy_open_subscribers(): void
    {
        $message = $this->transactionalMessage();
        $event = MessageEvent::factory()->forMessage($message)->proxyOpen()->create();
        ApiWebhook::factory()->events(['opened', 'uniqueOpened', 'proxyOpen'])->create();

        MessageEventRecorded::dispatch($event->id, $message->id, 'opened', null, $message->recipient_id, null, null, true);

        $this->assertSame(['proxy_open'], array_map(fn ($d) => $d->event, $this->deliveries()));
        $this->assertSame('Mozilla/5.0', $this->deliveries()[0]->payload['user_agent']);
    }

    public function test_bounce_spam_and_error_payloads_with_reason(): void
    {
        $webhook = ApiWebhook::factory()->events(['softBounce', 'hardBounce', 'invalid', 'spam', 'error', 'blocked', 'deferred', 'request'])->create();
        $message = $this->transactionalMessage();

        $message->markStatus(MessageStatus::Accepted);
        $this->recorder()->recordEvent($message, MessageEvent::DEFERRED, ['reason' => 'Greylisted']);
        $this->recorder()->recordEvent($message, MessageEvent::BLOCKED, ['reason' => 'Blocked by policy']);
        $message->forceFill(['last_provider_response' => '550 5.1.1 User unknown'])->save();
        $message->markStatus(MessageStatus::HardBounced);
        $soft = $this->transactionalMessage();
        $soft->markStatus(MessageStatus::SoftBounced);
        $this->transactionalMessage()->markStatus(MessageStatus::SpamComplaint);
        $this->transactionalMessage()->markStatus(MessageStatus::Failed);

        $byEvent = collect($this->deliveries())->groupBy('event');
        $this->assertSame(
            ['request', 'deferred', 'blocked', 'hard_bounce', 'invalid_email', 'soft_bounce', 'spam', 'error'],
            collect($this->deliveries())->pluck('event')->all(),
        );
        $this->assertSame('Greylisted', $byEvent['deferred'][0]->payload['reason']);
        $this->assertSame('Blocked by policy', $byEvent['blocked'][0]->payload['reason']);
        $this->assertSame('550 5.1.1 User unknown', $byEvent['hard_bounce'][0]->payload['reason']);
        $this->assertSame('550 5.1.1 User unknown', $byEvent['invalid_email'][0]->payload['reason']);
        $this->assertArrayNotHasKey('reason', $byEvent['spam'][0]->payload);
        $this->assertSame($webhook->uuid, $byEvent['spam'][0]->payload['id']);
    }

    public function test_marketing_click_payload_uses_campaign_keys(): void
    {
        $list = \Database\Factories\RecipientListFactory::new()->create();
        $campaign = Campaign::factory()->create(['name' => 'Newsletter octobre', 'status' => 'running', 'recipient_list_id' => $list->id, 'tags' => ['newsletter']]);
        $message = Message::factory()->create(['campaign_id' => $campaign->id, 'status' => MessageStatus::Accepted->value, 'sent_at' => now()->subMinutes(5)]);
        $webhook = ApiWebhook::factory()->marketing(['click', 'opened', 'unsubscribed', 'hardBounce'])->create();

        $this->recorder()->recordClick($message, 'https://exemple.dz/offre');
        $message->markStatus(MessageStatus::HardBounced);
        $message->markStatus(MessageStatus::Unsubscribed);

        $deliveries = collect($this->deliveries());
        $this->assertSame(['click', 'hard_bounce', 'unsubscribe'], $deliveries->pluck('event')->all());
        $click = $deliveries[0]->payload;
        $this->assertSame($webhook->uuid, $click['id']);
        $this->assertSame($campaign->uuid, $click['camp_id']);
        $this->assertSame('Newsletter octobre', $click['campaign name']);
        $this->assertSame('click', $click['event']);
        $this->assertSame('https://exemple.dz/offre', $click['URL']);
        $this->assertSame('newsletter', $click['tag']);
        $this->assertSame([$list->uuid], $click['list_id']);
        $this->assertSame($message->recipient->email, $click['email']);
        $this->assertSame($message->recipient->uuid, $click['contact_id']);
        foreach (['date_sent', 'date_event', 'ts_sent', 'ts_event', 'ts'] as $key) {
            $this->assertArrayHasKey($key, $click);
        }
        $this->assertArrayNotHasKey('message-id', $click);
        $this->assertArrayNotHasKey('link', $click);
        $this->assertArrayNotHasKey('URL', $deliveries[2]->payload);
    }

    public function test_subscription_filter_and_type_separation(): void
    {
        $tx = $this->transactionalMessage();
        $campaignMessage = Message::factory()->create(['campaign_id' => Campaign::factory()->create(['status' => 'running'])->id, 'status' => MessageStatus::Accepted->value]);
        ApiWebhook::factory()->events(['hardBounce'])->create();                // transactional
        ApiWebhook::factory()->marketing(['delivered'])->create();               // marketing
        ApiWebhook::factory()->events(['delivered'])->inactive()->create();      // inactive

        $tx->markStatus(MessageStatus::Delivered);               // tx webhook is not subscribed to delivered
        $campaignMessage->markStatus(MessageStatus::Delivered);  // marketing webhook gets it

        $deliveries = $this->deliveries();
        $this->assertCount(1, $deliveries);
        $this->assertSame($campaignMessage->campaign->uuid ?? Campaign::query()->find($campaignMessage->campaign_id)->uuid, $deliveries[0]->payload['camp_id']);
    }

    public function test_list_addition_payload(): void
    {
        $list = \Database\Factories\RecipientListFactory::new()->create();
        $recipients = Recipient::factory()->count(2)->create();
        $webhook = ApiWebhook::factory()->marketing(['listAddition'])->create();
        ApiWebhook::factory()->marketing(['delivered'])->create();

        event(new RecipientsAddedToList($list->id, $recipients->pluck('id')->all(), 'import'));

        $deliveries = $this->deliveries();
        $this->assertCount(2, $deliveries);
        $payload = $deliveries[0]->payload;
        $this->assertSame('list_addition', $payload['event']);
        $this->assertSame('list_addition', $deliveries[0]->event);
        $this->assertSame($recipients[0]->email, $payload['email']);
        $this->assertSame($webhook->uuid, $payload['id']);
        $this->assertSame($recipients[0]->uuid, $payload['key']);
        $this->assertSame([$list->uuid], $payload['list_id']);
        $this->assertArrayHasKey('date', $payload);
        $this->assertArrayHasKey('ts', $payload);
    }

    public function test_contact_updated_and_deleted_payloads(): void
    {
        $recipient = Recipient::factory()->create();
        ApiWebhook::factory()->marketing(['contactUpdated', 'contactDeleted'])->create();

        event(new ContactAttributesUpdated($recipient->id, ['VILLE' => ['old' => 'Oran', 'new' => 'Alger']], 'api'));
        event(new RecipientBlocklistChanged($recipient->id, true, 'manual'));
        event(new RecipientDeleted($recipient->id, $recipient->email));

        $deliveries = $this->deliveries();
        $this->assertSame(['contact_updated', 'contact_updated', 'contact_deleted'], array_map(fn ($d) => $d->event, $deliveries));
        $this->assertSame(['VILLE' => 'Alger'], $deliveries[0]->payload['attributes']);
        $this->assertSame(['BLOCKLISTED' => true], $deliveries[1]->payload['attributes']);
        $this->assertSame($recipient->uuid, $deliveries[0]->payload['key']);
        $this->assertSame($recipient->email, $deliveries[2]->payload['email']);
        $this->assertSame(['EMAIL' => $recipient->email], $deliveries[2]->payload['attributes']);
    }

    public function test_preferences_update_maps_to_unsubscribe(): void
    {
        $recipient = Recipient::factory()->create();
        $list = \Database\Factories\RecipientListFactory::new()->create();
        ApiWebhook::factory()->marketing(['unsubscribed'])->create();

        event(new ContactPreferencesUpdated($recipient->id, [], [$list->id], false)); // only re-subscribed: nothing
        event(new ContactPreferencesUpdated($recipient->id, [$list->id], [], false));

        $deliveries = $this->deliveries();
        $this->assertCount(1, $deliveries);
        $this->assertSame('unsubscribe', $deliveries[0]->payload['event']);
        $this->assertSame([$list->uuid], $deliveries[0]->payload['list_id']);
        $this->assertSame($recipient->email, $deliveries[0]->payload['email']);
    }

    public function test_listener_failure_never_breaks_the_firing_code(): void
    {
        ApiWebhook::factory()->events(['delivered'])->create();
        $message = $this->transactionalMessage();
        $this->mock(\App\Modules\Webhooks\Services\WebhookService::class, fn ($m) => $m->shouldReceive('subscribers')->andThrow(new \RuntimeException('boom')));

        $message->markStatus(MessageStatus::Delivered);

        $this->assertSame('delivered', $message->fresh()->status);
        $this->assertCount(0, $this->deliveries());
    }
}
