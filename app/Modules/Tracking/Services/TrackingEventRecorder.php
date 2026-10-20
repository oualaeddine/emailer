<?php

namespace App\Modules\Tracking\Services;

use App\Domain\Enums\MessageStatus;
use App\Modules\Tracking\Events\MessageEventRecorded;
use App\Modules\Tracking\Models\CampaignLink;
use App\Modules\Tracking\Models\Message;
use App\Modules\Tracking\Models\MessageEvent;
use App\Modules\Tracking\Support\BotUserAgentDetector;
use App\Modules\Tracking\Support\EngagementScore;
use App\Modules\Tracking\Support\TrackingContext;
use App\Modules\Tracking\Support\TrackingEnricher;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * docs/21-open-click-tracking.md §21.1-21.4, docs/43 §43.6.3/§43.6.5 and
 * doc 45 §45.6.4/§45.9 (WP-70) — single writer of `message_events`.
 *
 * Every counted open / click, and every delivery transition recorded through
 * `Message::markStatus()` (sent, delivered, bounces, complaints,
 * unsubscriptions, failures), becomes one `message_events` row enriched with
 * the parsed user agent, GeoIP country / city and the Apple MPP proxy-open
 * flag, followed by a `MessageEventRecorded` event (after commit).
 *
 * Side effects kept from WP-21: every open increments `open_count` while
 * `opened_at` is stamped only once (same for clicks), and status only ever
 * advances along the positive funnel (queued → sending → accepted →
 * delivered → opened → clicked) — never over a terminal / bounce status.
 *
 * Added by WP-70:
 * - clicks upsert `campaign_links` by `link_id` (the WP-63 `data-link-id`,
 *   first 16 hex of sha1(url) when the link carries none); `unique_clicks`
 *   counts distinct recipients per link;
 * - non-test messages update the recipient's real-time counters
 *   (`opened`, `clicked`, `messages_sent`, `delivered`, bounces, complaints,
 *   unsubscriptions), `last_opened_at` / `last_clicked_at` and
 *   `engagement_score`, compatible with the nightly
 *   `RecomputeRecipientCountersJob` reconciliation.
 */
class TrackingEventRecorder
{
    /**
     * @var array<string, int>
     */
    private const FUNNEL_RANK = [
        'queued' => 0,
        'sending' => 1,
        'accepted' => 2,
        'delivered' => 3,
        'opened' => 4,
        'clicked' => 5,
    ];

    /** MessageStatus value => message_events.event_type. */
    private const STATUS_EVENTS = [
        'accepted' => MessageEvent::SENT,
        'delivered' => MessageEvent::DELIVERED,
        'opened' => MessageEvent::OPENED,
        'clicked' => MessageEvent::CLICKED,
        'soft_bounced' => MessageEvent::SOFT_BOUNCED,
        'hard_bounced' => MessageEvent::HARD_BOUNCED,
        'spam_complaint' => MessageEvent::COMPLAINT,
        'unsubscribed' => MessageEvent::UNSUBSCRIBED,
        'failed' => MessageEvent::FAILED,
        'rejected' => MessageEvent::BLOCKED,
    ];

    /** MessageStatus value => recipients counter incremented on the transition. */
    private const STATUS_COUNTERS = [
        'soft_bounced' => 'soft_bounces',
        'hard_bounced' => 'hard_bounces',
        'spam_complaint' => 'complaints',
        'unsubscribed' => 'unsubscriptions',
    ];

    private static int $suppressModelSync = 0;

    public function __construct(
        private readonly TrackingEnricher $enricher,
        private readonly BotUserAgentDetector $bots,
    ) {}

    public function recordOpen(Message $message, ?TrackingContext $context = null): MessageEvent
    {
        $enriched = $this->enricher->enrich($context, detectProxy: true);
        $firstOpen = $message->opened_at === null;

        $message->open_count = $message->open_count + 1;
        if ($firstOpen) {
            $message->opened_at = now();
        }
        $this->advanceStatusIfEarlier($message, MessageStatus::Opened);
        self::withoutModelSync(fn () => $message->save());

        $event = $this->write($message, MessageEvent::OPENED, $enriched);

        if ($this->countsForRecipient($message)) {
            $this->touchRecipient((int) $message->recipient_id, array_filter([
                'last_opened_at' => now(),
                'opened' => $firstOpen ? DB::raw('opened + 1') : null,
            ]));
        }

        return $event;
    }

