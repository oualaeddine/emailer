<?php

namespace App\Modules\DeliveryEngine\Scheduling\Jobs;

use App\Modules\DeliveryEngine\Scheduling\Services\BestSendTimeService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * docs/45 §45.8 Slot P / §45.9 — WP-81 nightly precompute: each engaged
 * contact's best hour of week into `recipients.best_send_hour` (+
 * `best_send_hour_computed_at`) and the account-wide best hour into the
 * cache ({@see BestSendTimeService::ACCOUNT_HOUR_CACHE_KEY}).
 * Scheduled daily in routes/console.php.
 */
class ComputeBestSendHoursJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3600;

    public int $uniqueFor = 3600;

    public function handle(BestSendTimeService $service): void
    {
        $service->refreshAccountBestHour();
        $service->precompute();
    }
}
