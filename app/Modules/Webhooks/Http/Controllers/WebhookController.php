<?php

namespace App\Modules\Webhooks\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Webhooks\Http\Requests\TestWebhookRequest;
use App\Modules\Webhooks\Http\Requests\WebhookRequest;
use App\Modules\Webhooks\Http\Resources\WebhookDeliveryResource;
use App\Modules\Webhooks\Http\Resources\WebhookResource;
use App\Modules\Webhooks\Jobs\DeliverWebhookJob;
use App\Modules\Webhooks\Models\ApiWebhook;
use App\Modules\Webhooks\Models\ApiWebhookDelivery;
use App\Modules\Webhooks\Services\WebhookDispatcher;
use App\Modules\Webhooks\Services\WebhookPayloadBuilder;
use App\Modules\Webhooks\Services\WebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

/** docs/45 §45.7.7 — /api/v1/webhooks (permission `webhooks.manage`, enforced by the route middleware). */
class WebhookController extends Controller
{
    public function __construct(private readonly WebhookService $webhooks) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $type = $request->validate(['type' => ['nullable', Rule::in([ApiWebhook::TYPE_TRANSACTIONAL, ApiWebhook::TYPE_MARKETING])]])['type'] ?? null;

        return WebhookResource::collection($this->webhooks->list($type));
    }

    public function store(WebhookRequest $request): JsonResponse
    {
        [$webhook, $secret] = $this->webhooks->create($request->validated(), $request->user());

        return $this->created($webhook, $secret, 201);
    }

    public function show(string $id): WebhookResource
    {
        return new WebhookResource($this->webhooks->findByUuid($id));
    }

    public function update(WebhookRequest $request, string $id): WebhookResource
    {
        return new WebhookResource($this->webhooks->update($this->webhooks->findByUuid($id), $request->validated()));
    }

    public function destroy(Request $request, string $id): Response
    {
        $this->webhooks->delete($this->webhooks->findByUuid($id), $request->user());

        return response()->noContent();
    }

    public function rotateSecret(Request $request, string $id): JsonResponse
    {
        [$webhook, $secret] = $this->webhooks->rotateSecret($this->webhooks->findByUuid($id), $request->user());

        return $this->created($webhook, $secret, 200);
    }

    /** Synchronous single attempt of a sample payload (no retry, no failure counting). */
    public function test(TestWebhookRequest $request, string $id, WebhookPayloadBuilder $payloads, WebhookDispatcher $dispatcher): JsonResponse
    {
        $webhook = $this->webhooks->findByUuid($id);
        $subscription = $request->validated('event') === 'sent' ? 'request' : $request->validated('event');

        $delivery = ApiWebhookDelivery::query()->create([
            'api_webhook_id' => $webhook->id,
            'event' => WebhookPayloadBuilder::payloadEvent($webhook->type, $subscription),
            'status' => ApiWebhookDelivery::PENDING,
            'attempts' => 0,
            'payload' => $payloads->sample($webhook, $subscription),
        ]);
        $delivery->setRelation('webhook', $webhook);

        return (new WebhookDeliveryResource($dispatcher->attempt($delivery, retry: false)))->response()->setStatusCode(200);
    }

    public function deliveries(Request $request, string $id): AnonymousResourceCollection
    {
        $webhook = $this->webhooks->findByUuid($id);
        $filters = $request->validate([
            'status' => ['nullable', Rule::in([ApiWebhookDelivery::PENDING, ApiWebhookDelivery::SUCCEEDED, ApiWebhookDelivery::FAILED, ApiWebhookDelivery::RETRYING])],
            'event' => ['nullable', 'string', 'max:32'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $deliveries = $webhook->deliveries()
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($filters['event'] ?? null, fn ($q, $v) => $q->where('event', $v))
            ->with('webhook')
            ->orderByDesc('id')
            ->cursorPaginate($filters['per_page'] ?? 25);

        return WebhookDeliveryResource::collection($deliveries);
    }

    /** Manual redelivery: a fresh attempt cycle (attempt counter reset) queued immediately. */
    public function retry(string $id): JsonResponse
    {
        $delivery = $this->webhooks->findDelivery($id);
        $delivery->forceFill(['status' => ApiWebhookDelivery::PENDING, 'attempts' => 0, 'next_retry_at' => null, 'delivered_at' => null])->save();
        DeliverWebhookJob::dispatch($delivery->id);

        return (new WebhookDeliveryResource($delivery))->response()->setStatusCode(202);
    }

    private function created(ApiWebhook $webhook, string $secret, int $status): JsonResponse
    {
        return response()->json(['data' => [
            'webhook' => (new WebhookResource($webhook))->resolve(),
            'secret' => $secret,
        ]], $status);
    }
}