    public function recordClick(
        Message $message,
        ?string $url = null,
        ?TrackingContext $context = null,
        ?string $linkId = null,
    ): MessageEvent {
        $url = $url !== null && trim($url) !== '' ? trim($url) : null;
        $linkId = $linkId !== null && preg_match('/^[A-Za-z0-9_-]{1,16}$/', $linkId) === 1
            ? $linkId
            : ($url !== null ? CampaignLink::linkIdFor($url) : null);

        $enriched = $this->enricher->enrich($context);
        $firstClick = $message->clicked_at === null;

        $message->click_count = $message->click_count + 1;
        if ($firstClick) {
            $message->clicked_at = now();
        }
        $this->advanceStatusIfEarlier($message, MessageStatus::Clicked);
        self::withoutModelSync(fn () => $message->save());

        $trackLink = $message->campaign_id !== null && $url !== null && $linkId !== null && ! $this->isTest($message);
        $uniqueForLink = $trackLink && ! MessageEvent::query()
            ->where('recipient_id', $message->recipient_id)
            ->where('campaign_id', $message->campaign_id)
            ->where('event_type', MessageEvent::CLICKED)
            ->where('link_id', $linkId)
            ->exists();

        $event = $this->write($message, MessageEvent::CLICKED, $enriched + [
            'url' => $url,
            'link_id' => $linkId,
        ], ['is_likely_bot' => $this->bots->isBot($context?->userAgent) ?: null]);

        if ($trackLink) {
            $this->upsertLink((int) $message->campaign_id, $linkId, $url, $uniqueForLink);
        }

        if ($this->countsForRecipient($message)) {
            $this->touchRecipient((int) $message->recipient_id, array_filter([
                'last_clicked_at' => now(),
                'clicked' => $firstClick ? DB::raw('clicked + 1') : null,
            ]));
        }

        return $event;
    }

    /**
     * Called by `Message::markStatus()` after a status transition was saved:
     * records the matching delivery event (`sent`, `delivered`, bounces,
     * `spam_complaint`, `unsubscribed`, `failed`, `blocked`) and bumps the
     * recipient counter the transition reached for the first time.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function recordStatusChange(Message $message, MessageStatus $status, array $metadata = []): ?MessageEvent
    {
        $eventType = self::STATUS_EVENTS[$status->value] ?? null;
        if ($eventType === null) {
            return null;
        }

        $event = $this->write($message, $eventType, [], $metadata);

        if ($this->countsForRecipient($message)) {
            $updates = match ($status) {
                MessageStatus::Accepted => $message->wasChanged('sent_at') ? ['messages_sent' => DB::raw('messages_sent + 1')] : [],
                MessageStatus::Delivered => $message->wasChanged('delivered_at') ? ['delivered' => DB::raw('delivered + 1')] : [],
                MessageStatus::Opened => array_filter([
                    'last_opened_at' => now(),
                    'opened' => $message->wasChanged('opened_at') ? DB::raw('opened + 1') : null,
                ]),
                MessageStatus::Clicked => array_filter([
                    'last_clicked_at' => now(),
                    'clicked' => $message->wasChanged('clicked_at') ? DB::raw('clicked + 1') : null,
                ]),
                default => isset(self::STATUS_COUNTERS[$status->value])
                    ? [self::STATUS_COUNTERS[$status->value] => DB::raw(self::STATUS_COUNTERS[$status->value].' + 1')]
                    : [],
            };

            if ($updates !== []) {
                $this->touchRecipient((int) $message->recipient_id, $updates);
            }
        }

        return $event;
    }

    /**
     * Records an event that has no status transition of its own (e.g. a
     * provider `deferred` notification).
     *
     * @param  array<string, mixed>  $metadata
     */
    public function recordEvent(Message $message, string $eventType, array $metadata = [], ?Carbon $occurredAt = null): MessageEvent
    {
        return $this->write($message, mb_substr($eventType, 0, 32), [], $metadata, $occurredAt);
    }

    /**
     * Fallback for writers that stamp `messages` directly through Eloquent
     * instead of going through this recorder / `markStatus()` (fixtures,
     * future provider webhooks): each first-reached timestamp or terminal
     * status saved on the model gets its `message_events` row, dated with
     * that timestamp, so readers that switch to `message_events` once it
     * exists (WP-52 timeline, WP-54 engagement rules, the rollup) never lose
     * history. Called from the Message model's created / updated hooks.
     */
    public function syncFromModel(Message $message, bool $created): void
    {
        if (self::$suppressModelSync > 0) {
            return;
        }

        $reached = function (string $column) use ($message, $created): bool {
            if ($message->getAttribute($column) === null) {
                return false;
            }

            return $created || ($message->wasChanged($column) && ($message->getPrevious()[$column] ?? null) === null);
        };
        $statusReached = fn (string $status): bool => $message->status === $status && ($created || $message->wasChanged('status'));

        $events = [];
        foreach (['sent_at' => MessageEvent::SENT, 'delivered_at' => MessageEvent::DELIVERED, 'opened_at' => MessageEvent::OPENED, 'clicked_at' => MessageEvent::CLICKED] as $column => $type) {
            if ($reached($column)) {
                $events[$type] = $message->getAttribute($column);
            }
        }
        foreach (['hard_bounced' => MessageEvent::HARD_BOUNCED, 'soft_bounced' => MessageEvent::SOFT_BOUNCED] as $status => $type) {
            if ($message->status === $status && $message->bounced_at !== null && ($reached('bounced_at') || $statusReached($status))) {
                $events[$type] = $message->bounced_at;
            }
        }
        if ($message->failed_at !== null && ($reached('failed_at') || $statusReached('failed') || $statusReached('rejected'))) {
            $events[$message->status === 'rejected' ? MessageEvent::BLOCKED : MessageEvent::FAILED] = $message->failed_at;
        }
        foreach (['spam_complaint' => MessageEvent::COMPLAINT, 'unsubscribed' => MessageEvent::UNSUBSCRIBED] as $status => $type) {
            if ($statusReached($status)) {
                $events[$type] = $message->updated_at ?? now();
            }
        }

        foreach ($events as $type => $at) {
            $this->write($message, $type, [], ['source' => 'message'], Carbon::instance($at));
        }
    }

