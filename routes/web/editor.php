<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Email editor host pages (WP-60)
|--------------------------------------------------------------------------
| docs/43-brevo-parity-plan.md §43.4.4 / docs/45 §45.6.1 — the drag & drop
| EmailBuilder opened for a template or for a campaign's design step.
| Required from routes/web/app.php inside the `auth` middleware group.
*/

Route::get('templates/{template}/edit', fn (string $template) => Inertia::render('Templates/Edit', [
    'templateId' => $template,
]))
    ->whereUuid('template')
    ->middleware('can:templates.manage')
    ->name('templates.edit');

Route::get('campaigns/{campaign}/design', fn (string $campaign) => Inertia::render('Campaigns/Design', [
    'campaignId' => $campaign,
]))
    ->whereUuid('campaign')
    ->middleware('can:campaigns.create')
    ->name('campaigns.design');
