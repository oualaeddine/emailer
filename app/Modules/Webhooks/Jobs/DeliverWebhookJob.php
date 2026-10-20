<?php

namespace App\Modules\Webhooks\Jobs;

use App\Modules\Webhooks\Models\ApiWebhookDelivery;
use App\Modules\Webhooks\Services\WebhookDispatcher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

/** docs/45 §45.9 — one delivery attempt; re-dispatched with a delay for retries. */
class DeliverWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(public readonly int $deliveryId) {}

    public function handle(WebhookDispatcher $dispatcher): void
    {
        $lock = Cache::lock('webhook-delivery:'.$this->deliveryId, 60);
        if (! $lock->get()) {
            return;
        }

        try {
            $delivery = ApiWebhookDelivery::query()->with('webhook')->find($this->deliveryId);
            if ($delivery === null || ! in_array($delivery->status, [ApiWebhookDelivery::PENDING, ApiWebhookDelivery::RETRYING], true)) {
                return;
            }
            if ($delivery->next_retry_at !== null && $delivery->next_retry_at->isFuture()) {
                return; // duplicate dispatch (e.g. sweep) — the delayed job will run it
            }
            if (! $delivery->webhook->is_active) {
                $delivery->forceFill(['status' => ApiWebhookDelivery::FAILED, 'next_retry_at' => null, 'response_body_excerpt' => 'Webhook désactivé.'])->save();

                return;
            }

            $dispatcher->attempt($delivery);
        } finally {
            $lock->release();
        }
    }
}
