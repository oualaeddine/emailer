<?php

namespace App\Modules\Verification\Services;

use App\Modules\Verification\DataTransferObjects\VerificationOutcome;
use App\Modules\Verification\Enums\VerificationVerdict;

/**
 * docs/22-email-verification.md §22.3 — "Syntax/MX checks can run locally
 * even without a configured third-party provider (baseline verification
 * always available)." This is that local baseline, and the default
 * production binding: no third-party verification API (ZeroBounce/
 * NeverBounce/Kickbox-style, §22.3) is integrated or configured anywhere in
 * this codebase yet (no API key/Settings wiring exists), so a hardcoded
 * vendor adapter would be a fabrication — this local heuristic is the
 * sensible v1 default instead, matching the SMTP module's "provider
 * defaults configurable, not hardcoded" precedent
 * (docs/18-smtp-management.md §18.3).
 *
 * Performs (docs/22-email-verification.md §22.2):
 * - Syntax validation — always synchronous, free, no external call.
 * - MX validation — DNS MX (falling back to A) record lookup.
 * - Role-based detection — local-part pattern match (`info@`, `admin@`, …).
 *
 * Left `null` (unknown, not implemented): disposable-domain detection (needs
 * "a maintained disposable-domain list ... provider-supplied or a
 * bundled/updatable list in Settings", §22.2 — no such list/Settings
 * integration exists yet) and catch-all detection (needs a provider-side or
 * heuristic SMTP probe, §22.2 — no SMTP probing capability is wired here).
 *
 * Verdict computation (docs/22-email-verification.md §22.4): `undeliverable`
 * when syntax or MX is invalid; `risky` when the local-part is role-based;
 * otherwise `unknown` — no third-party provider is configured to confirm
 * `deliverable` with high confidence, and per §22.4 `unknown` is "treated as
 * 'not blocked' by default policy."
 */
class NullVerificationProvider implements VerificationProviderContract
{
    /**
     * @var list<string>
     */
    private const ROLE_BASED_LOCAL_PARTS = [
        'info', 'admin', 'support', 'noreply', 'no-reply', 'contact',
        'sales', 'webmaster', 'postmaster', 'abuse', 'hello',
    ];

    public function verify(string $email): VerificationOutcome
    {
        $syntaxValid = filter_var($email, FILTER_VALIDATE_EMAIL) !== false;

        if (! $syntaxValid) {
            return new VerificationOutcome(
                verdict: VerificationVerdict::Undeliverable,
                syntaxValid: false,
                provider: 'local',
            );
        }

        $domain = substr((string) strrchr($email, '@'), 1);
        $mxValid = $this->hasMailExchanger($domain);
        $isRoleBased = $this->isRoleBased($email);

        $verdict = match (true) {
            ! $mxValid => VerificationVerdict::Undeliverable,
            $isRoleBased => VerificationVerdict::Risky,
            default => VerificationVerdict::Unknown,
        };

        return new VerificationOutcome(
            verdict: $verdict,
            syntaxValid: true,
            mxValid: $mxValid,
            isDisposable: null,
            isRoleBased: $isRoleBased,
            isCatchAll: null,
            provider: 'local',
        );
    }

    private function hasMailExchanger(string $domain): bool
    {
        if ($domain === '') {
            return false;
        }

        return checkdnsrr($domain, 'MX') || checkdnsrr($domain, 'A');
    }

    private function isRoleBased(string $email): bool
    {
        $localPart = strtolower((string) strstr($email, '@', true));

        return in_array($localPart, self::ROLE_BASED_LOCAL_PARTS, true);
    }
}
