<?php

namespace Tests\Support;

use App\Modules\Verification\DataTransferObjects\VerificationOutcome;
use App\Modules\Verification\Enums\VerificationVerdict;
use App\Modules\Verification\Services\VerificationProviderContract;

/**
 * docs/32-testing.md §32.4-style fake adapter — mirrors
 * `Tests\Support\FakeSmtpConnectionTester`. Tests set `$verdict` (applied to
 * every email checked) or a per-email override via `$verdictsByEmail`
 * before exercising the verify endpoints/jobs, then assert on the
 * persisted `verification_results` row and/or `recipients.status`.
 */
class FakeVerificationProvider implements VerificationProviderContract
{
    public static VerificationVerdict $verdict = VerificationVerdict::Deliverable;

    /**
     * @var array<string, VerificationVerdict>
     */
    public static array $verdictsByEmail = [];

    public function verify(string $email): VerificationOutcome
    {
        $verdict = self::$verdictsByEmail[$email] ?? self::$verdict;

        return new VerificationOutcome(
            verdict: $verdict,
            syntaxValid: true,
            mxValid: $verdict !== VerificationVerdict::Undeliverable,
            isDisposable: false,
            isRoleBased: $verdict === VerificationVerdict::Risky,
            isCatchAll: false,
            provider: 'fake',
            rawResponse: ['fake' => true, 'verdict' => $verdict->value],
        );
    }

    public static function reset(): void
    {
        self::$verdict = VerificationVerdict::Deliverable;
        self::$verdictsByEmail = [];
    }
}
