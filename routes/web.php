<?php

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
