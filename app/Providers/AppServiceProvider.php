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
        // docs/45 §45.3 — WP-57 async operations store (bound at the Wave 2 gate).
        $this->app->bind(\App\Modules\Recipients\Views\Contracts\AsyncOperations::class, \App\Modules\Recipients\Views\Services\AsyncOperationService::class);
        // docs/45 §45.9 — WP-70 campaign statistics contract + local GeoLite2 resolver (tests bind a fake).
        $this->app->bind(\App\Modules\Reporting\Contracts\CampaignStats::class, \App\Modules\Reporting\Services\CampaignStatsService::class);
        $this->app->singleton(\App\Modules\Tracking\Contracts\GeoIpResolver::class, \App\Modules\Tracking\Support\MaxMindGeoIpResolver::class);
        // docs/45 §45.9 (WP-100) — the single transactional send path (also called by WP-91 / WP-90).
        $this->app->bind(\App\Modules\Transactional\Contracts\TransactionalSender::class, \App\Modules\Transactional\Services\TransactionalSendService::class);
        // docs/45 §45.9 — WP-81 send at best time (CampaignService::BEST_SEND_TIME_SERVICE dispatcher hook).
        $this->app->bind(\App\Modules\DeliveryEngine\Scheduling\Contracts\BestSendTimeService::class, \App\Modules\DeliveryEngine\Scheduling\Services\BestSendTimeService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Wave 3 gate: literal `campaigns/compare` must not be captured by `campaigns/{campaign}`.
        \Illuminate\Support\Facades\Route::pattern('campaign', '[0-9a-fA-F-]{36}');
        // docs/45 §45.9 — WP-70: final rollup when a campaign completes.
        \Illuminate\Support\Facades\Event::listen(\App\Modules\Campaigns\Events\CampaignStatusChanged::class, \App\Modules\Tracking\Listeners\RollupCampaignStatsOnCompletion::class);
        // docs/45 §45.7.4 — WP-90: the double opt-in confirmation confirms the contact's pending form submissions.
        \Illuminate\Support\Facades\Event::listen(\App\Modules\Suppression\Preferences\Events\DoubleOptInConfirmed::class, \App\Modules\Forms\Listeners\ConfirmFormSubmissionsOnOptIn::class);
        // docs/45 §45.1 (WP-100) — API keys accepted as `Authorization: Bearer` or Brevo-style `api-key` header.
        \Laravel\Sanctum\Sanctum::getAccessTokenFromRequestUsing(fn (\Illuminate\Http\Request $request): ?string => \App\Modules\Transactional\Services\ApiKeyService::tokenFromRequest($request));
        // docs/45 §45.7.6 — a token stops authenticating once its owner is deactivated or (soft-)deleted.
        \Laravel\Sanctum\Sanctum::authenticateAccessTokensUsing(fn ($token, bool $isValid): bool => $isValid
            && $token->tokenable instanceof \App\Modules\Identity\Models\User
            && ! $token->tokenable->trashed()
            && (bool) $token->tokenable->is_active);
    }
}
