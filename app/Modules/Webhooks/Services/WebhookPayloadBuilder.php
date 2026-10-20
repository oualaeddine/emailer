<?php

namespace App\Modules\Webhooks\Services;

use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Recipients\Models\Recipient;
use App\Modules\Recipients\Models\RecipientList;
use App\Modules\Recipients\Models\Segment;
use App\Modules\Tracking\Models\Message;
use App\Modules\Tracking\Models\MessageEvent;
use App\Modules\Webhooks\Models\ApiWebhook;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * docs/45 §45.7.7 — Brevo-shaped webhook payloads. Keys are Brevo's, verbatim;
 * absent values are omitted, never `null`.
 */
class WebhookPayloadBuilder
{
    public const TIMEZONE = 'Africa/Algiers';

    /**
     * Subscription identifier -> payload `event`, per webhook type.
     *
     * @var array<string, array<string, string>>
     */
    public const EVENT_MAP = [
        ApiWebhook::TYPE_TRANSACTIONAL => [
            'request' => 'request', 'delivered' => 'delivered', 'opened' => 'opened',
            'uniqueOpened' => 'unique_opened', 'click' => 'click', 'softBounce' => 'soft_bounce',
            'hardBounce' => 'hard_bounce', 'invalid' => 'invalid_email', 'deferred' => 'deferred',
            'spam' => 'spam', 'unsubscribed' => 'unsubscribed', 'blocked' => 'blocked',
            'error' => 'error', 'proxyOpen' => 'proxy_open',
        ],
        ApiWebhook::TYPE_MARKETING => [
            'delivered' => 'delivered', 'opened' => 'opened', 'click' => 'click',
            'softBounce' => 'soft_bounce', 'hardBounce' => 'hard_bounce', 'spam' => 'spam',
            'unsubscribed' => 'unsubscribe', 'listAddition' => 'list_addition',
            'contactUpdated' => 'contact_updated', 'contactDeleted' => 'contact_deleted',
        ],
    ];

    public static function payloadEvent(string $type, string $subscription): string
    {
        return self::EVENT_MAP[$type][$subscription] ?? $subscription;
    }

    /**
     * Payload of a message-level event.
     *
     * @return array<string, mixed>
     */
    public function forMessageEvent(ApiWebhook $webhook, MessageEvent $event, Message $message, string $payloadEvent): array
    {
        $message->loadMissing('recipient');
        $campaign = $message->campaign_id !== null ? Campaign::query()->find($message->campaign_id) : null;
        $at = $event->occurred_at ?? now();
        $reason = $this->reason($event, $message);
        $isEngagement = in_array($payloadEvent, ['opened', 'unique_opened', 'proxy_open', 'click'], true);

        if ($webhook->type === ApiWebhook::TYPE_MARKETING) {
            return $this->clean([
                'id' => $webhook->uuid,
                'camp_id' => $campaign?->uuid,
                'campaign name' => $campaign?->name,
                'email' => $message->recipient?->email,
                'event' => $payloadEvent,
                'tag' => $this->tagString($campaign),
                'date_sent' => $this->date($message->sent_at),
                'date_event' => $this->date($at),
                'ts_sent' => $message->sent_at?->getTimestamp(),
                'ts_event' => $at->getTimestamp(),
                'ts' => $at->getTimestamp(),
                'URL' => $payloadEvent === 'click' ? $event->url : null,
                'list_id' => $campaign !== null ? $this->listIds($campaign) : null,
                'segment_ids' => $campaign !== null ? $this->segmentIds($campaign) : null,
                'reason' => in_array($payloadEvent, ['soft_bounce', 'hard_bounce'], true) ? $reason : null,
                'sending_ip' => null,
                'contact_id' => $message->recipient?->uuid,
            ]);
        }

        $tags = $campaign?->tags;

        return $this->clean([
            'event' => $payloadEvent,
            'email' => $message->recipient?->email,
            'id' => $webhook->uuid,
            'date' => $this->date($at),
            'ts' => $at->getTimestamp(),
            'message-id' => $message->provider_message_id ?: '<'.$message->uuid.'@pagesjaunes-mailer>',
            'ts_event' => $at->getTimestamp(),
            'ts_epoch' => (int) ($at->getTimestamp() * 1000 + (int) $at->format('v')),
            'subject' => $message->subject,
            'tag' => $tags ? implode(',', $tags) : null,
            'tags' => $tags ?: null,
            'template_id' => $campaign?->template_id !== null ? $this->templateUuid((int) $campaign->template_id) : null,
            'contact_id' => $message->recipient?->uuid,
            'link' => $payloadEvent === 'click' ? $event->url : null,
            'reason' => in_array($payloadEvent, ['soft_bounce', 'hard_bounce', 'blocked', 'deferred', 'error', 'invalid_email'], true) ? $reason : null,
            'user_agent' => $isEngagement ? $event->user_agent : null,
            'device_used' => $isEngagement && $event->device_type ? strtoupper($event->device_type) : null,
        ]);
    }

    /**
     * `list_addition` payload.
     *
     * @return array<string, mixed>
     */
    public function forListAddition(ApiWebhook $webhook, RecipientList $list, Recipient $recipient): array
    {
        $now = now();

        return $this->clean([
            'event' => 'list_addition',
            'email' => $recipient->email,
            'id' => $webhook->uuid,
            'key' => $recipient->uuid,
            'list_id' => [$list->uuid],
            'date' => $this->date($now),
            'ts' => $now->getTimestamp(),
        ]);
    }

