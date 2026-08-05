<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
| docs/29-api-specification.md §29.1 — base path /api/v1, Sanctum session
| auth via the `auth:sanctum` guard (stateful SPA requests).
|
| docs/42-parallel-execution-plan.md §42.5 (WP-00) — split into per-module
| files under routes/api/ so future modules never edit this shared file.
| Per §42.11, later work packages only append one `require` line at the
| bottom of this group; the order below mirrors the original route
| declaration order for a stable route-list output.
*/

Route::prefix('v1')->middleware('auth:sanctum')->group(function (): void {
    require __DIR__.'/api/identity.php';
    require __DIR__.'/api/settings.php';
    require __DIR__.'/api/pagejaunes.php';
    require __DIR__.'/api/recipients.php';
    require __DIR__.'/api/importing.php';
    require __DIR__.'/api/composer.php';
    require __DIR__.'/api/templates.php';
    require __DIR__.'/api/delivery.php';
    require __DIR__.'/api/tracking.php';
});
