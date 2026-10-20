<?php

namespace App\Modules\Webhooks\Models;

use App\Support\Concerns\HasUuid;
use Database\Factories\ApiWebhookFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * docs/45 §45.7.7 / §45.8 Slot S — an outgoing webhook.
 *
 * @property int $id
 * @property string $uuid
 * @property string $url
 * @property string|null $description
 * @property string $type transactional|marketing
 * @property list<string> $events subscription identifiers (Brevo, `sent` stored as `request`)
 * @property string $secret
 * @property array<string, string>|null $headers
 * @property bool $is_active
 * @property int $failure_count
 * @property \Illuminate\Support\Carbon|null $last_delivery_at
 * @property string|null $last_delivery_status
 */
class ApiWebhook extends Model
{
    /** @use HasFactory<ApiWebhookFactory> */
    use HasFactory, HasUuid;

    public const TYPE_TRANSACTIONAL = 'transactional';

    public const TYPE_MARKETING = 'marketing';

    /** @var list<string> */
    public const TRANSACTIONAL_EVENTS = [
        'request', 'delivered', 'opened', 'uniqueOpened', 'click', 'softBounce', 'hardBounce', 'invalid',
        'deferred', 'spam', 'unsubscribed', 'blocked', 'error', 'proxyOpen',
    ];

    /** @var list<string> */
    public const MARKETING_EVENTS = [
        'delivered', 'opened', 'click', 'softBounce', 'hardBounce', 'spam', 'unsubscribed',
        'listAddition', 'contactUpdated', 'contactDeleted',
    ];

    protected $fillable = ['url', 'description', 'type', 'events', 'secret', 'headers', 'is_active', 'created_by'];

    protected $hidden = ['secret'];

    protected function casts(): array
    {
        return [
            'events' => 'array',
            'secret' => 'encrypted',
            'headers' => 'encrypted:array',
            'is_active' => 'boolean',
            'last_delivery_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * @return HasMany<ApiWebhookDelivery, $this>
     */
    public function deliveries(): HasMany
    {
        return $this->hasMany(ApiWebhookDelivery::class);
    }

    /** @return list<string> */
    public static function eventsFor(string $type): array
    {
        return $type === self::TYPE_MARKETING ? self::MARKETING_EVENTS : self::TRANSACTIONAL_EVENTS;
    }

    public function secretHint(): string
    {
        return '…'.substr($this->secret, -4);
    }

    protected static function newFactory(): ApiWebhookFactory
    {
        return ApiWebhookFactory::new();
    }
}
