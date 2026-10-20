<?php

namespace App\Modules\Webhooks\Listeners;

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
use App\Modules\Webhooks\Models\ApiWebhook;
use App\Modules\Webhooks\Services\WebhookPayloadBuilder;
use App\Modules\Webhooks\Services\WebhookService;
use Throwable;

/**
 * docs/45 §45.9 — turns domain events into webhook deliveries. A failure here
 * is reported but never propagates to the code that fired the domain event.
 *
 * message_events -> Brevo events:
 *   transactional (message without campaign): sent=request, delivered, opened/unique_opened/proxy_open,
 *   clicked=click, soft_bounced=soft_bounce, hard_bounced=hard_bounce (+ invalid_email when the reason says
 *   the address is invalid), spam_complaint=spam, unsubscribed, deferred, blocked, failed=error.
 *   marketing (campaign message): delivered, opened (proxy opens included), click, soft_bounce,
 *   hard_bounce, spam, unsubscribed=unsubscribe.
 */
class WebhookEventSubscriber
{
    /** A single huge import must not create an unbounded number of deliveries per webhook. */
    public const MAX_LIST_ADDITIONS_PER_EVENT = 500;

    private const INVALID_REASON = '/invalid|unknown (user|recipient)|no such (user|mailbox)|does not exist|user unknown|mailbox not found/i';

    public function __construct(
        private readonly WebhookService $webhooks,
        private readonly WebhookPayloadBuilder $payloads,
    ) {}

    public function onMessageEvent(MessageEventRecorded $event): void
    {
        $this->safely(function () use ($event): void {
            if (! ApiWebhook::query()->where('is_active', true)->exists()) {
                return;
            }
            $messageEvent = MessageEvent::query()->find($event->messageEventId);
            $message = Message::query()->find($event->messageId);
            if ($messageEvent === null || $message === null) {
                return;
            }

            $type = $message->campaign_id === null ? ApiWebhook::TYPE_TRANSACTIONAL : ApiWebhook::TYPE_MARKETING;
            foreach ($this->targets($type, $messageEvent, $message) as [$subscription, $payloadEvent]) {
                foreach ($this->webhooks->subscribers($type, $subscription) as $webhook) {
                    $this->webhooks->enqueue($webhook, $payloadEvent, $this->payloads->forMessageEvent($webhook, $messageEvent, $message, $payloadEvent));
                }
            }
        });
    }

    public function onListAddition(RecipientsAddedToList $event): void
    {
        $this->safely(function () use ($event): void {
            $webhooks = $this->webhooks->subscribers(ApiWebhook::TYPE_MARKETING, 'listAddition');
            $list = RecipientList::query()->find($event->listId);
            if ($webhooks->isEmpty() || $list === null) {
                return;
            }
            $recipients = Recipient::query()->whereIn('id', array_slice($event->recipientIds, 0, self::MAX_LIST_ADDITIONS_PER_EVENT))->get();
            foreach ($recipients as $recipient) {
                foreach ($webhooks as $webhook) {
                    $this->webhooks->enqueue($webhook, 'list_addition', $this->payloads->forListAddition($webhook, $list, $recipient));
                }
            }
        });
    }

    public function onAttributesUpdated(ContactAttributesUpdated $event): void
    {
        $this->safely(function () use ($event): void {
            $recipient = Recipient::query()->find($event->recipientId);
            if ($recipient === null) {
                return;
            }
            $changed = array_map(fn (array $change) => $change['new'] ?? null, $event->changes);
            $this->contactUpdated($recipient, $changed);
        });
    }

    public function onBlocklistChanged(RecipientBlocklistChanged $event): void
    {
        $this->safely(function () use ($event): void {
            $recipient = Recipient::query()->find($event->recipientId);
            if ($recipient === null) {
                return;
            }
            $this->contactUpdated($recipient, ['BLOCKLISTED' => $event->blocklisted]);
        });
    }

