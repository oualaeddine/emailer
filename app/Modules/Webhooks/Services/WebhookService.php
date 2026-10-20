<?php

namespace App\Modules\Webhooks\Services;

use App\Modules\Audit\Services\AuditLogger;
use App\Modules\Identity\Models\User;
use App\Modules\Webhooks\Jobs\DeliverWebhookJob;
use App\Modules\Webhooks\Models\ApiWebhook;
use App\Modules\Webhooks\Models\ApiWebhookDelivery;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;

/** docs/45 §45.7.7 — webhook CRUD, secret handling, delivery fan-out. */
class WebhookService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** @return Collection<int, ApiWebhook> */
    public function list(?string $type = null): Collection
    {
        return ApiWebhook::query()
            ->when($type !== null, fn ($q) => $q->where('type', $type))
            ->orderByDesc('id')
            ->get();
    }

    public function findByUuid(string $uuid): ApiWebhook
    {
        return ApiWebhook::query()->where('uuid', $uuid)->firstOr(fn () => throw (new ModelNotFoundException)->setModel(ApiWebhook::class));
    }

    public function findDelivery(string $uuid): ApiWebhookDelivery
    {
        return ApiWebhookDelivery::query()->where('uuid', $uuid)->with('webhook')->firstOr(fn () => throw (new ModelNotFoundException)->setModel(ApiWebhookDelivery::class));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{0: ApiWebhook, 1: string} webhook + plain secret (shown once)
     */
    public function create(array $data, ?User $actor): array
    {
        $secret = $this->newSecret();
        $webhook = ApiWebhook::query()->create([
            'url' => $data['url'],
            'description' => $data['description'] ?? null,
            'type' => $data['type'],
            'events' => self::normalizeEvents($data['events']),
            'secret' => $secret,
            'headers' => $data['headers'] ?? null ?: null,
            'is_active' => $data['is_active'] ?? true,
            'created_by' => $actor?->id,
        ]);

        $webhook->refresh();
        $this->audit->record('webhook.created', $webhook, $actor, null, [
            'url' => $webhook->url, 'type' => $webhook->type, 'events' => $webhook->events,
        ]);

        return [$webhook, $secret];
    }

    /** @param  array<string, mixed>  $data */
    public function update(ApiWebhook $webhook, array $data): ApiWebhook
    {
        if (array_key_exists('events', $data)) {
            $data['events'] = self::normalizeEvents($data['events']);
        }
        if (array_key_exists('headers', $data) && $data['headers'] === []) {
            $data['headers'] = null;
        }
        $reactivated = ($data['is_active'] ?? false) === true && ! $webhook->is_active;

        $webhook->fill(array_intersect_key($data, array_flip(['url', 'description', 'type', 'events', 'headers', 'is_active'])));
        if ($reactivated) {
            $webhook->failure_count = 0;
        }
        $webhook->save();

        return $webhook;
    }

    public function delete(ApiWebhook $webhook, ?User $actor): void
    {
        $this->audit->record('webhook.deleted', $webhook, $actor, ['url' => $webhook->url, 'type' => $webhook->type], null);
        $webhook->delete();
    }

    /** @return array{0: ApiWebhook, 1: string} */
    public function rotateSecret(ApiWebhook $webhook, ?User $actor): array
    {
        $secret = $this->newSecret();
        $webhook->forceFill(['secret' => $secret])->save();
        $this->audit->record('webhook.secret_rotated', $webhook, $actor);

        return [$webhook, $secret];
    }

    /**
     * Active webhooks of `$type` subscribed to `$subscription`.
     *
     * @return Collection<int, ApiWebhook>
     */
    public function subscribers(string $type, string $subscription): Collection
    {
        return ApiWebhook::query()
            ->where('is_active', true)
            ->where('type', $type)
            ->whereJsonContains('events', $subscription)
            ->get();
    }

    /**
     * Logs a pending delivery and queues its job.
     *
     * @param  array<string, mixed>  $payload
     */
    public function enqueue(ApiWebhook $webhook, string $payloadEvent, array $payload): ApiWebhookDelivery
    {
        $delivery = ApiWebhookDelivery::query()->create([
            'api_webhook_id' => $webhook->id,
            'event' => $payloadEvent,
            'status' => ApiWebhookDelivery::PENDING,
            'attempts' => 0,
            'payload' => $payload,
        ]);

        DeliverWebhookJob::dispatch($delivery->id);

        return $delivery;
    }

    /**
     * "sent" and "request" are synonyms; stored as "request". Deduplicated, order kept.
     *
     * @param  array<int, string>  $events
     * @return list<string>
     */
    public static function normalizeEvents(array $events): array
    {
        return array_values(array_unique(array_map(fn (string $e) => $e === 'sent' ? 'request' : $e, $events)));
    }

    private function newSecret(): string
    {
        return 'whsec_'.Str::random(40);
    }
}
