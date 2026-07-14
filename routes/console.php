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

Schedule::job(new SyncPageJaunesCompaniesJob())->daily();