    public function onRecipientDeleted(RecipientDeleted $event): void
    {
        $this->safely(function () use ($event): void {
            $uuid = Recipient::withTrashed()->whereKey($event->recipientId)->value('uuid');
            foreach ($this->webhooks->subscribers(ApiWebhook::TYPE_MARKETING, 'contactDeleted') as $webhook) {
                $this->webhooks->enqueue($webhook, 'contact_deleted', $this->payloads->forContact($webhook, 'contact_deleted', $event->email, $uuid, ['EMAIL' => $event->email]));
            }
        });
    }

    public function onPreferencesUpdated(ContactPreferencesUpdated $event): void
    {
        $this->safely(function () use ($event): void {
            if ($event->unsubscribedListIds === [] && ! $event->unsubscribedAll) {
                return;
            }
            $webhooks = $this->webhooks->subscribers(ApiWebhook::TYPE_MARKETING, 'unsubscribed');
            $recipient = Recipient::query()->find($event->recipientId);
            if ($webhooks->isEmpty() || $recipient === null) {
                return;
            }
            $listUuids = RecipientList::query()->whereIn('id', $event->unsubscribedListIds)->pluck('uuid')->all();
            foreach ($webhooks as $webhook) {
                $this->webhooks->enqueue($webhook, 'unsubscribe', $this->payloads->forUnsubscribe($webhook, $recipient, $listUuids));
            }
        });
    }

    /** @param  array<string, mixed>  $attributes */
    private function contactUpdated(Recipient $recipient, array $attributes): void
    {
        foreach ($this->webhooks->subscribers(ApiWebhook::TYPE_MARKETING, 'contactUpdated') as $webhook) {
            $this->webhooks->enqueue($webhook, 'contact_updated', $this->payloads->forContact($webhook, 'contact_updated', $recipient->email, $recipient->uuid, $attributes));
        }
    }

    /**
     * @return list<array{0: string, 1: string}> [subscription id, payload event]
     */
    private function targets(string $type, MessageEvent $event, Message $message): array
    {
        $transactional = $type === ApiWebhook::TYPE_TRANSACTIONAL;

        switch ($event->event_type) {
            case MessageEvent::SENT:
                return $transactional ? [['request', 'request']] : [];
            case MessageEvent::DELIVERED:
                return [['delivered', 'delivered']];
            case MessageEvent::OPENED:
                if (! $transactional) {
                    return [['opened', 'opened']];
                }
                if ($event->is_proxy_open) {
                    return [['proxyOpen', 'proxy_open']];
                }
                $targets = [['opened', 'opened']];
                $firstOpen = ! MessageEvent::query()
                    ->where('message_id', $message->id)->where('event_type', MessageEvent::OPENED)
                    ->where('is_proxy_open', false)->where('id', '<', $event->id)->exists();
                if ($firstOpen) {
                    $targets[] = ['uniqueOpened', 'unique_opened'];
                }

                return $targets;
            case MessageEvent::CLICKED:
                return [['click', 'click']];
            case MessageEvent::SOFT_BOUNCED:
                return [['softBounce', 'soft_bounce']];
            case MessageEvent::HARD_BOUNCED:
                $targets = [['hardBounce', 'hard_bounce']];
                $reason = (string) (($event->metadata['reason'] ?? null) ?: $message->last_provider_response);
                if ($transactional && preg_match(self::INVALID_REASON, $reason) === 1) {
                    $targets[] = ['invalid', 'invalid_email'];
                }

                return $targets;
            case MessageEvent::COMPLAINT:
                return [['spam', 'spam']];
            case MessageEvent::UNSUBSCRIBED:
                return $transactional ? [['unsubscribed', 'unsubscribed']] : [['unsubscribed', 'unsubscribe']];
            case MessageEvent::DEFERRED:
                return $transactional ? [['deferred', 'deferred']] : [];
            case MessageEvent::BLOCKED:
                return $transactional ? [['blocked', 'blocked']] : [];
            case MessageEvent::FAILED:
                return $transactional ? [['error', 'error']] : [];
        }

        return [];
    }

    private function safely(callable $callback): void
    {
        try {
            $callback();
        } catch (Throwable $e) {
            report($e);
        }
    }
}
