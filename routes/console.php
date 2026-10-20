<?php

use App\Modules\PageJaunesIntegration\Jobs\SyncPageJaunesCompaniesJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduled Tasks
|--------------------------------------------------------------------------
| docs/30-background-jobs.md §30.3 — Scheduler Configuration.
*/

Schedule::job(new SyncPageJaunesCompaniesJob)->daily();

Schedule::command('recipients:recompute-counters')->daily();
Schedule::command('exports:prune')->daily();

// docs/45 §45.9 — WP-70 campaign stats rollup (fans out per active campaign) and monthly GeoLite2 refresh.
Schedule::job(new \App\Modules\Reporting\Jobs\RollupCampaignStatsJob)->hourly();
Schedule::command('geoip:update')->monthly();

// docs/45 §45.7.7 — WP-101 webhook retry safety net + 30-day delivery log pruning.
Schedule::job(new \App\Modules\Webhooks\Jobs\SweepWebhookDeliveriesJob)->everyFiveMinutes();
