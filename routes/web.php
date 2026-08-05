<?php

use App\Modules\Suppression\Http\Controllers\UnsubscribeController;
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
