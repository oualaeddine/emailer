<?php

namespace App\Providers;

use App\Modules\Campaigns\Services\Checklist\CampaignChecklistContract;
use App\Modules\Campaigns\Services\Checklist\CampaignChecklistService;
use App\Modules\DeliveryEngine\Services\SmtpConnectionTesterContract;
use App\Modules\DeliveryEngine\Services\SmtpManagerContract;
use App\Modules\DeliveryEngine\Services\SmtpManagerService;
use App\Modules\DeliveryEngine\Services\SymfonySmtpConnectionTester;
use App\Modules\Verification\Services\NullVerificationProvider;
use App\Modules\Verification\Services\VerificationProviderContract;
use App\Modules\Senders\Contracts\DnsResolverContract;
use App\Modules\Senders\Support\SystemDnsResolver;
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

        // docs/22-email-verification.md §22.3 — local syntax/MX/role-based
        // baseline in production (no third-party verification API is
        // integrated/configured in this codebase yet); tests bind a fake
        // implementation instead (docs/32-testing.md §32.4).
        $this->app->bind(VerificationProviderContract::class, NullVerificationProvider::class);

        // docs/45 §45.9 — DNS lookups for sending-domain verification (WP-41); tests bind FakeDnsResolver.
        $this->app->bind(DnsResolverContract::class, SystemDnsResolver::class);
        // docs/45-brevo-api-contracts.md §45.9 — WP-42 audience resolution contract.
        $this->app->bind(
            \App\Modules\Campaigns\Contracts\AudienceResolver::class,
            \App\Modules\Campaigns\Services\AudienceResolver::class,
        );
        // docs/45-brevo-api-contracts.md §45.9 — WP-45 pre-send checklist.
        $this->app->bind(CampaignChecklistContract::class, CampaignChecklistService::class);
        $this->app->bind(CampaignChecklistService::class);
        $this->app->scoped(\App\Modules\Campaigns\Support\ExternalReferences::class);
        // docs/45-brevo-api-contracts.md §45.9 — WP-54 rule-tree compiler (SegmentCompilerContract resolves via #[Bind]).
        $this->app->bind(\App\Modules\Recipients\Segments\Contracts\RuleTreeCompiler::class, \App\Modules\Recipients\Segments\Services\RuleTreeCompiler::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
