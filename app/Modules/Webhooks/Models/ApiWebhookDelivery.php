<?php

namespace App\Modules\Webhooks\Models;

use App\Support\Concerns\HasUuid;
use Database\Factories\ApiWebhookDeliveryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * docs/45 §45.7.7 / §45.8 Slot S — one event delivery (with its retries) to a webhook.
 *
 * @property int $id
 * @property string $uuid
 * @property int $api_webhook_id
 * @property string $event payload `event` value
 * @property string $status pending|succeeded|failed|retrying
 * @property int $attempts
 * @property array<string, mixed> $payload
 * @property int|null $response_status
 * @property string|null $response_body_excerpt
 * @property int|null $duration_ms
 * @property \Illuminate\Support\Carbon|null $next_retry_at
 * @property \Illuminate\Support\Carbon|null $delivered_at
 * @property-read ApiWebhook $webhook
 */
class ApiWebhookDelivery extends Model
{
    /** @use HasFactory<ApiWebhookDeliveryFactory> */
    use HasFactory, HasUuid;

    public const PENDING = 'pending';

    public const SUCCEEDED = 'succeeded';

    public const FAILED = 'failed';

    public const RETRYING = 'retrying';

    protected $fillable = [
        'api_webhook_id', 'event', 'status', 'attempts', 'payload', 'response_status',
        'response_body_excerpt', 'duration_ms', 'next_retry_at', 'delivered_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'next_retry_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * @return BelongsTo<ApiWebhook, $this>
     */
    public function webhook(): BelongsTo
    {
        return $this->belongsTo(ApiWebhook::class, 'api_webhook_id');
    }

    protected static function newFactory(): ApiWebhookDeliveryFactory
    {
        return ApiWebhookDeliveryFactory::new();
    }
}
