<?php

namespace App\Providers;

use App\Modules\DeliveryEngine\Services\SmtpConnectionTesterContract;
use App\Modules\DeliveryEngine\Services\SmtpManagerContract;
use App\Modules\DeliveryEngine\Services\SmtpManagerService;
use App\Modules\DeliveryEngine\Services\SymfonySmtpConnectionTester;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // The 'web' session guard implements StatefulGuard; bind the
        // interface so it can be constructor-injected (e.g. AuthService)
        // instead of resolving through the Auth facade everywhere.
        $this->app->bind(StatefulGuard::class, fn () => Auth::guard('web'));

        // docs/18-smtp-management.md §18.4 — real SMTP handshake in
        // production; tests bind a fake implementation instead
        // (docs/32-testing.md §32.4).
        $this->app->bind(SmtpConnectionTesterContract::class, SymfonySmtpConnectionTester::class);

        // docs/17-delivery-engine.md §17.6 — real SMTP send in production;
        // tests bind a fake implementation instead (docs/32-testing.md §32.4).
        $this->app->bind(SmtpManagerContract::class, SmtpManagerService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
