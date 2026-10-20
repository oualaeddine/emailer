<?php

namespace App\Modules\Tracking\Models;

use App\Support\Concerns\HasUuid;
use Database\Factories\MessageEventFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * docs/45-brevo-api-contracts.md §45.8 Slot L — one engagement / delivery
 * event of a message. `event_type` uses MessageStatus-style names
 * (`sent`, `delivered`, `opened`, `clicked`, `hard_bounced`, `soft_bounced`,
 * `spam_complaint`, `unsubscribed`, `deferred`, `failed`, `blocked`);
 * `metadata` mirrors url / device_type / email_client / os / ip_country for
 * readers that only look at the JSON (WP-52 timeline).
 *
 * @property int $id
 * @property string $uuid
 * @property int $message_id
 * @property int|null $campaign_id
 * @property int|null $recipient_id
 * @property string $event_type
 * @property bool $is_proxy_open
 */
class MessageEvent extends Model
{
    /** @use HasFactory<MessageEventFactory> */
    use HasFactory, HasUuid;

    public const OPENED = 'opened';

    public const CLICKED = 'clicked';

    public const SENT = 'sent';

    public const DELIVERED = 'delivered';

    public const HARD_BOUNCED = 'hard_bounced';

    public const SOFT_BOUNCED = 'soft_bounced';

    public const COMPLAINT = 'spam_complaint';

    public const UNSUBSCRIBED = 'unsubscribed';

    public const DEFERRED = 'deferred';

    public const FAILED = 'failed';

    public const BLOCKED = 'blocked';

    protected $fillable = [
        'message_id', 'campaign_id', 'recipient_id', 'event_type', 'occurred_at', 'ip', 'metadata',
        'url', 'link_id', 'user_agent', 'device_type', 'email_client', 'ip_country', 'ip_city',
        'is_proxy_open',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'metadata' => 'array',
            'is_proxy_open' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Message, $this>
     */
    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    protected static function newFactory(): MessageEventFactory
    {
        return MessageEventFactory::new();
    }
}