    /**
     * `contact_updated` / `contact_deleted` payload.
     *
     * @param  array<string, mixed>  $attributes  changed attributes (updated) / identifying attributes (deleted)
     * @return array<string, mixed>
     */
    public function forContact(ApiWebhook $webhook, string $payloadEvent, string $email, ?string $contactUuid, array $attributes): array
    {
        $now = now();

        return $this->clean([
            'event' => $payloadEvent,
            'email' => $email,
            'id' => $webhook->uuid,
            'key' => $contactUuid,
            'date' => $this->date($now),
            'ts' => $now->getTimestamp(),
            'attributes' => $attributes === [] ? new \stdClass : $attributes,
        ]);
    }

    /**
     * Marketing `unsubscribe` from the preference page.
     *
     * @param  list<string>  $listUuids
     * @return array<string, mixed>
     */
    public function forUnsubscribe(ApiWebhook $webhook, Recipient $recipient, array $listUuids): array
    {
        $now = now();

        return $this->clean([
            'id' => $webhook->uuid,
            'email' => $recipient->email,
            'event' => 'unsubscribe',
            'date_event' => $this->date($now),
            'ts_event' => $now->getTimestamp(),
            'ts' => $now->getTimestamp(),
            'list_id' => $listUuids === [] ? null : $listUuids,
            'contact_id' => $recipient->uuid,
        ]);
    }

    /**
     * Sample payload for `POST /webhooks/{id}/test`.
     *
     * @return array<string, mixed>
     */
    public function sample(ApiWebhook $webhook, string $subscription): array
    {
        $event = self::payloadEvent($webhook->type, $subscription);
        $now = now();
        $uuid = '00000000-0000-4000-8000-000000000000';

        if ($webhook->type === ApiWebhook::TYPE_MARKETING) {
            $base = [
                'id' => $webhook->uuid,
                'camp_id' => $uuid,
                'campaign name' => 'Newsletter (test)',
                'email' => 'amina@exemple.dz',
                'event' => $event,
                'tag' => 'newsletter',
                'date_sent' => $this->date($now->copy()->subMinutes(5)),
                'date_event' => $this->date($now),
                'ts_sent' => $now->copy()->subMinutes(5)->getTimestamp(),
                'ts_event' => $now->getTimestamp(),
                'ts' => $now->getTimestamp(),
                'list_id' => [$uuid],
                'contact_id' => $uuid,
            ];

            return match ($event) {
                'click' => $base + ['URL' => 'https://exemple.dz/offre'],
                'soft_bounce', 'hard_bounce' => $base + ['reason' => 'Boîte pleine (test)'],
                'list_addition' => $this->clean(['event' => $event, 'email' => 'amina@exemple.dz', 'id' => $webhook->uuid, 'key' => $uuid, 'list_id' => [$uuid], 'date' => $this->date($now), 'ts' => $now->getTimestamp()]),
                'contact_updated', 'contact_deleted' => $this->clean(['event' => $event, 'email' => 'amina@exemple.dz', 'id' => $webhook->uuid, 'key' => $uuid, 'date' => $this->date($now), 'ts' => $now->getTimestamp(), 'attributes' => ['NOM' => 'Amina']]),
                default => $base,
            };
        }

        $payload = [
            'event' => $event,
            'email' => 'amina@exemple.dz',
            'id' => $webhook->uuid,
            'date' => $this->date($now),
            'ts' => $now->getTimestamp(),
            'message-id' => '<'.$uuid.'@pagesjaunes-mailer>',
            'ts_event' => $now->getTimestamp(),
            'ts_epoch' => $now->getTimestamp() * 1000,
            'subject' => 'Votre commande 1042 (test)',
            'tag' => 'commande',
            'tags' => ['commande'],
            'contact_id' => $uuid,
        ];

        if ($event === 'click') {
            $payload['link'] = 'https://exemple.dz/suivi';
        }
        if (in_array($event, ['soft_bounce', 'hard_bounce', 'blocked', 'deferred', 'error', 'invalid_email'], true)) {
            $payload['reason'] = 'Message de test';
        }
        if (in_array($event, ['opened', 'unique_opened', 'proxy_open', 'click'], true)) {
            $payload['user_agent'] = 'Mozilla/5.0 (test)';
            $payload['device_used'] = 'DESKTOP';
        }

        return $payload;
    }

    private function reason(MessageEvent $event, Message $message): ?string
    {
        $metadata = is_array($event->metadata) ? $event->metadata : [];
        $reason = $metadata['reason'] ?? $message->last_provider_response;

        return is_string($reason) && $reason !== '' ? mb_substr($reason, 0, 500) : null;
    }

    private function tagString(?Campaign $campaign): ?string
    {
        return $campaign?->tags ? implode(',', $campaign->tags) : null;
    }

    private function templateUuid(int $templateId): ?string
    {
        return \App\Modules\Templates\Models\Template::query()->whereKey($templateId)->value('uuid');
    }

    /** @return list<string>|null */
    private function listIds(Campaign $campaign): ?array
    {
        if ($campaign->recipient_list_id === null) {
            return null;
        }
        $uuid = RecipientList::query()->whereKey($campaign->recipient_list_id)->value('uuid');

        return $uuid ? [$uuid] : null;
    }

    /** @return list<string>|null */
    private function segmentIds(Campaign $campaign): ?array
    {
        if ($campaign->segment_id === null) {
            return null;
        }
        $uuid = Segment::query()->whereKey($campaign->segment_id)->value('uuid');

        return $uuid ? [$uuid] : null;
    }

    private function date(?CarbonInterface $at): ?string
    {
        return $at === null ? null : Carbon::instance($at)->setTimezone(self::TIMEZONE)->format('Y-m-d H:i:s');
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function clean(array $payload): array
    {
        return array_filter($payload, fn ($v) => $v !== null && $v !== []);
    }
}
