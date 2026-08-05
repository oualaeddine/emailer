<?php

namespace App\Modules\Verification\Services;

use App\Modules\Verification\DataTransferObjects\VerificationOutcome;

/**
 * docs/22-email-verification.md §22.3 — Provider Abstraction: a
 * provider-agnostic interface with a swappable adapter (configurable in
 * Settings → Verification Provider: provider name, API key, endpoint), so
 * the platform can integrate any third-party verification API (ZeroBounce,
 * NeverBounce, Kickbox-style services) without coupling core logic to one
 * vendor — mirrors the SMTP provider-agnostic pattern
 * (docs/18-smtp-management.md §18.3).
 *
 * Mirrors `App\Modules\DeliveryEngine\Services\SmtpConnectionTesterContract`:
 * bound to a concrete implementation in `AppServiceProvider`, with
 * `Tests\Support\FakeVerificationProvider` bound instead in tests.
 */
interface VerificationProviderContract
{
    public function verify(string $email): VerificationOutcome;
}