    /**
     * Runs `$callback` without the Message model hooks mirroring timestamps
     * into `message_events` (the recorder writes the enriched row itself).
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public static function withoutModelSync(callable $callback): mixed
    {
        self::$suppressModelSync++;

        try {
            return $callback();
        } finally {
            self::$suppressModelSync--;
        }
    }

    /**
     * @param  array<string, mixed>  $columns  enrichment columns (+ url / link_id)
     * @param  array<string, mixed>  $extraMetadata
     */
    private function write(
        Message $message,
        string $eventType,
        array $columns,
        array $extraMetadata = [],
        ?Carbon $occurredAt = null,
    ): MessageEvent {
        $metadata = array_filter([
            'url' => $columns['url'] ?? null,
            'device_type' => $columns['device_type'] ?? null,
            'email_client' => $columns['email_client'] ?? null,
            'os' => $columns['os'] ?? null,
            'ip_country' => $columns['ip_country'] ?? null,
            ...$extraMetadata,
        ], fn ($value): bool => $value !== null);

        $event = MessageEvent::query()->create([
            'uuid' => (string) Str::uuid(),
            'message_id' => $message->id,
            'campaign_id' => $message->campaign_id,
            'recipient_id' => $message->recipient_id,
            'event_type' => $eventType,
            'occurred_at' => $occurredAt ?? now(),
            'ip' => $columns['ip'] ?? null,
            'metadata' => $metadata === [] ? null : $metadata,
            'url' => $columns['url'] ?? null,
            'link_id' => $columns['link_id'] ?? null,
            'user_agent' => $columns['user_agent'] ?? null,
            'device_type' => $columns['device_type'] ?? null,
            'email_client' => $columns['email_client'] ?? null,
            'ip_country' => $columns['ip_country'] ?? null,
            'ip_city' => $columns['ip_city'] ?? null,
            'is_proxy_open' => (bool) ($columns['is_proxy_open'] ?? false),
        ]);

        MessageEventRecorded::dispatch(
            $event->id,
            $message->id,
            $eventType,
            $message->campaign_id,
            $message->recipient_id,
            $event->url,
            $event->link_id,
            $event->is_proxy_open,
        );

        return $event;
    }

    private function upsertLink(int $campaignId, string $linkId, string $url, bool $unique): void
    {
        $now = now();

        DB::table('campaign_links')->insertOrIgnore([
            'uuid' => (string) Str::uuid(),
            'campaign_id' => $campaignId,
            'link_id' => $linkId,
            'url' => $url,
            'url_hash' => sha1($url),
            'unique_clicks' => 0,
            'total_clicks' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('campaign_links')
            ->where('campaign_id', $campaignId)
            ->where('link_id', $linkId)
            ->update([
                'total_clicks' => DB::raw('total_clicks + 1'),
                'unique_clicks' => DB::raw('unique_clicks + '.($unique ? 1 : 0)),
                'last_clicked_at' => $now,
                'updated_at' => $now,
            ]);
    }

    /**
     * @param  array<string, mixed>  $updates
     */
    private function touchRecipient(int $recipientId, array $updates): void
    {
        DB::table('recipients')->where('id', $recipientId)->update($updates);

        $row = DB::table('recipients')->where('id', $recipientId)
            ->first(['messages_sent', 'delivered', 'opened', 'clicked', 'last_opened_at', 'last_clicked_at']);
        if ($row === null) {
            return;
        }

        $last = collect([$row->last_opened_at, $row->last_clicked_at])->filter()
            ->map(fn ($at) => Carbon::parse($at))->sortDesc()->first();

        DB::table('recipients')->where('id', $recipientId)->update([
            'engagement_score' => EngagementScore::compute(
                (int) $row->messages_sent,
                (int) $row->delivered,
                (int) $row->opened,
                (int) $row->clicked,
                $last,
            ),
        ]);
    }

    private function countsForRecipient(Message $message): bool
    {
        return $message->recipient_id !== null && ! $this->isTest($message);
    }

    private function isTest(Message $message): bool
    {
        return (bool) ($message->getAttribute('is_test') ?? false);
    }

    private function advanceStatusIfEarlier(Message $message, MessageStatus $target): void
    {
        $currentRank = self::FUNNEL_RANK[$message->status] ?? null;

        // Terminal/bounce/failure statuses (not present in FUNNEL_RANK) are
        // never overwritten by an open/click side effect.
        if ($currentRank === null) {
            return;
        }

        if ($currentRank < self::FUNNEL_RANK[$target->value]) {
            $message->status = $target->value;
        }
    }
}
