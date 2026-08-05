<?php

namespace App\Modules\PageJaunesIntegration\Jobs;

use App\Domain\Enums\SettingValueType;
use App\Modules\PageJaunesIntegration\Services\PageJaunesSyncService;
use App\Modules\Settings\Services\SettingsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * docs/30-background-jobs.md — `SyncPageJaunesCompaniesJob`, daily
 * (docs/06-pagejaunes-integration.md §6.5). Cursor-paginated sweep;
 * progress (`last_id`) is persisted in `settings` between runs via
 * SettingsService::setSystemValue(), so an interrupted run resumes
 * rather than restarting from zero.
 */
class SyncPageJaunesCompaniesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * docs/24-queue-management.md §24.1 — `maintenance` queue. This is the
     * periodic company-data reconciliation sweep (resumable cursor sync),
     * distinct from the `imports` queue's user-initiated file processing
     * (`ParseImportFileJob`/`CommitImportJob`), so it belongs with the
     * other low-priority background/health-probe workloads.
     */
    public string $queue = 'maintenance';

    private const PROGRESS_KEY = 'pagejaunes.last_synced_company_id';

    private const CHUNK_SIZE = 500;

    public function handle(PageJaunesSyncService $sync, SettingsService $settings): void
    {
        $sinceId = (int) $settings->get(self::PROGRESS_KEY, 0);

        do {
            $result = $sync->syncBatch($sinceId, self::CHUNK_SIZE);

            if ($result['last_id'] !== null) {
                $sinceId = $result['last_id'];
            }
        } while ($result['synced'] === self::CHUNK_SIZE);

        $settings->setSystemValue(self::PROGRESS_KEY, $sinceId, SettingValueType::Int);
    }
}
