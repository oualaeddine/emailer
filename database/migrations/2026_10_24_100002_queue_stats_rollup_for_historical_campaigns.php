<?php

use App\Modules\Reporting\Jobs\RollupCampaignStatsJob;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The hourly RollupCampaignStatsJob only revisits campaigns active in the
 * last `tracking.stats.active_window_days`. Campaigns finished before the
 * Brevo-parity upgrade would therefore keep zero counters (sent, delivered,
 * opens, clicks…) in the campaign list, the report and the statistics page.
 * Queue one final rollup per historical campaign that has messages; the jobs
 * run on the normal queue after the deploy.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('campaigns')
            ->whereNotIn('status', ['draft', 'scheduled'])
            ->where('sent_count', 0)
            ->whereExists(fn ($q) => $q->select(DB::raw(1))->from('messages')->whereColumn('messages.campaign_id', 'campaigns.id'))
            ->orderBy('id')
            ->pluck('id')
            ->each(fn ($id) => RollupCampaignStatsJob::dispatch((int) $id, true));
    }

    public function down(): void
    {
        // Queued jobs only; nothing to undo.
    }
};
