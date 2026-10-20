<?php

namespace App\Modules\Webhooks\Jobs;

use App\Modules\Webhooks\Models\ApiWebhookDelivery;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

/** Safety net: re-queues overdue retries lost by the queue, and prunes deliveries older than 30 days. */
class SweepWebhookDeliveriesJob implements ShouldQueue
{
    use Dispatchable, Queueable;

    public function handle(): void
    {
        ApiWebhookDelivery::query()
            ->where('status', ApiWebhookDelivery::RETRYING)
            ->where('next_retry_at', '<=', now()->subMinutes(5))
            ->pluck('id')
            ->each(fn (int $id) => DeliverWebhookJob::dispatch($id));

        ApiWebhookDelivery::query()->where('created_at', '<', now()->subDays(30))->delete();
    }
}
