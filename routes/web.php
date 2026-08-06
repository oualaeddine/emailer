<?php

use App\Modules\Suppression\Http\Controllers\UnsubscribeController;
use App\Modules\Tracking\Http\Controllers\TrackClickController;
use App\Modules\Tracking\Http\Controllers\TrackOpenController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes (Inertia pages)
|--------------------------------------------------------------------------
| docs/08-navigation.md §8.3 — Route Map.
|
| docs/42-parallel-execution-plan.md §42.5 (WP-00) — split into
| routes/web/{auth,app}.php so future modules never edit this shared
| file. Per §42.11, later work packages only append one `require` line
| inside the appropriate middleware group below.
*/

Route::middleware('guest')->group(function (): void {
    require __DIR__.'/web/auth.php';
});

Route::middleware('auth')->group(function (): void {
    require __DIR__.'/web/app.php';
});

/*
|--------------------------------------------------------------------------
| Public Unsubscribe Routes (WP-11)
|--------------------------------------------------------------------------
| docs/23-suppression-list.md §23.4, docs/29-api-specification.md §29.8 —
| GET/POST /unsubscribe/{token}: public, unauthenticated, no session guard.
| Not a member of the `guest`/`auth` groups above (this isn't a login-guest
| page nor does it require a session) — its own top-level group, gated by
| Laravel's `signed` URL middleware instead.
*/
Route::middleware('signed')->group(function (): void {
    Route::get('unsubscribe/{token}', [UnsubscribeController::class, 'show'])->name('unsubscribe.show');
    Route::post('unsubscribe/{token}', [UnsubscribeController::class, 'confirm'])->name('unsubscribe.confirm');
});

/*
|--------------------------------------------------------------------------
| Public Open/Click Tracking Routes (WP-21)
|--------------------------------------------------------------------------
| docs/21-open-click-tracking.md §21.1-21.2 — GET /t/o/{message}.gif
| (tracking pixel) and GET /t/c/{message}?url=... (click redirect): public,
| unauthenticated, no session guard, same rationale as the unsubscribe
| group above — its own top-level group gated by the `signed` URL
| middleware rather than a member of `guest`/`auth`, mirroring that
| group's exact pattern.
|
| `{message}` binds via Message::getRouteKeyName() (`uuid`,
| docs/04-database-design.md §4.10) and is constrained to the UUID shape
| so the literal `.gif` suffix on the pixel route can't be swallowed into
| the parameter. The constraint anchors dash positions (8-4-4-4-12), not
| just character class + length — `messages.uuid` is a native Postgres
| `uuid` column, which throws a raw SQL exception (500) for a
| malformed-but-same-length value like 36 `a`s; a route pattern that
| actually enforces UUID shape rejects it at the router (404) before it
| ever reaches the database.
*/
Route::middleware('signed')->group(function (): void {
    Route::get('t/o/{message}.gif', [TrackOpenController::class, 'open'])
        ->whereUuid('message')
        ->name('tracking.open');

    Route::get('t/c/{message}', [TrackClickController::class, 'redirect'])
        ->whereUuid('message')
        ->name('tracking.click');
});
