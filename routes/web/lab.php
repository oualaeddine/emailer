<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Lab (spikes, not linked from navigation)
|--------------------------------------------------------------------------
| docs/46-editor-spike.md — WP-60S GrapesJS + grapesjs-mjml spike. Removed
| once WP-60 ships the EmailBuilder.
*/

Route::get('lab/editor-spike', fn () => Inertia::render('Lab/EditorSpike'))
    ->middleware('can:templates.manage')
    ->name('lab.editor-spike');
