<?php

namespace App\Modules\Webhooks\Services;

use App\Domain\Notifications\NotificationCategory;
use App\Domain\Notifications\NotificationCenterService;
use App\Modules\Identity\Models\User;
use App\Modules\Webhooks\Jobs\DeliverWebhookJob;
use App\Modules\Webhooks\Models\ApiWebhook;
use App\Modules\Webhooks\Models\ApiWebhookDelivery;
use App\Modules\Webhooks\Notifications\WebhookAutoDisabledNotification;
use App\Support\Network\PublicUrlGuard;
use App\Support\Network\UnsafeUrlException;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * docs/45 §45.7.7 — signs and POSTs one delivery attempt; schedules retries
 * (+1 min, +5 min, +30 min, +2 h, +12 h: 6 attempts) and auto-disables a
 * webhook after 50 consecutive failed deliveries.
 */
class WebhookDispatcher
{
    /** Seconds before attempt N+1, indexed by the number of attempts already made - 1. */
    public const BACKOFF_SECONDS = [60, 300, 1800, 7200, 43200];

    public const MAX_ATTEMPTS = 6;

    public const AUTO_DISABLE_AFTER = 50;

    public const TIMEOUT_SECONDS = 10;

    public const EXCERPT_LENGTH = 1000;

    public function __construct(
        private readonly PublicUrlGuard $guard,
        private readonly NotificationCenterService $notifications,
    ) {}

    public static function signature(string $secret, int $timestamp, string $body): string
    {
        return 'sha256='.hash_hmac('sha256', $timestamp.'.'.$body, $secret);
    }

    /**
     * One attempt, then retry scheduling. `$retry = false` (test endpoint) never schedules a retry
     * and leaves the webhook's failure counter alone.
     */
    public function attempt(ApiWebhookDelivery $delivery, bool $retry = true): ApiWebhookDelivery
    {
        $webhook = $delivery->webhook;
        $body = json_encode($delivery->payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $timestamp = time();
        $started = microtime(true);
        $status = null;
        $excerpt = null;
        $ok = false;

        try {
            // Re-checked before every delivery: DNS may have changed since creation.
            $resolved = $this->guard->resolve($webhook->url);

            $response = Http::withHeaders([
                'X-Mailer-Event' => (string) $delivery->event,
                'X-Mailer-Delivery' => $delivery->uuid,
                'X-Mailer-Timestamp' => (string) $timestamp,
                'X-Mailer-Signature' => self::signature($webhook->secret, $timestamp, $body),
            ] + ($webhook->headers ?? []))
                ->withBody($body, 'application/json')
                ->withOptions($this->guard->curlOptions($resolved))
                ->timeout(self::TIMEOUT_SECONDS)
                ->post($webhook->url);

            $status = $response->status();
            $excerpt = mb_substr($response->body(), 0, self::EXCERPT_LENGTH);
            $ok = $response->successful();
        } catch (UnsafeUrlException) {
            $excerpt = 'URL refusée : hôte non public.';
        } catch (Throwable $e) {
            $excerpt = mb_substr($e->getMessage(), 0, self::EXCERPT_LENGTH);
        }

        $attempts = $delivery->attempts + 1;
        $attrs = [
            'attempts' => $attempts,
            'response_status' => $status,
            'response_body_excerpt' => $excerpt === '' ? null : $excerpt,
            'duration_ms' => (int) round((microtime(true) - $started) * 1000),
        ];

        if ($ok) {
            $delivery->forceFill($attrs + ['status' => ApiWebhookDelivery::SUCCEEDED, 'next_retry_at' => null, 'delivered_at' => now()])->save();
            if ($retry) {
                $webhook->forceFill(['last_delivery_at' => now(), 'last_delivery_status' => ApiWebhookDelivery::SUCCEEDED, 'failure_count' => 0])->save();
            }

            return $delivery;
        }

        if ($retry && $attempts < self::MAX_ATTEMPTS) {
            $delay = self::BACKOFF_SECONDS[$attempts - 1];
            $delivery->forceFill($attrs + ['status' => ApiWebhookDelivery::RETRYING, 'next_retry_at' => now()->addSeconds($delay)])->save();
            $webhook->forceFill(['last_delivery_at' => now(), 'last_delivery_status' => ApiWebhookDelivery::RETRYING])->save();
            DeliverWebhookJob::dispatch($delivery->id)->delay(now()->addSeconds($delay));

            return $delivery;
        }

        $delivery->forceFill($attrs + ['status' => ApiWebhookDelivery::FAILED, 'next_retry_at' => null])->save();
        if ($retry) {
            $this->recordFailure($webhook);
        }

        return $delivery;
    }

    private function recordFailure(ApiWebhook $webhook): void
    {
        $webhook->failure_count++;
        $webhook->last_delivery_at = now();
        $webhook->last_delivery_status = ApiWebhookDelivery::FAILED;

        $disable = $webhook->is_active && $webhook->failure_count >= self::AUTO_DISABLE_AFTER;
        if ($disable) {
            $webhook->is_active = false;
        }
        $webhook->save();

        if ($disable) {
            $admins = User::query()->whereHas('role.permissions', fn ($q) => $q->where('name', 'webhooks.manage'))->get();
            $this->notifications->notify(NotificationCategory::DeliveryInfrastructure, $admins, new WebhookAutoDisabledNotification($webhook->url));
        }
    }
}
